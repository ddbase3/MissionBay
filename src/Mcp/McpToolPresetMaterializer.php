<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

use Base3\Api\IClassMap;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentComponentPresetRepository;
use AssistantFoundation\Api\IAgentContext;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentTool;

/**
 * McpToolPresetMaterializer
 *
 * Materializes tool profiles by instantiating the referenced component presets
 * and their dock dependencies. This intentionally does not use MissionBay flow
 * classes or agent nodes.
 */
class McpToolPresetMaterializer {

	private const LOG_SCOPE = 'missionbay_mcp';

	/**
	 * @var array<string,IAgentResource>
	 */
	private array $resources = [];

	/**
	 * @var array<string,bool>
	 */
	private array $resolving = [];

	/**
	 * @var array<int,string>
	 */
	private array $warnings = [];

	public function __construct(
		private readonly IAgentComponentPresetRepository $presetRepository,
		private readonly IClassMap $classMap,
		private readonly IAgentContextFactory $contextFactory,
		private readonly ILogger $logger
	) {}

	public static function getName(): string {
		return 'mcptoolpresetmaterializer';
	}

	/**
	 * @param array<string,mixed> $profile
	 */
	public function createContext(array $profile): IAgentContext {
		return $this->contextFactory->createContext('agentcontext', null, [
			'mcp' => true,
			'mcp_profile_id' => (string)($profile['id'] ?? ''),
			'mcp_profile_label' => (string)($profile['label'] ?? '')
		]);
	}

	/**
	 * @param array<string,mixed> $profile
	 * @return IAgentTool[]
	 */
	public function materialize(array $profile, IAgentContext $context): array {
		$this->resources = [];
		$this->resolving = [];
		$this->warnings = [];

		$tools = [];
		$presetIds = $this->normalizeStringList($profile['tools'] ?? []);

		foreach($presetIds as $presetId) {
			$preset = $this->presetRepository->getPreset($presetId, []);

			if($preset === []) {
				$this->warn('MCP tool preset not found.', ['preset' => $presetId]);
				continue;
			}

			if(!$this->isEnabled($preset)) {
				$this->warn('MCP tool preset is disabled.', ['preset' => $presetId]);
				continue;
			}

			if(!$this->presetMayBeTool($preset)) {
				$this->warn('MCP profile references a preset without tool capability.', ['preset' => $presetId]);
				continue;
			}

			$resource = $this->materializePreset($presetId, $context);

			if(!$resource instanceof IAgentTool) {
				$this->warn('MCP preset did not materialize to an agent tool.', ['preset' => $presetId]);
				continue;
			}

			$tools[] = $resource;
		}

		return $tools;
	}

	/**
	 * @return array<int,string>
	 */
	public function getWarnings(): array {
		return $this->warnings;
	}

	private function materializePreset(string $presetId, IAgentContext $context): ?IAgentResource {
		$resourceId = $this->buildResourceId($presetId);

		if(isset($this->resources[$resourceId])) {
			return $this->resources[$resourceId];
		}

		if(!empty($this->resolving[$presetId])) {
			$this->warn('Circular MCP preset dock reference detected.', ['preset' => $presetId]);
			return null;
		}

		$preset = $this->presetRepository->getPreset($presetId, []);

		if($preset === []) {
			$this->warn('MCP dock preset not found.', ['preset' => $presetId]);
			return null;
		}

		if(!$this->isEnabled($preset)) {
			$this->warn('MCP dock preset is disabled.', ['preset' => $presetId]);
			return null;
		}

		$type = trim((string)($preset['type'] ?? ''));

		if($type === '') {
			$this->warn('MCP preset has no resource type.', ['preset' => $presetId]);
			return null;
		}

		$this->resolving[$presetId] = true;

		$resource = $this->classMap->getInstanceByInterfaceName(IAgentResource::class, $type);

		if(!$resource instanceof IAgentResource) {
			unset($this->resolving[$presetId]);
			$this->warn('MCP preset resource type could not be instantiated.', [
				'preset' => $presetId,
				'type' => $type
			]);
			return null;
		}

		$resource->setId($resourceId);

		if(isset($preset['config']) && is_array($preset['config'])) {
			$resource->setConfig($preset['config']);
		}

		$dockedResources = $this->materializeDocks($preset, $context);
		$resource->init($dockedResources, $context);

		$this->resources[$resourceId] = $resource;
		unset($this->resolving[$presetId]);

		return $resource;
	}

	/**
	 * @param array<string,mixed> $preset
	 * @return array<string,IAgentResource[]>
	 */
	private function materializeDocks(array $preset, IAgentContext $context): array {
		$result = [];

		if(!isset($preset['docks']) || !is_array($preset['docks'])) {
			return $result;
		}

		foreach($preset['docks'] as $dockName => $targets) {
			$dockName = trim((string)$dockName);

			if($dockName === '') {
				continue;
			}

			foreach((array)$targets as $targetId) {
				$targetId = trim((string)$targetId);

				if($targetId === '') {
					continue;
				}

				$target = null;

				if($this->presetRepository->hasPreset($targetId)) {
					$target = $this->materializePreset($targetId, $context);
				}
				elseif(isset($this->resources[$targetId])) {
					$target = $this->resources[$targetId];
				}
				else {
					$this->warn('MCP dock target is not a known preset or materialized resource.', [
						'target' => $targetId,
						'dock' => $dockName
					]);
				}

				if($target instanceof IAgentResource) {
					$result[$dockName][] = $target;
				}
			}
		}

		return $result;
	}

	/**
	 * @param array<string,mixed> $preset
	 */
	private function presetMayBeTool(array $preset): bool {
		$capabilities = $this->normalizeStringList($preset['capabilities'] ?? []);

		return $capabilities === [] || in_array('tool', $capabilities, true);
	}

	private function buildResourceId(string $presetId): string {
		$id = 'preset_' . trim($presetId);
		$id = (string)preg_replace('/[^A-Za-z0-9_]+/', '_', $id);
		$id = trim($id, '_');

		if($id === '') {
			return 'preset_component';
		}

		if(preg_match('/^[0-9]/', $id)) {
			$id = 'preset_' . $id;
		}

		return strtolower($id);
	}

	/**
	 * @return array<int,string>
	 */
	private function normalizeStringList(mixed $value): array {
		if($value === null || $value === '') {
			return [];
		}

		if(is_string($value)) {
			$value = explode(',', $value);
		}

		if(!is_array($value)) {
			return [];
		}

		$result = [];

		foreach($value as $item) {
			$item = strtolower(trim((string)$item));

			if($item === '') {
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

		$value = strtolower(trim((string)$value));

		return !in_array($value, ['0', 'false', 'no', 'off'], true);
	}

	/**
	 * @param array<string,mixed> $context
	 */
	private function warn(string $message, array $context = []): void {
		$this->warnings[] = $message;
		$context['scope'] = self::LOG_SCOPE;
		$this->logger->logLevel(ILogger::WARNING, $message, $context);
	}
}
