<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Orchestrator\Stage;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentStageResult;
use MissionBay\Dto\Assistant\AgentCapabilityDiscoveryResult;

/**
 * Publishes the pre-resolved run-local capability composition into the stage
 * context before capability selection and model decision.
 */
final class AgentCapabilityDiscoveryStage implements IAgentStage {

	public function __construct(
		private readonly string $id = 'capability-discovery',
		private readonly string $stageName = 'capability-discovery'
	) {}

	public static function getName(): string {
		return 'agentcapabilitydiscoverystage';
	}

	public function id(): string {
		return $this->id;
	}

	public function name(): string {
		return $this->stageName;
	}

	public function getDescription(): string {
		return 'Activates the run-specific capability composition resolved from the agent configuration.';
	}

	public function getAiUsage(): string {
		return IAgentStage::AI_USAGE_NONE;
	}

	public function supports(IAgentContext $context): bool {
		return $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_MODEL
			&& $context->getVar(AgentToolLoopContextKeys::CAPABILITY_DISCOVERY_APPLIED) !== true
			&& $context->getVar(AgentToolLoopContextKeys::COMPLETED) !== true
			&& (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '') === '';
	}

	public function process(IAgentContext $context): AgentStageResult {
		$discovery = $context->getVar(AgentToolLoopContextKeys::CAPABILITY_DISCOVERY);
		$catalog = $context->getVar(AgentToolLoopContextKeys::CAPABILITY_CATALOG);

		if (!$discovery instanceof AgentCapabilityDiscoveryResult) {
			return $this->failure('capability_discovery_missing', 'Capability discovery stage did not receive a run-local discovery result.');
		}

		$payload = $discovery->toArray();
		$payload['catalog_size'] = $catalog instanceof AgentCapabilityCatalog ? count($catalog) : 0;
		$this->emit($context, $payload);

		if (!$catalog instanceof AgentCapabilityCatalog) {
			return $this->failure('capability_catalog_missing', 'Capability discovery did not produce a valid tool catalog.', $payload);
		}
		if ($discovery->hasErrors()) {
			return $this->failure('capability_discovery_failed', implode(' ', $discovery->getErrors()), $payload);
		}

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::CAPABILITY_DISCOVERY_APPLIED => true,
			AgentToolLoopContextKeys::RESOURCE_PROVIDERS => $discovery->getResourceProviders(),
			AgentToolLoopContextKeys::PROMPT_PROVIDERS => $discovery->getPromptProviders(),
			AgentToolLoopContextKeys::MODULE_INSTRUCTIONS => $discovery->getInstructions()
		], $payload);
	}

	/** @param array<string,mixed> $payload */
	private function emit(IAgentContext $context, array $payload): void {
		$callback = $context->getVar(AgentToolLoopContextKeys::EVENT_CALLBACK);
		if (!is_callable($callback)) {
			return;
		}
		try {
			$callback('capability.discovery', $payload);
		} catch (\Throwable) {
		}
	}

	/** @param array<string,mixed> $detail */
	private function failure(string $code, string $message, array $detail = []): AgentStageResult {
		return AgentStageResult::patch([
			AgentToolLoopContextKeys::FAILURE_CODE => $code,
			AgentToolLoopContextKeys::FAILURE_MESSAGE => $message,
			AgentToolLoopContextKeys::FAILURE_DETAIL => $detail,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FAILED
		]);
	}
}
