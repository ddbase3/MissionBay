<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Orchestrator\Decision;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentStageResult;
use MissionBay\Api\IAgentModelDecisionStrategy;
use MissionBay\Dto\Orchestrator\AgentModelDecisionAssessment;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;

final class SimpleAgentModelDecisionStrategy extends AbstractAgentModelDecisionStrategy implements IAgentModelDecisionStrategy {

	public static function getName(): string {
		return AgentModelDecisionConfig::STRATEGY_SIMPLE;
	}

	public function decide(IAgentContext $context, AgentModelDecisionConfig $config): AgentStageResult {
		try {
			$runtime = $this->readRuntime($context);
		} catch (\Throwable $e) {
			return $this->failure('stage_runtime_error', $e->getMessage(), []);
		}

		$this->log($runtime['logger'], 'Tool phase iteration ' . $runtime['iteration'] . ' started with simple model decision.');
		$instruction = 'You are in the tool-decision phase. Request additional tools only when they are expected to add materially new evidence. When no further tool call is required, do not write the user-facing answer. Return exactly ' . self::TERMINAL_SIGNAL . ' and nothing else. The final answer is generated in a separate response phase.';
		if ($runtime['continuation_hint'] !== '') {
			$instruction .= "\n\n" . $runtime['continuation_hint'];
		}

		try {
			$result = $this->callModel(
				$runtime['model'],
				$this->buildMessages($runtime['messages'], $instruction),
				$runtime['tool_definitions'],
				$runtime['model_results']
			);
		} catch (\Throwable $e) {
			$this->logError($runtime['logger'], 'Model completion call failed: ' . $e->getMessage());
			return $this->recoverModelFailure($context, $e, $runtime['model_results']);
		}

		$toolCalls = $result->getToolCalls();
		if ($toolCalls !== []) {
			$assessment = AgentModelDecisionAssessment::toolCall(
				$this->getToolNames($toolCalls),
				false,
				$runtime['mutation_tool_names']
			);
			return $this->toolCallResult($context, $runtime['messages'], $result, $toolCalls, $runtime['model_results'], $assessment);
		}

		$assessment = new AgentModelDecisionAssessment(
			AgentModelDecisionAssessment::DECISION_COMPLETE,
			AgentModelDecisionAssessment::INTENT_UNKNOWN,
			1.0,
			[],
			'Simple strategy treats a response without tool calls as terminal.'
		);
		$this->log($runtime['logger'], 'Tool phase completed after ' . $runtime['iteration'] . ' iteration(s). Final answer phase starts.');

		return $this->completeResult($context, $result, $runtime['model_results'], $assessment);
	}
}
