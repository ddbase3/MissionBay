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

namespace MissionBay\Orchestrator\Stage;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentStageResult;
use MissionBay\Api\IAgentModelDecisionStrategyResolver;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use MissionBay\Orchestrator\Decision\AiGuardedAgentModelDecisionStrategy;
use MissionBay\Orchestrator\Decision\SimpleAgentModelDecisionStrategy;

/**
 * Stable model-decision stage delegating decision behavior to a profile-selected strategy.
 */
final class AgentModelDecisionStage implements IAgentStage {

	public function __construct(
		private readonly string $id = 'model-decision',
		private readonly string $stageName = 'model-decision',
		private readonly ?IAgentModelDecisionStrategyResolver $strategyResolver = null
	) {}

	public static function getName(): string {
		return 'agentmodeldecisionstage';
	}

	public function id(): string {
		return $this->id;
	}

	public function name(): string {
		return $this->stageName;
	}

	public function getDescription(): string {
		return 'Calls the active chat model through the configured decision strategy and requires a reliable tool or terminal decision.';
	}

	public function getAiUsage(): string {
		return IAgentStage::AI_USAGE_REQUIRED;
	}

	public function supports(IAgentContext $context): bool {
		return $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_MODEL
			&& $context->getVar(AgentToolLoopContextKeys::COMPLETED) !== true
			&& (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '') === '';
	}

	public function process(IAgentContext $context): AgentStageResult {
		$config = $context->getVar(AgentToolLoopContextKeys::MODEL_DECISION_CONFIG);
		if (!$config instanceof AgentModelDecisionConfig) {
			$config = AgentModelDecisionConfig::simple();
		}

		try {
			$strategy = $this->resolveStrategy($config);
			return $strategy->decide($context, $config);
		} catch (\Throwable $e) {
			return AgentStageResult::patch([
				AgentToolLoopContextKeys::FAILURE_CODE => 'model_decision_strategy_error',
				AgentToolLoopContextKeys::FAILURE_MESSAGE => 'Model decision strategy failed.',
				AgentToolLoopContextKeys::FAILURE_DETAIL => [
					'type' => get_class($e),
					'message' => $e->getMessage(),
					'strategy' => $config->getStrategy()
				],
				AgentToolLoopContextKeys::COMPLETED => false,
				AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FAILED
			]);
		}
	}

	private function resolveStrategy(AgentModelDecisionConfig $config): \MissionBay\Api\IAgentModelDecisionStrategy {
		if ($this->strategyResolver instanceof IAgentModelDecisionStrategyResolver) {
			return $this->strategyResolver->resolve($config->getStrategy());
		}

		return $config->getStrategy() === AgentModelDecisionConfig::STRATEGY_AI_GUARDED
			? new AiGuardedAgentModelDecisionStrategy()
			: new SimpleAgentModelDecisionStrategy();
	}
}
