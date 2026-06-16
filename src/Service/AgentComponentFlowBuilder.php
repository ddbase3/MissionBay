<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace MissionBay\Service;

use MissionBay\Api\IAgentComponentFlowBuilder;
use MissionBay\Api\IAgentComponentPresetRepository;

/**
 * AgentComponentFlowBuilder
 *
 * Builds an effective AgentFlow by adding configured tool and memory wrappers
 * for selected component presets.
 */
class AgentComponentFlowBuilder implements IAgentComponentFlowBuilder {

	private const ASSISTANT_NODE_TYPE = 'streamingaiassistantnode';
	private const TOOL_WRAPPER_TYPE = 'configuredagenttoolresource';
	private const MEMORY_WRAPPER_TYPE = 'configuredagentmemoryresource';

	/**
	 * @var array<int,string>
	 */
	private array $warnings = [];

	/**
	 * @var array<string,string>
	 */
	private array $presetResourceIds = [];

	/**
	 * @var array<string,bool>
	 */
	private array $resolvingPresets = [];

	public function __construct(private readonly IAgentComponentPresetRepository $presetRepository) {}

	public static function getName(): string {
		return 'agentcomponentflowbuilder';
	}

	public function build(array $baseFlow, array $components, string $assistantNodeId = 'assistant'): array {
		$this->warnings = [];
		$this->presetResourceIds = [];
		$this->resolvingPresets = [];

		$flow = $this->normalizeFlow($baseFlow);
		$assistantIndex = $this->findAssistantNodeIndex($flow, $assistantNodeId);

		if ($assistantIndex === null) {
			$this->warnings[] = 'Assistant node not found: ' . $assistantNodeId;
			return $flow;
		}

		foreach ($components as $index => $component) {
			if (!is_array($component)) {
				$this->warnings[] = 'Component entry is not an array at index ' . $index . '.';
				continue;
			}

			$this->applyComponent($flow, $assistantIndex, $component, $index);
		}

		return $flow;
	}

	public function getWarnings(): array {
		return $this->warnings;
	}

	/**
	 * @param array<string,mixed> $flow
	 * @param array<string,mixed> $component
	 */
	private function applyComponent(array &$flow, int $assistantIndex, array $component, int $index): void {
		if (!$this->isEnabled($component)) {
			return;
		}

		$presetId = trim((string)($component['preset'] ?? ''));

		if ($presetId === '') {
			$this->warnings[] = 'Component has no preset at index ' . $index . '.';
			return;
		}

		$preset = $this->presetRepository->getPreset($presetId, []);

		if ($preset === []) {
			$this->warnings[] = 'Component preset not found: ' . $presetId;
			return;
		}

		if (!$this->isEnabled($preset)) {
			$this->warnings[] = 'Component preset is disabled: ' . $presetId;
			return;
		}

		$baseResourceId = $this->ensurePresetResource($flow, $presetId, $preset);
		$attachAs = $this->normalizeStringList($component['attach_as'] ?? ($preset['capabilities'] ?? []));

		if (in_array('tool', $attachAs, true)) {
			$this->addConfiguredTool($flow, $assistantIndex, $presetId, $baseResourceId, $component, $index);
		}

		if (in_array('memory', $attachAs, true)) {
			$this->addConfiguredMemory($flow, $assistantIndex, $presetId, $baseResourceId, $component, $index);
		}
	}

