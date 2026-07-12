<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Composition;

use AssistantFoundation\Api\IAgentContextContributor;
use AssistantFoundation\Api\IAgentExecutionService;
use AssistantFoundation\Api\IAgentMemory;
use AssistantFoundation\Dto\AgentCapabilitySourceConfig;
use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IAgentComponentPresetRepository;
use MissionBay\Api\IAgentContextFactory;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Api\IAgentMemoryRoleResolver;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentTool;
use MissionBay\Capability\AgentCapabilityCatalogBuilder;
use MissionBay\Capability\AgentCapabilityDiscoveryService;
use MissionBay\Orchestrator\AgentStagePipelineResolver;
use MissionBay\Orchestrator\Profile\AgentOrchestratorProfileRepository;
use MissionBay\Profile\AgentContextProfileResolver;
use MissionBay\Profile\AgentMemoryProfileResolver;
use MissionBay\Profile\AgentToolProfileResolver;

/**
 * Builds a read-only diagnostic view of the effective configured composition
 * of one agent. It follows the same profile, flow, resource, capability and
 * stage resolution paths used by the runtime, but never executes a node or a
 * tool call.
 */
final class AgentCompositionInspector {

	private const TOOL_PROFILE_SETTINGS_GROUP = 'tool-profile';
	private const DEFAULT_ASSISTANT_NODE_ID = 'assistant';

	public function __construct(
		private readonly ISettingsStore $settingsStore,
		private readonly IAgentExecutionService $executionService,
		private readonly IAgentContextFactory $contextFactory,
		private readonly IAgentFlowFactory $flowFactory,
		private readonly AgentOrchestratorProfileRepository $orchestratorProfiles,
		private readonly AgentToolProfileResolver $toolProfileResolver,
		private readonly AgentMemoryProfileResolver $memoryProfileResolver,
		private readonly AgentContextProfileResolver $contextProfileResolver,
		private readonly IAgentComponentPresetRepository $presetRepository,
		private readonly AgentCapabilityDiscoveryService $capabilityDiscoveryService,
		private readonly AgentCapabilityCatalogBuilder $catalogBuilder,
		private readonly AgentStagePipelineResolver $stagePipelineResolver,
		private readonly IAgentMemoryRoleResolver $memoryRoleResolver
	) {}

