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

use AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use AssistantFoundation\Dto\AgentCapabilitySourceConfig;
use MissionBay\Api\IAgentComponentFlowBuilder;
use MissionBay\Api\IAgentFlowCompiler;
use MissionBay\Dto\AgentFlowCompilation;
use MissionBay\Orchestrator\Profile\AgentOrchestratorProfile;
use MissionBay\Orchestrator\Profile\AgentOrchestratorProfileRepository;
use MissionBay\Profile\AgentContextProfileResolver;
use MissionBay\Profile\AgentMemoryProfileResolver;
use MissionBay\Profile\AgentToolProfileResolver;

/**
 * Builds the effective MissionBay flow from stored agent settings.
 *
 * Compilation is intentionally separate from the replaceable agent execution
 * contract because alternate runtimes do not have MissionBay AgentFlows.
 */
final class AgentFlowCompiler implements IAgentFlowCompiler {

	private const CHAT_LLM_RESOURCE_ID = 'chatllm';
	private const CHAT_LLM_RESOURCE_TYPE = 'configuredchatmodelagentresource';
	private const DEFAULT_ASSISTANT_NODE_ID = 'assistant';
	private const CANONICAL_ASSISTANT_NODE_TYPE = 'aiassistantnode';
	private const LEGACY_ASSISTANT_NODE_TYPE = 'streamingaiassistantnode';
	private const CANONICAL_USER_INPUT = 'prompt';
	private const LEGACY_USER_INPUT = 'user';

	public function __construct(
		private readonly IAgentComponentFlowBuilder $componentFlowBuilder,
		private readonly ?AgentOrchestratorProfileRepository $orchestratorProfileRepository = null,
		private readonly ?AgentToolProfileResolver $toolProfileResolver = null,
		private readonly ?AgentMemoryProfileResolver $memoryProfileResolver = null,
		private readonly ?AgentContextProfileResolver $contextProfileResolver = null
	) {}

	public static function getName(): string {
		return 'agentflowcompiler';
	}

	public function compile(array $agentSettings): AgentFlowCompilation {
		$warnings = [];
		$flow = $this->normalizeAgentFlow($agentSettings['agent_flow'] ?? []);
		$flow = $this->normalizeAssistantNodeTypes($flow, $warnings);
		$flow = $this->normalizePromptInputConnections($flow);

		if ($flow === []) {
			throw new \RuntimeException('Invalid Flow JSON');
		}

		$llm = $this->normalizeTechnicalKey((string)($agentSettings['llm'] ?? ''));
		if ($llm !== '') {
			$flow = $this->applyLlmToAgentFlow($flow, $llm);
		}

		$assistantNodeId = $this->normalizeAssistantNodeId(
			$agentSettings['agent_components_assistant_node'] ?? self::DEFAULT_ASSISTANT_NODE_ID
		);
		$profileId = trim((string)($agentSettings['orchestrator_profile'] ?? ''));
		if ($profileId !== '') {
			if (!$this->orchestratorProfileRepository instanceof AgentOrchestratorProfileRepository) {
				throw new \RuntimeException('Agent orchestrator profile repository is not available.');
			}
			$profile = $this->orchestratorProfileRepository->getProfile($profileId);
			$flow = $this->applyOrchestratorProfile($flow, $profile, $assistantNodeId, $warnings);
		}
		$flow = $this->applyCapabilityConfiguration($flow, $agentSettings, $assistantNodeId);

		$memoryProfileId = $this->normalizeTechnicalKey((string)($agentSettings['memory_profile'] ?? ''));
		$contextProfileId = $this->normalizeTechnicalKey((string)($agentSettings['context_profile'] ?? ''));
		$toolProfileIds = $this->normalizeStringIds($agentSettings['tool_profiles'] ?? []);
		$profileComponents = [];

		if ($toolProfileIds !== [] && !$this->toolProfileResolver instanceof AgentToolProfileResolver) {
			throw new \RuntimeException('Agent tool profile resolver is not available.');
		}
		if ($this->toolProfileResolver instanceof AgentToolProfileResolver) {
			$profileComponents = $this->toolProfileResolver->resolveComponents($toolProfileIds);
		}

		$memoryProfileComponents = [];
		if ($memoryProfileId !== '') {
			if (!$this->memoryProfileResolver instanceof AgentMemoryProfileResolver) {
				throw new \RuntimeException('Agent memory profile resolver is not available.');
			}
			$memoryProfileComponents = $this->memoryProfileResolver->resolveComponents($memoryProfileId);
		}

		if (
			$contextProfileId === ''
			&& $memoryProfileId !== ''
			&& $this->contextProfileResolver instanceof AgentContextProfileResolver
			&& $this->contextProfileResolver->hasProfile($memoryProfileId)
		) {
			$contextProfileId = $memoryProfileId;
			$warnings[] = 'Context profile was derived from the legacy combined profile "' . $memoryProfileId . '". Save the agent with an explicit context profile.';
		}

		$contextProfileComponents = [];
		if ($contextProfileId !== '') {
			if (!$this->contextProfileResolver instanceof AgentContextProfileResolver) {
				throw new \RuntimeException('Agent context profile resolver is not available.');
			}
			$contextProfileComponents = $this->contextProfileResolver->resolveComponents($contextProfileId);
		}

		$legacyComponents = $this->normalizeAgentComponents($agentSettings['agent_components'] ?? []);
		$components = $this->mergeAgentComponents(
			array_merge($profileComponents, $memoryProfileComponents, $contextProfileComponents),
			$legacyComponents
		);

		if ($components !== []) {
			$flow = $this->componentFlowBuilder->build($flow, $components, $assistantNodeId);
			$warnings = array_merge($warnings, $this->componentFlowBuilder->getWarnings());
		}

		return new AgentFlowCompilation(
			$this->normalizePromptInputConnections($flow),
			array_values(array_unique($warnings))
		);
	}

