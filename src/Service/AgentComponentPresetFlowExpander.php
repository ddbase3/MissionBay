<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Service;

use MissionBay\Api\IAgentComponentPresetFlowExpander;
use MissionBay\Api\IAgentComponentPresetRepository;

/**
 * Canonical flow-definition expansion for component presets and recursive docks.
 */
final class AgentComponentPresetFlowExpander implements IAgentComponentPresetFlowExpander {

	public function __construct(private readonly IAgentComponentPresetRepository $presetRepository) {}

	public function expand(array $flow, array $presetIds): array {
		$flow = $this->normalizeFlow($flow);
		$resourceIds = [];
		$resolving = [];
		$warnings = [];

		foreach($presetIds as $presetId) {
			$presetId = trim((string)$presetId);
			if($presetId === '') {
				continue;
			}

			$preset = $this->presetRepository->getPreset($presetId, []);
			if($preset === []) {
				$warnings[] = 'Component preset not found: ' . $presetId;
				continue;
			}
			if(!$this->isEnabled($preset)) {
				$warnings[] = 'Component preset is disabled: ' . $presetId;
				continue;
			}

			$resourceIds[$presetId] = $this->ensurePresetResource(
				$flow,
				$presetId,
				$preset,
				$resourceIds,
				$resolving,
				$warnings
			);
		}

		return [
			'flow' => $flow,
			'resource_ids' => $resourceIds,
			'warnings' => array_values(array_unique($warnings))
		];
	}

	/**
	 * @param array<string,mixed> $flow
	 * @param array<string,mixed> $preset
	 * @param array<string,string> $resourceIds
	 * @param array<string,bool> $resolving
	 * @param array<int,string> $warnings
	 */
	private function ensurePresetResource(
		array &$flow,
		string $presetId,
		array $preset,
		array &$resourceIds,
		array &$resolving,
		array &$warnings
	): string {
		if(!empty($resolving[$presetId])) {
			$resourceId = $resourceIds[$presetId] ?? $this->buildResourceId($presetId);
			$warnings[] = 'Circular preset dock reference detected: ' . $presetId;
			return $resourceId;
		}

		if(isset($resourceIds[$presetId])) {
			return $resourceIds[$presetId];
		}

		$resourceId = $this->buildResourceId($presetId);
		$resourceIds[$presetId] = $resourceId;

		if($this->resourceExists($flow, $resourceId)) {
			return $resourceId;
		}

		$type = trim((string)($preset['type'] ?? ''));
		if($type === '') {
			$warnings[] = 'Component preset has no type: ' . $presetId;
			return $resourceId;
		}

		$resolving[$presetId] = true;
		$resource = [
			'id' => $resourceId,
			'type' => $type
		];

		if(!empty($preset['config']) && is_array($preset['config'])) {
			$resource['config'] = $preset['config'];
		}

		$docks = $this->buildPresetResourceDocks($flow, $preset, $resourceIds, $resolving, $warnings);
		if($docks !== []) {
			$resource['docks'] = $docks;
		}

		$flow['resources'][] = $resource;
		unset($resolving[$presetId]);

		return $resourceId;
	}

	/**
	 * @param array<string,mixed> $flow
	 * @param array<string,mixed> $preset
	 * @param array<string,string> $resourceIds
	 * @param array<string,bool> $resolving
	 * @param array<int,string> $warnings
	 * @return array<string,array<int,string>>
	 */
	private function buildPresetResourceDocks(
		array &$flow,
		array $preset,
		array &$resourceIds,
		array &$resolving,
		array &$warnings
	): array {
		$docks = [];

		if(empty($preset['docks']) || !is_array($preset['docks'])) {
			return $docks;
		}

		foreach($preset['docks'] as $dockName => $targets) {
			if(!is_string($dockName)) {
				continue;
			}

			$targetIds = [];
			foreach((array)$targets as $targetId) {
				$targetId = trim((string)$targetId);
				if($targetId === '') {
					continue;
				}

				$targetPreset = $this->presetRepository->getPreset($targetId, []);
				if($targetPreset !== []) {
					$targetIds[] = $this->ensurePresetResource(
						$flow,
						$targetId,
						$targetPreset,
						$resourceIds,
						$resolving,
						$warnings
					);
					continue;
				}

				$targetIds[] = $targetId;
			}

			if($targetIds !== []) {
				$docks[$dockName] = array_values(array_unique($targetIds));
			}
		}

		return $docks;
	}

	/** @param array<string,mixed> $flow */
	private function resourceExists(array $flow, string $resourceId): bool {
		foreach($flow['resources'] as $resource) {
			if(is_array($resource) && (string)($resource['id'] ?? '') === $resourceId) {
				return true;
			}
		}

		return false;
	}

	private function buildResourceId(string $presetId): string {
		$id = trim('preset_' . $presetId);
		$id = (string)preg_replace('/[^A-Za-z0-9_]+/', '_', $id);
		$id = trim($id, '_');

		if($id === '') {
			return 'component';
		}
		if(preg_match('/^[0-9]/', $id)) {
			$id = 'component_' . $id;
		}

		return strtolower($id);
	}

	/** @param array<string,mixed> $flow @return array<string,mixed> */
	private function normalizeFlow(array $flow): array {
		if(!isset($flow['nodes']) || !is_array($flow['nodes'])) {
			$flow['nodes'] = [];
		}
		if(!isset($flow['resources']) || !is_array($flow['resources'])) {
			$flow['resources'] = [];
		}
		if(!isset($flow['connections']) || !is_array($flow['connections'])) {
			$flow['connections'] = [];
		}

		return $flow;
	}

	/** @param array<string,mixed> $data */
	private function isEnabled(array $data): bool {
		if(!array_key_exists('enabled', $data)) {
			return true;
		}

		$value = $data['enabled'];
		if(is_bool($value)) {
			return $value;
		}
		if(is_int($value)) {
			return $value !== 0;
		}

		return !in_array(strtolower(trim((string)$value)), ['0', 'false', 'no', 'off'], true);
	}
}