	/**
	 * @param array<string,mixed> $settings
	 * @return array<string,mixed>
	 */
	public function inspect(string $agentId, array $settings): array {
		$warnings = [];
		$errors = [];
		$profileId = $this->normalizeId((string)($settings['orchestrator_profile'] ?? AgentOrchestratorProfileRepository::DEFAULT_PROFILE_ID));
		$profile = null;

		try {
			$profile = $this->orchestratorProfiles->getProfile($profileId);
		}
		catch (\Throwable $e) {
			$errors[] = $e->getMessage();
		}

		$toolProfileIds = $this->normalizeIds($settings['tool_profiles'] ?? []);
		$toolProfiles = $this->describeToolProfiles($toolProfileIds, $warnings, $errors);
		$memoryProfileId = $this->normalizeId((string)($settings['memory_profile'] ?? ''));
		$contextProfileId = $this->normalizeId((string)($settings['context_profile'] ?? ''));
		if ($contextProfileId === '' && $memoryProfileId !== '' && $this->contextProfileResolver->hasProfile($memoryProfileId)) {
			$contextProfileId = $memoryProfileId;
			$warnings[] = 'Context profile is derived from the legacy combined profile "' . $memoryProfileId . '".';
		}
		$memoryProfile = $this->describeMemoryProfile($memoryProfileId, $warnings, $errors);
		$contextProfile = $this->describeContextProfile($contextProfileId, $warnings, $errors);
		$componentSources = $this->buildComponentSources($toolProfiles, $memoryProfile, $contextProfile);
		$components = $this->describeConfiguredComponents($settings, $memoryProfileId, $contextProfileId, $componentSources, $warnings, $errors);

		$effectiveFlow = [];
		try {
			$effectiveFlow = $this->executionService->buildEffectiveFlow($settings);
			$warnings = array_merge($warnings, $this->executionService->getWarnings());
		}
		catch (\Throwable $e) {
			$errors[] = 'Effective flow could not be built: ' . $e->getMessage();
		}

		$assistantNode = $this->findAssistantNode($effectiveFlow, (string)($settings['agent_components_assistant_node'] ?? self::DEFAULT_ASSISTANT_NODE_ID));
		$assistantNodeId = (string)($assistantNode['id'] ?? self::DEFAULT_ASSISTANT_NODE_ID);
		$nodeInputs = is_array($assistantNode['inputs'] ?? null) ? $assistantNode['inputs'] : [];
		$coreStageIds = $this->normalizeIds($nodeInputs['stages'] ?? ($profile?->getStageIds() ?? []), false);
		$sourceConfig = AgentCapabilitySourceConfig::fromArray(
			is_array($nodeInputs['capabilitysources'] ?? null)
				? $nodeInputs['capabilitysources']
				: (is_array($settings['capability_sources'] ?? null) ? $settings['capability_sources'] : [])
		);

		$runtime = [
			'tools' => [],
			'memories' => [],
			'discovery' => [
				'sources' => $sourceConfig->toArray(),
				'resolved' => [],
				'warnings' => [],
				'errors' => []
			],
			'module_stage_mounts' => [],
			'final_stages' => $coreStageIds,
			'resource_count' => 0
		];

		if ($effectiveFlow !== [] && $assistantNode !== []) {
			try {
				$runtime = $this->inspectRuntimeComposition(
					$effectiveFlow,
					$assistantNodeId,
					$coreStageIds,
					$sourceConfig,
					$warnings,
					$errors
				);
			}
			catch (\Throwable $e) {
				$errors[] = 'Runtime composition could not be inspected: ' . $e->getMessage();
			}
		}

		$components = $this->attachRuntimeDetails($components, $runtime['tools'], $runtime['memories']);
		$warnings = array_values(array_unique(array_filter(array_map('trim', $warnings))));
		$errors = array_values(array_unique(array_filter(array_map('trim', $errors))));
		$status = $errors !== [] ? 'error' : ($warnings !== [] ? 'warning' : 'valid');

		return [
			'agent_id' => $agentId,
			'label' => trim((string)($settings['label'] ?? '')) ?: $agentId,
			'enabled' => $this->toBool($settings['enabled'] ?? true),
			'llm' => trim((string)($settings['llm'] ?? '')),
			'status' => $status,
			'orchestrator' => $profile !== null ? $profile->toArray() : [
				'id' => $profileId,
				'label' => $profileId,
				'mode' => '',
				'max_tool_loops' => (int)($nodeInputs['maxtoolloops'] ?? 0),
				'stage_ids' => $coreStageIds
			],
			'tool_profiles' => $toolProfiles,
			'memory_profile' => $memoryProfile,
			'context_profile' => $contextProfile,
			'components' => $components,
			'capability_sources' => $sourceConfig->toArray(),
			'capability_selection' => is_array($nodeInputs['capabilityselection'] ?? null)
				? $nodeInputs['capabilityselection']
				: (is_array($settings['capability_selection'] ?? null) ? $settings['capability_selection'] : []),
			'core_stage_ids' => $coreStageIds,
			'final_stage_ids' => $runtime['final_stages'],
			'module_stage_mounts' => $runtime['module_stage_mounts'],
			'tools' => $runtime['tools'],
			'memories' => $runtime['memories'],
			'discovery' => $runtime['discovery'],
			'resource_count' => $runtime['resource_count'],
			'warnings' => $warnings,
			'errors' => $errors,
			'effective_flow' => $this->redact($effectiveFlow)
		];
	}

