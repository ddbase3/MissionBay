<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Api\IAgentConversationMemory;
use AssistantFoundation\Api\IAgentMemory;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Api\IAgentTool;
use MissionBay\Dto\AgentComponentPresetMaterialization;
use MissionBay\Resource\ConfiguredAgentMemoryResource;
use MissionBay\Resource\ConfiguredAgentToolResource;

/**
 * Canonical materializer for stored agent component presets.
 *
 * Every top-level materialization uses fresh resource instances, recursively
 * resolves preset docks, and creates the configured capability wrappers used by
 * the actual agent runtime.
 */
final class AgentComponentPresetMaterializer implements IAgentComponentPresetMaterializer {

	private const LOG_SCOPE = 'missionbay_component_preset';

	/** @var array<string,IAgentResource> */
	private array $resources = [];

	/** @var array<string,bool> */
	private array $resolving = [];

	/** @var array<int,string> */
	private array $warnings = [];

	/** @var array<string,array<int,string>> */
	private array $resolvedDocks = [];

	public function __construct(
		private readonly IAgentComponentPresetRepository $presetRepository,
		private readonly IAgentResourceFactory $resourceFactory,
		private readonly IAgentContextFactory $contextFactory,
		private readonly ILogger $logger
	) {}

	public static function getName(): string {
		return 'agentcomponentpresetmaterializer';
	}

	public function createContext(array $vars = []): IAgentContext {
		$vars = array_replace([
			'source' => 'agent-component-preset-materializer'
		], $vars);

		return $this->contextFactory->createContext('agentcontext', null, $vars);
	}

	public function materialize(string $presetId, IAgentContext $context): AgentComponentPresetMaterialization {
		$this->resources = [];
		$this->resolving = [];
		$this->warnings = [];
		$this->resolvedDocks = [];

		$presetId = trim($presetId);
		$preset = $presetId !== '' ? $this->presetRepository->getPreset($presetId, []) : [];

		if($presetId === '') {
			$this->warn('Component preset id is missing.');
		}
		elseif($preset === []) {
			$this->warn('Component preset not found.', ['preset' => $presetId]);
		}
		elseif(!$this->isEnabled($preset)) {
			$this->warn('Component preset is disabled.', ['preset' => $presetId]);
		}

		$resource = $preset !== [] && $this->isEnabled($preset)
			? $this->materializePresetResource($presetId, $context)
			: null;
		$declaredCapabilities = $this->normalizeCapabilities($preset['capabilities'] ?? []);
		$capabilities = $this->resolveCapabilities($resource, $declaredCapabilities);
		$tool = $this->createToolCapability($presetId, $resource, $capabilities, $context);
		$memory = $this->createMemoryCapability($presetId, $resource, $capabilities, $context);
		$contextContributor = $resource instanceof IAgentContextContributor
			&& in_array('context', $capabilities, true)
			? $resource
			: null;

		$this->validateDeclaredCapabilities($presetId, $resource, $declaredCapabilities);

		return new AgentComponentPresetMaterialization(
			$presetId,
			$preset,
			$resource,
			$tool,
			$memory,
			$contextContributor,
			$capabilities,
			$this->warnings,
			$this->resolvedDocks
		);
	}

	private function materializePresetResource(string $presetId, IAgentContext $context): ?IAgentResource {
		$resourceId = $this->buildResourceId($presetId);

		if(isset($this->resources[$resourceId])) {
			return $this->resources[$resourceId];
		}

		if(!empty($this->resolving[$presetId])) {
			$this->warn('Circular component preset dock reference detected.', ['preset' => $presetId]);
			return null;
		}

		$preset = $this->presetRepository->getPreset($presetId, []);

		if($preset === []) {
			$this->warn('Docked component preset not found.', ['preset' => $presetId]);
			return null;
		}

		if(!$this->isEnabled($preset)) {
			$this->warn('Docked component preset is disabled.', ['preset' => $presetId]);
			return null;
		}

		$type = trim((string)($preset['type'] ?? ''));

		if($type === '') {
			$this->warn('Component preset has no resource type.', ['preset' => $presetId]);
			return null;
		}

		$this->resolving[$presetId] = true;
		$resource = $this->resourceFactory->createResource($type);

		if(!$resource instanceof IAgentResource) {
			unset($this->resolving[$presetId]);
			$this->warn('Component preset resource type could not be instantiated.', [
				'preset' => $presetId,
				'type' => $type
			]);
			return null;
		}

		$resource->setId($resourceId);
		$resource->setConfig(is_array($preset['config'] ?? null) ? $preset['config'] : []);
		$docks = $this->materializeDocks($preset, $context);
		$resource->init($docks, $context);

		$this->resources[$resourceId] = $resource;
		unset($this->resolving[$presetId]);

		return $resource;
	}