	/** @return array<string,mixed> */
	private function normalizeAgentFlow(mixed $value): array {
		if (is_array($value)) {
			return $value;
		}
		if (!is_string($value)) {
			return [];
		}
		$value = trim($value);
		if ($value === '') {
			return [];
		}
		$decoded = json_decode($value, true);
		return is_array($decoded) ? $decoded : [];
	}

	/**
	 * @param array<string,mixed> $flow
	 * @param array<int,string> $warnings
	 * @return array<string,mixed>
	 */
	private function normalizeAssistantNodeTypes(array $flow, array &$warnings): array {
		$converted = false;
		foreach ($flow['nodes'] ?? [] as $index => $node) {
			if (!is_array($node) || (string)($node['type'] ?? '') !== self::LEGACY_ASSISTANT_NODE_TYPE) {
				continue;
			}
			$flow['nodes'][$index]['type'] = self::CANONICAL_ASSISTANT_NODE_TYPE;
			$converted = true;
		}
		if ($converted) {
			$warnings[] = 'Legacy node type "' . self::LEGACY_ASSISTANT_NODE_TYPE . '" was normalized to "' . self::CANONICAL_ASSISTANT_NODE_TYPE . '". Save the flow with the canonical node type.';
		}
		return $flow;
	}

	/** @param array<string,mixed> $flow @return array<string,mixed> */
	private function normalizePromptInputConnections(array $flow): array {
		if (!isset($flow['connections']) || !is_array($flow['connections'])) {
			return $flow;
		}
		$connections = [];
		$seen = [];
		foreach ($flow['connections'] as $connection) {
			if (!is_array($connection)) {
				continue;
			}
			if ((string)($connection['from'] ?? '') === '__input__' && (string)($connection['output'] ?? '') === self::LEGACY_USER_INPUT) {
				$connection['output'] = self::CANONICAL_USER_INPUT;
			}
			$key = implode("\0", [
				(string)($connection['from'] ?? ''),
				(string)($connection['output'] ?? ''),
				(string)($connection['to'] ?? ''),
				(string)($connection['input'] ?? '')
			]);
			if (isset($seen[$key])) {
				continue;
			}
			$seen[$key] = true;
			$connections[] = $connection;
		}
		$flow['connections'] = $connections;
		return $flow;
	}