	/**
	 * @param array<string,mixed> $effectiveFlow
	 * @param array<int,string> $coreStageIds
	 * @param array<int,string> $warnings
	 * @param array<int,string> $errors
	 * @return array<string,mixed>
	 */
	private function inspectRuntimeComposition(
		array $effectiveFlow,
		string $assistantNodeId,
		array $coreStageIds,
		AgentCapabilitySourceConfig $sourceConfig,
		array &$warnings,
		array &$errors
	): array {
		$context = $this->contextFactory->createContext();
		$flow = $this->flowFactory->createFromArray('strictflow', $effectiveFlow, $context);
		$resources = $flow->getResources();
		$docks = $flow->getDockConnections($assistantNodeId);
		$baseTools = $this->getDockedResources($resources, $docks['tools'] ?? [], IAgentTool::class);
		$memoryResources = $this->getDockedResources($resources, $docks['memory'] ?? [], IAgentMemory::class);
		$contextContributors = $this->getDockedResources(
			$resources,
			$docks['contextcontributors'] ?? [],
			IAgentContextContributor::class
		);
		$discovery = $this->capabilityDiscoveryService->discover($baseTools, $sourceConfig, $context);
		$warnings = array_merge($warnings, $discovery->getWarnings());
		$errors = array_merge($errors, $discovery->getErrors());
		$toolDefinitions = [];

		foreach ($discovery->getTools() as $tool) {
			try {
				foreach ($tool->getToolDefinitions() as $definition) {
					if (is_array($definition)) {
						$toolDefinitions[] = $definition;
					}
				}
			}
			catch (\Throwable $e) {
				$warnings[] = 'Tool definitions could not be read from ' . $tool::getName() . ': ' . $e->getMessage();
			}
		}

		$toolRows = [];
		try {
			$catalog = $this->catalogBuilder->build($discovery->getTools(), $toolDefinitions);
			foreach ($catalog->all() as $capability) {
				$row = $capability->toArray();
				$row['mutation'] = $this->isMutation($row['metadata'] ?? []);
				$row['requires_approval'] = $this->requiresApproval($row['metadata'] ?? []);
				$toolRows[] = $row;
			}
		}
		catch (\Throwable $e) {
			$errors[] = 'Capability catalog is invalid: ' . $e->getMessage();
			$toolRows = $this->fallbackToolRows($discovery->getTools(), $toolDefinitions);
		}

		usort($toolRows, static fn(array $left, array $right): int => strcasecmp((string)$left['name'], (string)$right['name']));
		$memoryRows = $this->describeMemories(array_merge($memoryResources, $contextContributors), $warnings);
		$mountRows = [];
		foreach ($discovery->getStageMounts() as $mount) {
			$mountRows[] = [
				'slot' => $mount->getSlot(),
				'stage_id' => $mount->getStage()->id(),
				'stage_name' => $mount->getStage()->name(),
				'order' => $mount->getOrder()
			];
		}

		$finalStageIds = $coreStageIds;
		try {
			$finalStageIds = array_map(
				static fn($stage): string => $stage->id(),
				$this->stagePipelineResolver->resolve($coreStageIds, $discovery->getStageMounts())
			);
		}
		catch (\Throwable $e) {
			$errors[] = 'Effective stage pipeline is invalid: ' . $e->getMessage();
		}

		return [
			'tools' => $toolRows,
			'memories' => $memoryRows,
			'discovery' => $discovery->toArray(),
			'module_stage_mounts' => $mountRows,
			'final_stages' => $finalStageIds,
			'resource_count' => count($resources)
		];
	}

	/**
	 * @param array<string,IAgentResource> $resources
	 * @param array<int,string> $resourceIds
	 * @return array<int,object>
	 */
	private function getDockedResources(array $resources, array $resourceIds, string $interface): array {
		$result = [];
		foreach ($resourceIds as $resourceId) {
			$resourceId = (string)$resourceId;
			if (isset($resources[$resourceId]) && is_a($resources[$resourceId], $interface)) {
				$result[] = $resources[$resourceId];
			}
		}
		return $result;
	}