	/**
	 * @param array<string,mixed> $preset
	 * @return array<string,array<int,IAgentResource>>
	 */
	private function materializeDocks(array $preset, IAgentContext $context): array {
		$result = [];

		if(!is_array($preset['docks'] ?? null)) {
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
					$target = $this->materializePresetResource($targetId, $context);
				}
				else {
					$knownResourceId = $this->buildResourceId($targetId);
					$target = $this->resources[$targetId] ?? $this->resources[$knownResourceId] ?? null;
				}

				if(!$target instanceof IAgentResource) {
					$this->warn('Dock target is not a known preset or materialized resource.', [
						'target' => $targetId,
						'dock' => $dockName
					]);
					continue;
				}

				$result[$dockName][] = $target;
				$this->resolvedDocks[$dockName][] = $target->getId();
			}
		}

		foreach($this->resolvedDocks as $dockName => $ids) {
			$this->resolvedDocks[$dockName] = array_values(array_unique($ids));
		}

		return $result;
	}

	/** @param array<int,string> $capabilities */
	private function createToolCapability(
		string $presetId,
		?IAgentResource $resource,
		array $capabilities,
		IAgentContext $context
	): ?IAgentTool {
		if(!$resource instanceof IAgentTool || !in_array('tool', $capabilities, true)) {
			return null;
		}

		if($resource instanceof ConfiguredAgentToolResource) {
			return $resource;
		}

		$wrapper = $this->resourceFactory->createResource(ConfiguredAgentToolResource::getName());

		if(!$wrapper instanceof ConfiguredAgentToolResource) {
			$this->warn('Configured tool wrapper could not be instantiated.', ['preset' => $presetId]);
			return null;
		}

		$wrapper->setId($this->buildWrapperId('configured_tool_', $presetId));
		$wrapper->setConfig([]);
		$wrapper->init(['tool' => [$resource]], $context);

		return $wrapper;
	}

	/** @param array<int,string> $capabilities */
	private function createMemoryCapability(
		string $presetId,
		?IAgentResource $resource,
		array $capabilities,
		IAgentContext $context
	): ?IAgentConversationMemory {
		if(!$resource instanceof IAgentMemory || !in_array('memory', $capabilities, true)) {
			return null;
		}

		if($resource instanceof ConfiguredAgentMemoryResource) {
			return $resource;
		}

		$wrapper = $this->resourceFactory->createResource(ConfiguredAgentMemoryResource::getName());

		if(!$wrapper instanceof ConfiguredAgentMemoryResource) {
			$this->warn('Configured memory wrapper could not be instantiated.', ['preset' => $presetId]);
			return null;
		}

		$wrapper->setId($this->buildWrapperId('configured_memory_', $presetId));
		$wrapper->setConfig([]);
		$wrapper->init(['memory' => [$resource]], $context);

		return $wrapper;
	}

	/**
	 * @param array<int,string> $declaredCapabilities
	 * @return array<int,string>
	 */
	private function resolveCapabilities(?IAgentResource $resource, array $declaredCapabilities): array {
		$capabilities = $declaredCapabilities;

		if($resource instanceof IAgentTool) {
			$capabilities[] = 'tool';
		}

		if($resource instanceof IAgentMemory) {
			$capabilities[] = 'memory';
		}

		if($resource instanceof IAgentContextContributor) {
			$capabilities[] = 'context';
		}

		$capabilities = array_values(array_unique($capabilities));
		sort($capabilities);

		return $capabilities;
	}

	/** @param array<int,string> $declaredCapabilities */
	private function validateDeclaredCapabilities(
		string $presetId,
		?IAgentResource $resource,
		array $declaredCapabilities
	): void {
		$checks = [
			'tool' => $resource instanceof IAgentTool,
			'memory' => $resource instanceof IAgentMemory,
			'context' => $resource instanceof IAgentContextContributor
		];

		foreach($declaredCapabilities as $capability) {
			if(($checks[$capability] ?? false) === true) {
				continue;
			}

			$this->warn('Component preset declares a capability not implemented by its resource.', [
				'preset' => $presetId,
				'capability' => $capability,
				'class' => $resource !== null ? $resource::class : ''
			]);
		}
	}

	/** @return array<int,string> */
	private function normalizeCapabilities(mixed $value): array {
		if(is_string($value)) {
			$value = explode(',', $value);
		}

		if(!is_array($value)) {
			return [];
		}

		$result = [];

		foreach($value as $capability) {
			$capability = strtolower(trim((string)$capability));

			if(in_array($capability, ['tool', 'memory', 'context'], true)) {
				$result[] = $capability;
			}
		}

		return array_values(array_unique($result));
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

	private function buildResourceId(string $presetId): string {
		return $this->sanitizeId('preset_' . $presetId);
	}

	private function buildWrapperId(string $prefix, string $presetId): string {
		return $this->sanitizeId($prefix . $presetId);
	}

	private function sanitizeId(string $id): string {
		$id = (string)preg_replace('/[^A-Za-z0-9_]+/', '_', trim($id));
		$id = trim($id, '_');

		if($id === '') {
			return 'preset_component';
		}

		if(preg_match('/^[0-9]/', $id)) {
			$id = 'preset_' . $id;
		}

		return strtolower($id);
	}

	/** @param array<string,mixed> $context */
	private function warn(string $message, array $context = []): void {
		$detail = $context === [] ? '' : ' ' . (string)json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$this->warnings[] = $message . $detail;
		$context['scope'] = self::LOG_SCOPE;
		$this->logger->logLevel(ILogger::WARNING, $message, $context);
	}
}
