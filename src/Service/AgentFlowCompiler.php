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

namespace Base3\MissionBay\Service;

use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\AssistantFoundation\Dto\AgentCapabilitySourceConfig;
use Base3\MissionBay\Api\IAgentComponentFlowBuilder;
use Base3\MissionBay\Api\IAgentFlowCompiler;
use Base3\MissionBay\Dto\AgentFlowCompilation;
use Base3\MissionBay\Orchestrator\Profile\AgentOrchestratorProfile;
use Base3\MissionBay\Orchestrator\Profile\AgentOrchestratorProfileRepository;
use Base3\MissionBay\Profile\AgentContextProfileResolver;
use Base3\MissionBay\Profile\AgentMemoryProfileResolver;
use Base3\MissionBay\Profile\AgentToolProfileResolver;

/**
 * Builds the effective MissionBay flow from stored agent settings.
 *
 * Compilation is intentionally separate from the replaceable agent execution
 * contract because alternate runtimes do not have MissionBay AgentFlows.
 */
final class AgentFlowCompiler implements IAgentFlowCompiler {

	private const ASSISTANT_NODE_ID = 'assistant';
	private const ASSISTANT_NODE_TYPE = 'aiassistantnode';

	public function __construct(
		private readonly IAgentComponentFlowBuilder $componentFlowBuilder,
		private readonly AgentOrchestratorProfileRepository $orchestratorProfileRepository,
		private readonly ?AgentToolProfileResolver $toolProfileResolver = null,
		private readonly ?AgentMemoryProfileResolver $memoryProfileResolver = null,
		private readonly ?AgentContextProfileResolver $contextProfileResolver = null
	) {}

	public static function getName(): string {
		return 'agentflowcompiler';
	}

	public function compile(array $agentSettings): AgentFlowCompilation {
		$warnings = [];
		$chatModelPreset = $this->normalizeTechnicalKey((string)($agentSettings['chatmodel'] ?? ''));

		if ($chatModelPreset === '') {
			throw new \RuntimeException('Chat model preset is required.');
		}

		$flow = $this->createBaseFlow();
		$profileId = AgentOrchestratorProfileRepository::resolveProfileId((string)($agentSettings['orchestrator_profile'] ?? ''));
		$auto = $profileId === AgentOrchestratorProfileRepository::AUTO_PROFILE_ID;
		// Explicit zero-loop profiles keep their existing resource-role filtering.
		// Auto builds the configured composition once before deciding whether a loop is needed.
		$profile = $auto ? null : $this->orchestratorProfileRepository->getProfile($profileId);
		$toolsEnabled = $profile === null || $profile->getMaxToolLoops() > 0;
		if ($profile !== null) {
			$flow = $this->applyOrchestratorProfile($flow, $profile, $warnings);
			if ($toolsEnabled) {
				$flow = $this->applyCapabilityConfiguration($flow, $agentSettings);
			}
		}

		$memoryProfileId = $this->normalizeTechnicalKey((string)($agentSettings['memory_profile'] ?? ''));
		$contextProfileId = $this->normalizeTechnicalKey((string)($agentSettings['context_profile'] ?? ''));
		$toolProfileIds = $this->normalizeStringIds($agentSettings['tool_profiles'] ?? []);
		$profileComponents = [];

		if ($toolsEnabled && $toolProfileIds !== [] && !$this->toolProfileResolver instanceof AgentToolProfileResolver) {
			throw new \RuntimeException('Agent tool profile resolver is not available.');
		}
		if ($this->toolProfileResolver instanceof AgentToolProfileResolver) {
			$profileComponents = $this->toolProfileResolver->resolveComponents($toolProfileIds, $toolsEnabled);
		}

		$memoryProfileComponents = [];
		if ($memoryProfileId !== '') {
			if (!$this->memoryProfileResolver instanceof AgentMemoryProfileResolver) {
				throw new \RuntimeException('Agent memory profile resolver is not available.');
			}
			$memoryProfileComponents = $this->memoryProfileResolver->resolveComponents($memoryProfileId);
		}

		$contextProfileComponents = [];
		if ($contextProfileId !== '') {
			if (!$this->contextProfileResolver instanceof AgentContextProfileResolver) {
				throw new \RuntimeException('Agent context profile resolver is not available.');
			}
			$contextProfileComponents = $this->contextProfileResolver->resolveComponents($contextProfileId);
		}

		$directComponents = $this->normalizeAgentComponents($agentSettings['agent_components'] ?? []);
		$chatModelComponent = [
			'preset' => $chatModelPreset,
			'attach_as' => ['chatmodel'],
			'enabled' => true
		];
		$components = $this->mergeAgentComponents(
			array_merge([$chatModelComponent], $profileComponents, $memoryProfileComponents, $contextProfileComponents),
			$directComponents
		);

		$flow = $this->componentFlowBuilder->build($flow, $components, self::ASSISTANT_NODE_ID);
		$warnings = array_merge($warnings, $this->componentFlowBuilder->getWarnings());
		$this->assertSingleChatModel($flow, $chatModelPreset);

		if ($auto) {
			// A failed expansion must not look like a valid tool-free composition.
			if ($warnings !== []) {
				throw new \RuntimeException('Automatic orchestration requires a valid component composition: ' . implode(' ', array_unique($warnings)));
			}
			$nodeIndex = $this->findAssistantNodeIndex($flow);
			$requiresToolLoop = !empty($flow['nodes'][$nodeIndex]['docks']['tools']);
			if ($this->toBool($agentSettings['expert_overrides_enabled'] ?? false)) {
				$sources = AgentCapabilitySourceConfig::fromArray(is_array($agentSettings['capability_sources'] ?? null) ? $agentSettings['capability_sources'] : []);
				$requiresToolLoop = $requiresToolLoop || !$sources->isEmpty();
			}
			$profile = $this->orchestratorProfileRepository->resolveAutoSelection($agentSettings, $requiresToolLoop);
			$flow = $this->applyOrchestratorProfile($flow, $profile, $warnings);
			if ($profile->getMaxToolLoops() > 0) {
				$flow = $this->applyCapabilityConfiguration($flow, $agentSettings);
			}
		}

		return new AgentFlowCompilation(
			$flow,
			array_values(array_unique($warnings))
		);
	}