	/**
	 * @param array<int,object> $memoryResources
	 * @return array<int,array<string,mixed>>
	 */
	private function describeMemories(array $memoryResources, array &$warnings): array {
		$rows = [];
		$seen = [];

		foreach ($memoryResources as $memory) {
			if (!is_object($memory)) {
				continue;
			}

			$objectId = spl_object_id($memory);
			if (isset($seen[$objectId])) {
				continue;
			}
			$seen[$objectId] = true;

			$resourceId = $memory instanceof IAgentResource ? $memory->getId() : '';
			$roles = [];
			$legacy = false;
			$conversationMemory = false;
			$contextContributor = $memory instanceof IAgentContextContributor;

			if ($memory instanceof IAgentMemory) {
				$roles = $this->memoryRoleResolver->getRoles($memory);
				$legacy = $this->memoryRoleResolver->isLegacyMemory($memory);
				$conversationMemory = $this->memoryRoleResolver->isConversationMemory($memory);
				$contextContributor = $this->memoryRoleResolver->isContextContributor($memory);
			}
			elseif ($contextContributor) {
				$roles[] = 'context-contributor';
			}
			else {
				continue;
			}

			if ($legacy) {
				$warnings[] = 'Legacy memory semantics are active for ' . ($resourceId !== '' ? $resourceId : $memory::class) . '. Declare IAgentConversationMemory or IAgentContextContributor to make read/write behavior explicit.';
			}

			$priority = method_exists($memory, 'getPriority') ? (int)$memory->getPriority() : 100;
			$configuredRole = method_exists($memory, 'getConfiguredRole') ? (string)$memory->getConfiguredRole() : 'auto';
			$readEnabled = method_exists($memory, 'isReadEnabled') ? (bool)$memory->isReadEnabled() : true;
			$writeEnabled = method_exists($memory, 'isWriteEnabled') ? (bool)$memory->isWriteEnabled() : true;
			$diagnosticId = $resourceId !== '' ? $resourceId : $memory::class;
			if (in_array($configuredRole, ['conversation-memory', 'both'], true) && !$conversationMemory) {
				$warnings[] = 'Configured conversation-memory role is not supported by ' . $diagnosticId . '.';
			}
			if (in_array($configuredRole, ['context-contributor', 'both'], true) && !$contextContributor) {
				$warnings[] = 'Configured context-contributor role is not supported by ' . $diagnosticId . '.';
			}
			$name = method_exists($memory, 'getName') ? (string)$memory::getName() : $memory::class;
			$rows[] = [
				'resource_id' => $resourceId,
				'name' => $name,
				'class' => $memory::class,
				'priority' => $priority,
				'preset_id' => $this->presetIdFromWrapper($resourceId, 'configured_memory_'),
				'roles' => $roles,
				'role' => implode(' + ', $roles),
				'conversation_memory' => $conversationMemory,
				'context_contributor' => $contextContributor,
				'legacy' => $legacy,
				'configured_role' => $configuredRole,
				'read_enabled' => $readEnabled,
				'write_enabled' => $writeEnabled
			];
		}

		usort($rows, static function(array $left, array $right): int {
			$result = ((int)$left['priority']) <=> ((int)$right['priority']);
			return $result !== 0 ? $result : strcmp((string)$left['resource_id'], (string)$right['resource_id']);
		});

		return $rows;
	}

	/**
	 * @param array<int,IAgentTool> $tools
	 * @param array<int,array<string,mixed>> $definitions
	 * @return array<int,array<string,mixed>>
	 */
	private function fallbackToolRows(array $tools, array $definitions): array {
		$sources = [];
		foreach ($tools as $tool) {
			try {
				foreach ($tool->getToolDefinitions() as $definition) {
					$name = trim((string)($definition['function']['name'] ?? ''));
					if ($name === '') {
						continue;
					}
					$sources[$name] = [
						'id' => $tool instanceof IAgentResource ? $tool->getId() : '',
						'name' => $tool::getName()
					];
				}
			}
			catch (\Throwable) {
			}
		}

		$rows = [];
		foreach ($definitions as $definition) {
			$name = trim((string)($definition['function']['name'] ?? ''));
			if ($name === '') {
				continue;
			}
			$metadata = $this->collectToolMetadata($definition);
			$rows[] = [
				'name' => $name,
				'title' => trim((string)($definition['label'] ?? $definition['function']['title'] ?? $name)),
				'description' => trim((string)($definition['function']['description'] ?? $definition['description'] ?? '')),
				'category' => trim((string)($definition['category'] ?? '')),
				'tags' => is_array($definition['tags'] ?? null) ? array_values($definition['tags']) : [],
				'priority' => (int)($definition['priority'] ?? 0),
				'source_id' => (string)($sources[$name]['id'] ?? ''),
				'source_name' => (string)($sources[$name]['name'] ?? ''),
				'always_available' => $this->toBool($definition['alwaysAvailable'] ?? $definition['always_available'] ?? false),
				'metadata' => $metadata,
				'mutation' => $this->isMutation($metadata),
				'requires_approval' => $this->requiresApproval($metadata)
			];
		}
		return $rows;
	}