	/** @param array<string,mixed> $flow @param array<string,mixed> $settings @return array<string,mixed> */
	private function applyCapabilityConfiguration(array $flow, array $settings, string $assistantNodeId): array {
		$nodeIndex = $this->findAssistantNodeIndex($flow, $assistantNodeId);
		if ($nodeIndex === null) {
			return $flow;
		}
		if (!isset($flow['nodes'][$nodeIndex]['inputs']) || !is_array($flow['nodes'][$nodeIndex]['inputs'])) {
			$flow['nodes'][$nodeIndex]['inputs'] = [];
		}
		$legacyMode = !array_key_exists('orchestrator_profile', $settings);
		$expertOverrides = $this->toBool($settings['expert_overrides_enabled'] ?? false);
		if (($legacyMode || $expertOverrides) && array_key_exists('capability_sources', $settings)) {
			$value = is_array($settings['capability_sources']) ? $settings['capability_sources'] : [];
			$flow['nodes'][$nodeIndex]['inputs']['capabilitysources'] = AgentCapabilitySourceConfig::fromArray($value)->toArray();
		}
		if (($legacyMode || $expertOverrides) && array_key_exists('capability_selection', $settings)) {
			$value = is_array($settings['capability_selection']) ? $settings['capability_selection'] : [];
			$flow['nodes'][$nodeIndex]['inputs']['capabilityselection'] = AgentCapabilitySelectionConfig::fromArray($value)->toArray();
		}
		return $flow;
	}

	/**
	 * @param array<string,mixed> $flow
	 * @param array<int,string> $warnings
	 * @return array<string,mixed>
	 */
	private function applyOrchestratorProfile(
		array $flow,
		AgentOrchestratorProfile $profile,
		string $assistantNodeId,
		array &$warnings
	): array {
		$nodeIndex = $this->findAssistantNodeIndex($flow, $assistantNodeId);
		if ($nodeIndex === null) {
			$warnings[] = 'Assistant node not found for orchestrator profile: ' . $assistantNodeId;
			return $flow;
		}
		if (!isset($flow['nodes'][$nodeIndex]['inputs']) || !is_array($flow['nodes'][$nodeIndex]['inputs'])) {
			$flow['nodes'][$nodeIndex]['inputs'] = [];
		}
		$flow['nodes'][$nodeIndex]['inputs']['stages'] = $profile->getStageIds();
		$flow['nodes'][$nodeIndex]['inputs']['maxtoolloops'] = $profile->getMaxToolLoops();
		$flow['nodes'][$nodeIndex]['inputs']['capabilityselection'] = $profile->getCapabilitySelection()->toArray();
		$flow['nodes'][$nodeIndex]['inputs']['modeldecision'] = $profile->getModelDecision()->toArray();
		$flow['nodes'][$nodeIndex]['inputs']['orchestratorprofile'] = $profile->getId();
		$flow['nodes'][$nodeIndex]['inputs']['deliberateplanning'] = $profile->isDeliberatePlanningEnabled();
		return $flow;
	}

	/** @param array<string,mixed> $flow */
	private function findAssistantNodeIndex(array $flow, string $assistantNodeId): ?int {
		$fallback = null;
		foreach ($flow['nodes'] ?? [] as $index => $node) {
			if (!is_array($node)) {
				continue;
			}
			if ((string)($node['id'] ?? '') === $assistantNodeId) {
				return (int)$index;
			}
			if ($fallback === null && (string)($node['type'] ?? '') === self::CANONICAL_ASSISTANT_NODE_TYPE) {
				$fallback = (int)$index;
			}
		}
		return $fallback;
	}