	/** @return array<string,mixed> */
	private function createBaseFlow(): array {
		return [
			'nodes' => [[
				'id' => self::ASSISTANT_NODE_ID,
				'type' => self::ASSISTANT_NODE_TYPE,
				'docks' => []
			]],
			'resources' => [],
			'connections' => [
				[
					'from' => '__input__',
					'output' => 'system',
					'to' => self::ASSISTANT_NODE_ID,
					'input' => 'system'
				],
				[
					'from' => '__input__',
					'output' => 'prompt',
					'to' => self::ASSISTANT_NODE_ID,
					'input' => 'prompt'
				]
			]
		];
	}

	/** @param array<string,mixed> $flow @param array<string,mixed> $settings @return array<string,mixed> */
	private function applyCapabilityConfiguration(array $flow, array $settings): array {
		$nodeIndex = $this->findAssistantNodeIndex($flow);
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
			// Legacy records explicitly configuring selection previously ran the
			// deterministic selection stage. Preserve their configured filters and
			// limits when applying the new native default. Explicit profile choices
			// still own their stage list, including expert settings.
			if ($legacyMode && !in_array('capability-selection', $flow['nodes'][$nodeIndex]['inputs']['stages'], true)
				&& !in_array('ai-capability-selection', $flow['nodes'][$nodeIndex]['inputs']['stages'], true)) {
				$stages = &$flow['nodes'][$nodeIndex]['inputs']['stages'];
				$decisionIndex = array_search('model-decision', $stages, true);
				array_splice($stages, $decisionIndex, 0, ['capability-selection']);
				unset($stages);
			}
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
		array &$warnings
	): array {
		$nodeIndex = $this->findAssistantNodeIndex($flow);
		if ($nodeIndex === null) {
			$warnings[] = 'Assistant node not found: ' . self::ASSISTANT_NODE_ID;
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
	private function assertSingleChatModel(array $flow, string $presetId): void {
		$nodeIndex = $this->findAssistantNodeIndex($flow);

		if ($nodeIndex === null) {
			throw new \RuntimeException('Assistant node is missing after flow compilation.');
		}

		$docks = is_array($flow['nodes'][$nodeIndex]['docks'] ?? null) ? $flow['nodes'][$nodeIndex]['docks'] : [];
		$chatModels = is_array($docks['chatmodel'] ?? null) ? array_values(array_filter(
			$docks['chatmodel'],
			static fn(mixed $id): bool => is_string($id) && trim($id) !== ''
		)) : [];

		if (count($chatModels) !== 1) {
			throw new \RuntimeException('Chat model preset could not be attached exactly once: ' . $presetId);
		}
	}

	/** @param array<string,mixed> $flow */
	private function findAssistantNodeIndex(array $flow): ?int {
		foreach ($flow['nodes'] ?? [] as $index => $node) {
			if (!is_array($node)) {
				continue;
			}
			if ((string)($node['id'] ?? '') === self::ASSISTANT_NODE_ID) {
				return (int)$index;
			}
		}
		return null;
	}

	/**
	 * @param array<int,array<string,mixed>> $profileComponents
	 * @param array<int,array<string,mixed>> $directComponents
	 * @return array<int,array<string,mixed>>
	 */
	private function mergeAgentComponents(array $profileComponents, array $directComponents): array {
		$result = [];
		foreach (array_merge($profileComponents, $directComponents) as $component) {
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

	private function normalizeTechnicalKey(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}
}