	/** @param array<string,mixed> $definition @return array<string,mixed> */
	private function collectToolMetadata(array $definition): array {
		$result = [];
		foreach (['mutation', 'requiresApproval', 'requires_approval', 'destructiveHint', 'destructive_hint', 'sideEffectHint', 'side_effect_hint', 'readOnlyHint', 'read_only_hint'] as $key) {
			if (array_key_exists($key, $definition)) {
				$result[$key] = $definition[$key];
			}
		}
		return $result;
	}

	/** @param array<string,mixed> $metadata */
	private function isMutation(array $metadata): bool {
		if ($this->toBool($metadata['mutation'] ?? false)) {
			return true;
		}
		if ($this->toBool($metadata['destructiveHint'] ?? $metadata['destructive_hint'] ?? false)) {
			return true;
		}
		if ($this->toBool($metadata['sideEffectHint'] ?? $metadata['side_effect_hint'] ?? false)) {
			return true;
		}
		if (array_key_exists('readOnlyHint', $metadata) || array_key_exists('read_only_hint', $metadata)) {
			return !$this->toBool($metadata['readOnlyHint'] ?? $metadata['read_only_hint']);
		}
		return false;
	}

	/** @param array<string,mixed> $metadata */
	private function requiresApproval(array $metadata): bool {
		return $this->toBool($metadata['requiresApproval'] ?? $metadata['requires_approval'] ?? false);
	}

	/**
	 * @param array<int,string> $warnings
	 * @param array<int,string> $errors
	 * @return array<string,mixed>|null
	 */
	private function describeMemoryProfile(string $profileId, array &$warnings, array &$errors): ?array {
		if ($profileId === '') {
			return [
				'id' => '',
				'label' => 'No memory profile',
				'description' => 'No conversation-memory profile is configured.',
				'enabled' => true,
				'presets' => [],
				'status' => 'none'
			];
		}

		try {
			$profile = $this->memoryProfileResolver->getProfile($profileId);
			$profile['status'] = $this->toBool($profile['enabled'] ?? true) ? 'active' : 'inactive';
			if (($profile['status'] ?? '') !== 'active') {
				$errors[] = 'Memory profile is disabled: ' . $profileId;
			}
			return $profile;
		}
		catch (\Throwable $e) {
			$errors[] = $e->getMessage();
			return [
				'id' => $profileId,
				'label' => $profileId,
				'description' => '',
				'enabled' => false,
				'presets' => [],
				'status' => 'missing'
			];
		}
	}

	private function describeContextProfile(string $profileId, array &$warnings, array &$errors): ?array {
		if ($profileId === '') {
			return [
				'id' => '',
				'label' => 'No context profile',
				'description' => 'No context-contributor profile is configured.',
				'enabled' => true,
				'presets' => [],
				'status' => 'none'
			];
		}

		try {
			$profile = $this->contextProfileResolver->getProfile($profileId);
			$profile['status'] = $this->toBool($profile['enabled'] ?? true) ? 'active' : 'inactive';
			if (($profile['status'] ?? '') !== 'active') {
				$errors[] = 'Context profile is disabled: ' . $profileId;
			}
			return $profile;
		}
		catch (\Throwable $e) {
			$errors[] = $e->getMessage();
			return [
				'id' => $profileId,
				'label' => $profileId,
				'description' => '',
				'enabled' => false,
				'presets' => [],
				'status' => 'missing'
			];
		}
	}