	/**
	 * @param array<string,mixed> $flow
	 * @param array<string,mixed> $preset
	 */
	private function ensurePresetResource(array &$flow, string $presetId, array $preset): string {
		if (isset($this->presetResourceIds[$presetId])) {
			return $this->presetResourceIds[$presetId];
		}

		$resourceId = $this->buildResourceId('preset_', $presetId);
		$this->presetResourceIds[$presetId] = $resourceId;

		if ($this->resourceExists($flow, $resourceId)) {
			return $resourceId;
		}

		if (!empty($this->resolvingPresets[$presetId])) {
			$this->warnings[] = 'Circular preset dock reference detected: ' . $presetId;
			return $resourceId;
		}

		$type = trim((string)($preset['type'] ?? ''));

		if ($type === '') {
			$this->warnings[] = 'Component preset has no type: ' . $presetId;
			return $resourceId;
		}

		$this->resolvingPresets[$presetId] = true;

		$resource = [
			'id' => $resourceId,
			'type' => $type
		];

		if (!empty($preset['config']) && is_array($preset['config'])) {
			$resource['config'] = $preset['config'];
		}

		$docks = $this->buildPresetResourceDocks($flow, $preset);

		if ($docks !== []) {
			$resource['docks'] = $docks;
		}

		$flow['resources'][] = $resource;

		unset($this->resolvingPresets[$presetId]);

		return $resourceId;
	}

	/**
	 * @param array<string,mixed> $flow
	 * @param array<string,mixed> $preset
	 * @return array<string,array<int,string>>
	 */
	private function buildPresetResourceDocks(array &$flow, array $preset): array {
		$docks = [];

		if (empty($preset['docks']) || !is_array($preset['docks'])) {
			return $docks;
		}

		foreach ($preset['docks'] as $dockName => $targets) {
			if (!is_string($dockName)) {
				continue;
			}

			$targetIds = [];

			foreach ((array)$targets as $targetId) {
				$targetId = trim((string)$targetId);

				if ($targetId === '') {
					continue;
				}

				$targetPreset = $this->presetRepository->getPreset($targetId, []);

				if ($targetPreset !== []) {
					$targetIds[] = $this->ensurePresetResource($flow, $targetId, $targetPreset);
					continue;
				}

				$targetIds[] = $targetId;
			}

			if ($targetIds !== []) {
				$docks[$dockName] = array_values(array_unique($targetIds));
			}
		}

		return $docks;
	}

	/**
	 * @param array<string,mixed> $flow
	 * @param array<string,mixed> $component
	 */
	private function addConfiguredTool(array &$flow, int $assistantIndex, string $presetId, string $baseResourceId, array $component, int $index): void {
		$wrapperId = $this->buildUniqueResourceId($flow, 'configured_tool_' . $this->sanitizeId($presetId) . '_' . $index);
		$config = $this->normalizeConfig($component['tool_config'] ?? []);

		if (isset($component['tool_namespace']) && !isset($config['namespace'])) {
			$config['namespace'] = $component['tool_namespace'];
		}

		if (isset($component['enabled']) && !isset($config['enabled'])) {
			$config['enabled'] = $component['enabled'];
		}

		$resource = [
			'id' => $wrapperId,
			'type' => self::TOOL_WRAPPER_TYPE,
			'docks' => [
				'tool' => [
					$baseResourceId
				]
			]
		];

		if ($config !== []) {
			$resource['config'] = $config;
		}

		$flow['resources'][] = $resource;
		$this->addNodeDockResource($flow, $assistantIndex, 'tools', $wrapperId);
	}

	/**
	 * @param array<string,mixed> $flow
	 * @param array<string,mixed> $component
	 */
	private function addConfiguredMemory(array &$flow, int $assistantIndex, string $presetId, string $baseResourceId, array $component, int $index): void {
		$wrapperId = $this->buildUniqueResourceId($flow, 'configured_memory_' . $this->sanitizeId($presetId) . '_' . $index);
		$config = $this->normalizeConfig($component['memory_config'] ?? []);

		if (isset($component['order']) && !isset($config['priority'])) {
			$config['priority'] = $component['order'];
		}

		if (isset($component['enabled']) && !isset($config['enabled'])) {
			$config['enabled'] = $component['enabled'];
		}

		$resource = [
			'id' => $wrapperId,
			'type' => self::MEMORY_WRAPPER_TYPE,
			'docks' => [
				'memory' => [
					$baseResourceId
				]
			]
		];

		if ($config !== []) {
			$resource['config'] = $config;
		}

		$flow['resources'][] = $resource;
		$this->addNodeDockResource($flow, $assistantIndex, 'memory', $wrapperId);
	}

