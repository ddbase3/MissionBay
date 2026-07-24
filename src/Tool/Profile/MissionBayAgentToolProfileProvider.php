<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Tool\Profile;

use AssistantFoundation\Api\IAgentToolProfileProvider;
use AssistantFoundation\Api\IAgentToolSet;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentExecutionRequest;
use MissionBay\Api\IAgentComponentPresetMaterializer;
use MissionBay\Api\IAgentTool;
use MissionBay\Capability\AgentCapabilityCatalogBuilder;
use Base3\Event\Api\IEventManager;
use MissionBay\Api\IAgentMutationGuardedTool;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use MissionBay\Profile\AgentToolProfileResolver;

/**
 * Exposes the existing MissionBay tool-profile store through a runtime-neutral
 * governed boundary. Read-only calls execute immediately; explicit mutations
 * are exported only when they require approval and satisfy commit-guard rules.
 */
final class MissionBayAgentToolProfileProvider implements IAgentToolProfileProvider {

	public function __construct(
		private readonly AgentToolProfileResolver $profileResolver,
		private readonly IAgentComponentPresetMaterializer $presetMaterializer,
		private readonly AgentCapabilityCatalogBuilder $catalogBuilder,
		private readonly AgentToolDefinitionSemantics $definitionSemantics,
		private readonly AgentToolContractValidationService $contractValidationService,
		private readonly AgentActionFingerprint $fingerprint,
		private readonly AgentMutationCommitGuardService $mutationCommitGuardService,
		private readonly ?IEventManager $eventManager = null
	) {}

	public static function getName(): string {
		return 'missionbayagenttoolprofileprovider';
	}

	public static function getProviderId(): string {
		return 'missionbay';
	}

	public function getOptions(): array {
		return $this->profileResolver->getOptions();
	}

	public function hasProfile(string $profileId): bool {
		$profileId = $this->normalizeId($profileId);
		if ($profileId === '') {
			return false;
		}

		foreach ($this->getOptions() as $option) {
			if ($this->normalizeId((string)($option['id'] ?? '')) === $profileId) {
				return true;
			}
		}

		return false;
	}

	public function resolve(array $profileIds, AgentExecutionRequest $request): IAgentToolSet {
		$profileIds = $this->normalizeIds($profileIds);
		$context = $this->presetMaterializer->createContext(array_replace(
			$request->getContext(),
			[
				'agent_configuration' => $request->getAgentConfiguration(),
				'agent_inputs' => $request->getInputs(),
				'tool_profiles' => $profileIds,
				'tool_mode' => 'governed'
			]
		));

		if ($profileIds === []) {
			return new MissionBayAgentToolSet(
				new AgentCapabilityCatalog(),
				[],
				[],
				$context,
				$this->contractValidationService,
				$this->definitionSemantics,
				$this->fingerprint,
				$this->mutationCommitGuardService,
				$this->eventManager
			);
		}

		$components = $this->profileResolver->resolveComponents($profileIds);
		$tools = [];
		$toolsByName = [];
		$definitions = [];
		$warnings = [];

		foreach ($components as $component) {
			$presetId = $this->normalizeId((string)($component['preset'] ?? ''));
			if ($presetId === '') {
				continue;
			}

			$materialization = $this->presetMaterializer->materialize($presetId, $context);
			$warnings = array_merge($warnings, $materialization->getWarnings());
			$tool = $materialization->getTool();
			if (!$tool instanceof IAgentTool) {
				$warnings[] = 'Tool profile preset produced no executable tool: ' . $presetId;
				continue;
			}

			$toolKey = 'object:' . spl_object_id($tool);
			$tools[$toolKey] = $tool;

			foreach ($tool->getToolDefinitions() as $definition) {
				if (!is_array($definition)) {
					$warnings[] = 'Tool returned an invalid definition: ' . $presetId;
					continue;
				}

				$definition = $this->normalizeDefinition($definition);
				$name = $this->definitionSemantics->getToolName($definition);
				if ($name === '') {
					$warnings[] = 'Tool definition has no function name: ' . $presetId;
					continue;
				}

				$isReadOnly = $this->definitionSemantics->isExplicitReadOnlyDefinition($definition);
				$isMutation = $this->definitionSemantics->isMutationDefinition($definition);
				if (!$isReadOnly && !$isMutation) {
					$warnings[] = 'Tool was not exposed because its side-effect semantics are not explicit: ' . $name;
					continue;
				}
				if ($isMutation && !$this->definitionSemantics->requiresApprovalDefinition($definition)) {
					$warnings[] = 'Mutation tool was not exposed because it does not require approval: ' . $name;
					continue;
				}
				if (
					$isMutation
					&& $this->definitionSemantics->isCommitGuardRequired($definition)
					&& !$tool instanceof IAgentMutationGuardedTool
				) {
					$warnings[] = 'Mutation tool was not exposed because its required commit guard is unavailable: ' . $name;
					continue;
				}

				if (isset($toolsByName[$name])) {
					throw new \RuntimeException('Duplicate tool name: ' . $name);
				}

				$toolsByName[$name] = $tool;
				$definitions[] = $definition;
			}
		}

		$catalog = $this->catalogBuilder->build(array_values($tools), $definitions);
		$context->setVar(AgentToolLoopContextKeys::TOOL_DEFINITIONS, $definitions);
		$context->setVar(AgentToolLoopContextKeys::TOOLS, array_values($tools));
		$context->setVar(AgentToolLoopContextKeys::MUTATION_TOOL_NAMES, $this->definitionSemantics->getMutationToolNames($definitions));

		return new MissionBayAgentToolSet(
			$catalog,
			$toolsByName,
			array_values($tools),
			$context,
			$this->contractValidationService,
			$this->definitionSemantics,
			$this->fingerprint,
			$this->mutationCommitGuardService,
			$this->eventManager,
			array_values(array_unique(array_filter(array_map('trim', $warnings))))
		);
	}

	/** @param array<string,mixed> $definition @return array<string,mixed> */
	private function normalizeDefinition(array $definition): array {
		if (is_array($definition['function'] ?? null)) {
			return $definition;
		}

		$name = trim((string)($definition['name'] ?? ''));
		$function = [
			'name' => $name,
			'description' => trim((string)($definition['description'] ?? '')),
			'parameters' => is_array($definition['parameters'] ?? null)
				? $definition['parameters']
				: ['type' => 'object', 'properties' => [], 'required' => []]
		];
		$result = $definition;
		unset($result['name'], $result['description'], $result['parameters']);
		$result['type'] = (string)($result['type'] ?? 'function');
		$result['function'] = $function;

		return $result;
	}

	/** @param array<int,mixed> $profileIds @return array<int,string> */
	private function normalizeIds(array $profileIds): array {
		$result = [];
		foreach ($profileIds as $profileId) {
			$profileId = $this->normalizeId((string)$profileId);
			if ($profileId !== '') {
				$result[$profileId] = $profileId;
			}
		}
		return array_values($result);
	}

	private function normalizeId(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}
}