	/**
	 * @param array<int,string> $profileIds
	 * @param array<int,string> $warnings
	 * @param array<int,string> $errors
	 * @return array<int,array<string,mixed>>
	 */
	private function describeToolProfiles(array $profileIds, array &$warnings, array &$errors): array {
		$rows = [];
		foreach ($profileIds as $profileId) {
			$settings = $this->settingsStore->get(self::TOOL_PROFILE_SETTINGS_GROUP, $profileId, []);
			if (!is_array($settings) || $settings === []) {
				$errors[] = 'Tool profile not found: ' . $profileId;
				$rows[] = [
					'id' => $profileId,
					'label' => $profileId,
					'enabled' => false,
					'internal_enabled' => false,
					'mcp_enabled' => false,
					'tools' => [],
					'status' => 'missing'
				];
				continue;
			}
			$enabled = $this->toBool($settings['enabled'] ?? true);
			$internal = array_key_exists('internal_enabled', $settings) ? $this->toBool($settings['internal_enabled']) : true;
			if (!$enabled || !$internal) {
				$errors[] = 'Tool profile is not active for internal agents: ' . $profileId;
			}
			$rows[] = [
				'id' => $profileId,
				'label' => trim((string)($settings['label'] ?? '')) ?: $profileId,
				'description' => trim((string)($settings['description'] ?? '')),
				'enabled' => $enabled,
				'internal_enabled' => $internal,
				'mcp_enabled' => $this->toBool($settings['mcp_enabled'] ?? false),
				'tools' => $this->normalizeIds($settings['tools'] ?? []),
				'status' => $enabled && $internal ? 'active' : 'inactive'
			];
		}
		return $rows;
	}

	/**
	 * @param array<int,array<string,mixed>> $toolProfiles
	 * @param array<string,mixed>|null $memoryProfile
	 * @return array<string,array<int,string>>
	 */
	private function buildComponentSources(array $toolProfiles, ?array $memoryProfile, ?array $contextProfile): array {
		$result = [];
		foreach ($toolProfiles as $profile) {
			foreach ((array)($profile['tools'] ?? []) as $presetId) {
				$result[(string)$presetId][] = 'tool:' . (string)($profile['id'] ?? '');
			}
		}
		foreach ((array)($memoryProfile['presets'] ?? []) as $presetId) {
			$result[(string)$presetId][] = 'memory:' . (string)($memoryProfile['id'] ?? '');
		}
		foreach ((array)($contextProfile['presets'] ?? []) as $presetId) {
			$result[(string)$presetId][] = 'context:' . (string)($contextProfile['id'] ?? '');
		}
		foreach ($result as &$profileIds) {
			$profileIds = array_values(array_unique(array_filter($profileIds)));
		}
		unset($profileIds);
		return $result;
	}