	/**
	 * @param array<string,mixed> $flow
	 */
	private function addNodeDockResource(array &$flow, int $nodeIndex, string $dockName, string $resourceId): void {
		if (!isset($flow['nodes'][$nodeIndex]['docks']) || !is_array($flow['nodes'][$nodeIndex]['docks'])) {
			$flow['nodes'][$nodeIndex]['docks'] = [];
		}

		if (!isset($flow['nodes'][$nodeIndex]['docks'][$dockName]) || !is_array($flow['nodes'][$nodeIndex]['docks'][$dockName])) {
			$flow['nodes'][$nodeIndex]['docks'][$dockName] = [];
		}

		if (!in_array($resourceId, $flow['nodes'][$nodeIndex]['docks'][$dockName], true)) {
			$flow['nodes'][$nodeIndex]['docks'][$dockName][] = $resourceId;
		}
	}

	/**
	 * @param array<string,mixed> $flow
	 */
	private function findAssistantNodeIndex(array $flow, string $assistantNodeId): ?int {
		foreach ($flow['nodes'] as $index => $node) {
			if (!is_array($node)) {
				continue;
			}

			if ((string)($node['id'] ?? '') === $assistantNodeId) {
				return (int)$index;
			}
		}

		foreach ($flow['nodes'] as $index => $node) {
			if (!is_array($node)) {
				continue;
			}

			if ((string)($node['type'] ?? '') === self::ASSISTANT_NODE_TYPE) {
				return (int)$index;
			}
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $flow
	 */
	private function resourceExists(array $flow, string $resourceId): bool {
		foreach ($flow['resources'] as $resource) {
			if (is_array($resource) && (string)($resource['id'] ?? '') === $resourceId) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string,mixed> $flow
	 */
	private function buildUniqueResourceId(array $flow, string $baseId): string {
		$baseId = $this->sanitizeId($baseId);
		$resourceId = $baseId;
		$counter = 2;

		while ($this->resourceExists($flow, $resourceId)) {
			$resourceId = $baseId . '_' . $counter;
			$counter++;
		}

		return $resourceId;
	}

	private function buildResourceId(string $prefix, string $id): string {
		return $this->sanitizeId($prefix . $id);
	}

	private function sanitizeId(string $id): string {
		$id = trim($id);
		$id = (string)preg_replace('/[^A-Za-z0-9_]+/', '_', $id);
		$id = trim($id, '_');

		if ($id === '') {
			return 'component';
		}

		if (preg_match('/^[0-9]/', $id)) {
			$id = 'component_' . $id;
		}

		return strtolower($id);
	}

	/**
	 * @param array<string,mixed> $flow
	 * @return array<string,mixed>
	 */
	private function normalizeFlow(array $flow): array {
		if (!isset($flow['nodes']) || !is_array($flow['nodes'])) {
			$flow['nodes'] = [];
		}

		if (!isset($flow['resources']) || !is_array($flow['resources'])) {
			$flow['resources'] = [];
		}

		if (!isset($flow['connections']) || !is_array($flow['connections'])) {
			$flow['connections'] = [];
		}

		return $flow;
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<string,mixed>
	 */
	private function normalizeConfig(mixed $config): array {
		return is_array($config) ? $config : [];
	}

	/**
	 * @return array<int,string>
	 */
	private function normalizeStringList(mixed $value): array {
		if ($value === null || $value === '') {
			return [];
		}

		if (is_string($value)) {
			$value = explode(',', $value);
		}

		if (!is_array($value)) {
			return [];
		}

		$result = [];

		foreach ($value as $item) {
			$item = strtolower(trim((string)$item));

			if ($item === '') {
				continue;
			}

			$result[] = $item;
		}

		return array_values(array_unique($result));
	}

	/**
	 * @param array<string,mixed> $data
	 */
	private function isEnabled(array $data): bool {
		if (!array_key_exists('enabled', $data)) {
			return true;
		}

		$value = $data['enabled'];

		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value)) {
			return $value !== 0;
		}

		$value = strtolower(trim((string)$value));

		return !in_array($value, ['0', 'false', 'no', 'off'], true);
	}
}