	/**
	 * @param array<int,array<string,mixed>> $profileComponents
	 * @param array<int,array<string,mixed>> $legacyComponents
	 * @return array<int,array<string,mixed>>
	 */
	private function mergeAgentComponents(array $profileComponents, array $legacyComponents): array {
		$result = [];
		foreach (array_merge($profileComponents, $legacyComponents) as $component) {
			if (!is_array($component)) {
				continue;
			}
			$preset = trim((string)($component['preset'] ?? ''));
			if ($preset === '') {
				continue;
			}
			if (!isset($result[$preset])) {
				$result[$preset] = $component;
				continue;
			}
			$existing = is_array($result[$preset]['attach_as'] ?? null) ? $result[$preset]['attach_as'] : [];
			$additional = is_array($component['attach_as'] ?? null) ? $component['attach_as'] : [];
			$result[$preset] = array_replace_recursive($result[$preset], $component);
			$result[$preset]['attach_as'] = array_values(array_unique(array_merge($existing, $additional)));
		}
		return array_values($result);
	}

	/** @return array<int,string> */
	private function normalizeStringIds(mixed $value): array {
		if (is_string($value)) {
			$value = preg_split('/[\r\n,]+/', $value) ?: [];
		}
		if (!is_array($value)) {
			return [];
		}
		$result = [];
		foreach ($value as $id) {
			$id = $this->normalizeTechnicalKey((string)$id);
			if ($id !== '') {
				$result[$id] = $id;
			}
		}
		return array_values($result);
	}

	private function toBool(mixed $value): bool {
		if (is_bool($value)) {
			return $value;
		}
		if (is_int($value)) {
			return $value !== 0;
		}
		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
	}

	/** @return array<int,array<string,mixed>> */
	private function normalizeAgentComponents(mixed $value): array {
		if (!is_array($value)) {
			return [];
		}
		$result = [];
		foreach ($value as $id => $component) {
			if (!is_array($component)) {
				continue;
			}
			if (!isset($component['preset']) && is_string($id)) {
				$component['preset'] = $id;
			}
			$result[] = $component;
		}
		return $result;
	}

	private function normalizeAssistantNodeId(mixed $value): string {
		$nodeId = trim((string)$value);
		return $nodeId !== '' ? $nodeId : self::DEFAULT_ASSISTANT_NODE_ID;
	}

	/** @param array<string,mixed> $agentFlow @return array<string,mixed> */
	private function applyLlmToAgentFlow(array $agentFlow, string $llm): array {
		if ($llm === '') {
			return $agentFlow;
		}
		if (!isset($agentFlow['resources']) || !is_array($agentFlow['resources'])) {
			$agentFlow['resources'] = [];
		}
		$resources = $agentFlow['resources'];
		$resourceIndex = $this->findChatLlmResourceIndex($resources);
		$resource = [
			'id' => self::CHAT_LLM_RESOURCE_ID,
			'type' => self::CHAT_LLM_RESOURCE_TYPE,
			'config' => [
				'service' => [
					'mode' => 'fixed',
					'value' => $llm
				]
			]
		];
		if ($resourceIndex !== null && isset($resources[$resourceIndex]) && is_array($resources[$resourceIndex])) {
			$resource = array_merge($resources[$resourceIndex], $resource);
			$resource['config'] = is_array($resources[$resourceIndex]['config'] ?? null)
				? $resources[$resourceIndex]['config']
				: [];
			$resource['config']['service'] = [
				'mode' => 'fixed',
				'value' => $llm
			];
			$resource['type'] = self::CHAT_LLM_RESOURCE_TYPE;
		}
		if ($resourceIndex === null) {
			$resources[] = $resource;
		}
		else {
			$resources[$resourceIndex] = $resource;
		}
		$agentFlow['resources'] = array_values($resources);
		return $agentFlow;
	}

	private function findChatLlmResourceIndex(array $resources): ?int {
		$fallback = null;
		foreach ($resources as $index => $resource) {
			if (!is_array($resource)) {
				continue;
			}
			if ((string)($resource['id'] ?? '') === self::CHAT_LLM_RESOURCE_ID) {
				return (int)$index;
			}
			if ($fallback === null && (string)($resource['type'] ?? '') === self::CHAT_LLM_RESOURCE_TYPE) {
				$fallback = (int)$index;
			}
		}
		return $fallback;
	}

	private function normalizeTechnicalKey(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}
}