	/**
	 * @param array<string,mixed> $settings
	 * @param array<string,array<int,string>> $componentSources
	 * @param array<int,string> $warnings
	 * @param array<int,string> $errors
	 * @return array<int,array<string,mixed>>
	 */
	private function describeConfiguredComponents(array $settings, string $memoryProfileId, string $contextProfileId, array $componentSources, array &$warnings, array &$errors): array {
		$components = [];
		try {
			$components = $this->toolProfileResolver->resolveComponents(
				$this->normalizeIds($settings['tool_profiles'] ?? [])
			);
			if ($memoryProfileId !== '') {
				$components = array_merge($components, $this->memoryProfileResolver->resolveComponents($memoryProfileId));
			}
			if ($contextProfileId !== '') {
				$components = array_merge($components, $this->contextProfileResolver->resolveComponents($contextProfileId));
			}
		}
		catch (\Throwable $e) {
			$errors[] = $e->getMessage();
		}

		foreach ($this->normalizeLegacyComponents($settings['agent_components'] ?? []) as $legacyComponent) {
			$components[] = $legacyComponent;
			$presetId = (string)($legacyComponent['preset'] ?? '');
			if ($presetId !== '') {
				$componentSources[$presetId][] = 'expert/legacy';
			}
		}

		$merged = [];
		foreach ($components as $component) {
			$presetId = trim((string)($component['preset'] ?? ''));
			if ($presetId === '') {
				continue;
			}
			$attachAs = $this->normalizeIds($component['attach_as'] ?? []);
			if (!isset($merged[$presetId])) {
				$merged[$presetId] = $component;
				$merged[$presetId]['attach_as'] = $attachAs;
				continue;
			}
			$merged[$presetId]['attach_as'] = array_values(array_unique(array_merge(
				(array)($merged[$presetId]['attach_as'] ?? []),
				$attachAs
			)));
			if (isset($component['memory_config']) && is_array($component['memory_config'])) {
				$merged[$presetId]['memory_config'] = $component['memory_config'];
			}
			if (isset($component['memory_profile'])) {
				$merged[$presetId]['memory_profile'] = $component['memory_profile'];
			}
			if (isset($component['context_profile'])) {
				$merged[$presetId]['context_profile'] = $component['context_profile'];
			}
		}

		$rows = [];
		foreach ($merged as $presetId => $component) {
			$preset = $this->presetRepository->getPreset($presetId, []);
			if ($preset === []) {
				$errors[] = 'Component preset not found: ' . $presetId;
			}
			$rows[] = [
				'preset_id' => $presetId,
				'label' => trim((string)($preset['label'] ?? '')) ?: $presetId,
				'type' => trim((string)($preset['type'] ?? '')),
				'enabled' => $this->toBool($preset['enabled'] ?? true),
				'roles' => $this->normalizeIds($component['attach_as'] ?? ($preset['capabilities'] ?? [])),
				'sources' => array_values(array_unique($componentSources[$presetId] ?? ['expert/legacy'])),
				'tool_names' => [],
				'memory_resources' => [],
				'memory_roles' => [],
				'effective_roles' => [],
				'memory_profile' => (string)($component['memory_profile'] ?? ''),
				'context_profile' => (string)($component['context_profile'] ?? ''),
				'memory_config' => is_array($component['memory_config'] ?? null) ? $component['memory_config'] : [],
				'config' => $this->redact(is_array($preset['config'] ?? null) ? $preset['config'] : [])
			];
		}
		usort($rows, static fn(array $left, array $right): int => strcasecmp((string)$left['preset_id'], (string)$right['preset_id']));
		return $rows;
	}

	/** @return array<int,array<string,mixed>> */
	private function normalizeLegacyComponents(mixed $components): array {
		if (!is_array($components)) {
			return [];
		}
		$result = [];
		foreach ($components as $component) {
			if (!is_array($component) || !$this->toBool($component['enabled'] ?? true)) {
				continue;
			}
			$presetId = $this->normalizeId((string)($component['preset'] ?? ''));
			if ($presetId === '') {
				continue;
			}
			$component['preset'] = $presetId;
			$result[] = $component;
		}
		return $result;
	}

	/**
	 * @param array<int,array<string,mixed>> $components
	 * @param array<int,array<string,mixed>> $tools
	 * @param array<int,array<string,mixed>> $memories
	 * @return array<int,array<string,mixed>>
	 */
	private function attachRuntimeDetails(array $components, array $tools, array $memories): array {
		$byPreset = [];
		foreach ($components as $index => $component) {
			$byPreset[(string)$component['preset_id']] = $index;
		}
		foreach ($tools as $tool) {
			$presetId = $this->presetIdFromWrapper((string)($tool['source_id'] ?? ''), 'configured_tool_');
			if ($presetId !== '' && isset($byPreset[$presetId])) {
				$components[$byPreset[$presetId]]['tool_names'][] = (string)($tool['name'] ?? '');
			}
		}
		foreach ($memories as $memory) {
			$presetId = (string)($memory['preset_id'] ?? '');
			if ($presetId !== '' && isset($byPreset[$presetId])) {
				$index = $byPreset[$presetId];
				$components[$index]['memory_resources'][] = (string)($memory['resource_id'] ?? '');
				$components[$index]['memory_roles'] = array_merge(
					(array)($components[$index]['memory_roles'] ?? []),
					(array)($memory['roles'] ?? [])
				);
			}
		}
		foreach ($components as &$component) {
			$component['tool_names'] = array_values(array_unique(array_filter($component['tool_names'])));
			$component['memory_resources'] = array_values(array_unique(array_filter($component['memory_resources'])));
			$component['memory_roles'] = array_values(array_unique(array_filter((array)($component['memory_roles'] ?? []))));

			$effectiveRoles = (array)($component['roles'] ?? []);
			if ($component['memory_roles'] !== []) {
				$effectiveRoles = array_values(array_filter(
					$effectiveRoles,
					static fn(string $role): bool => $role !== 'memory'
				));
				$effectiveRoles = array_merge($effectiveRoles, $component['memory_roles']);
			}
			if ($component['tool_names'] !== []) {
				$effectiveRoles[] = 'tool';
			}
			$component['effective_roles'] = array_values(array_unique(array_filter($effectiveRoles)));
		}
		unset($component);
		return $components;
	}

	/** @param array<string,mixed> $flow @return array<string,mixed> */
	private function findAssistantNode(array $flow, string $preferredId): array {
		$fallback = [];
		foreach ($flow['nodes'] ?? [] as $node) {
			if (!is_array($node)) {
				continue;
			}
			if ((string)($node['id'] ?? '') === $preferredId) {
				return $node;
			}
			if ($fallback === [] && in_array((string)($node['type'] ?? ''), ['aiassistantnode', 'streamingaiassistantnode'], true)) {
				$fallback = $node;
			}
		}
		return $fallback;
	}

	private function presetIdFromWrapper(string $resourceId, string $prefix): string {
		if (!str_starts_with($resourceId, $prefix)) {
			return '';
		}
		$value = substr($resourceId, strlen($prefix));
		$value = preg_replace('/_\d+$/', '', $value) ?? $value;
		foreach ($this->presetRepository->getPresets() as $presetId => $_preset) {
			if ($this->sanitizeResourceId((string)$presetId) === $value) {
				return (string)$presetId;
			}
		}
		return '';
	}

	private function sanitizeResourceId(string $id): string {
		$id = (string)preg_replace('/[^A-Za-z0-9_]+/', '_', trim($id));
		$id = trim($id, '_');
		if ($id === '') {
			return 'component';
		}
		if (preg_match('/^[0-9]/', $id)) {
			$id = 'component_' . $id;
		}
		return strtolower($id);
	}

	/** @return array<int,string> */
	private function normalizeIds(mixed $values, bool $technical = true): array {
		if (is_string($values)) {
			$values = preg_split('/[\r\n,]+/', $values) ?: [];
		}
		if (!is_array($values)) {
			return [];
		}
		$result = [];
		foreach ($values as $value) {
			$value = trim((string)$value);
			if ($technical) {
				$value = $this->normalizeId($value);
			}
			if ($value !== '') {
				$result[$value] = true;
			}
		}
		return array_keys($result);
	}

	private function normalizeId(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}

	private function redact(mixed $value, string $key = ''): mixed {
		$normalizedKey = strtolower($key);
		if ($normalizedKey !== '' && preg_match('/(?:password|passwd|secret|token|api[_-]?key|authorization|credential|private[_-]?key)/', $normalizedKey)) {
			return '[redacted]';
		}
		if (!is_array($value)) {
			return $value;
		}
		$result = [];
		foreach ($value as $childKey => $childValue) {
			$result[$childKey] = $this->redact($childValue, (string)$childKey);
		}
		return $result;
	}

	private function toBool(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}
		if (is_int($value) || is_float($value)) {
			return $value !== 0;
		}
		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
	}
}
