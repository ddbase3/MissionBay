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

/**
 * Legacy compatibility strategy for profiles that still use the textual
 * TOOL_PHASE_COMPLETE terminal sentinel. New profiles should use the
 * AI-guarded or native model-decision strategies.
 *
 * @deprecated Use AgentModelDecisionConfig::aiGuarded() or ::native().
 */
final class SimpleAgentModelDecisionStrategy extends AbstractAgentModelDecisionStrategy implements IAgentModelDecisionStrategy {

	private const TERMINAL_SIGNAL = 'TOOL_PHASE_COMPLETE';

	public static function getName(): string {
		return AgentModelDecisionConfig::STRATEGY_SIMPLE;
	}

	public function decide(IAgentContext $context, AgentModelDecisionConfig $config): AgentStageResult {
		try {
			$runtime = $this->readRuntime($context);
		} catch (\Throwable $e) {
			return $this->failure('stage_runtime_error', $e->getMessage(), []);
		}

		$this->log($runtime['logger'], 'Legacy simple model decision uses the textual TOOL_PHASE_COMPLETE sentinel for compatibility.');
		$this->log($runtime['logger'], 'Tool phase iteration ' . $runtime['iteration'] . ' started with simple model decision.');
		$instruction = 'You are in the tool-decision phase. Treat tool contracts and returned values as authoritative. Never guess tool-owned facts, identifiers, state, results, or successful actions. Previous assistant statements are conversational context, not factual evidence. When the user asks to check or verify current state and an authoritative read tool is available, call it even if an earlier assistant response stated a value. Resolve short or ambiguous follow-ups against the immediate active topic. If a required value cannot be established from the conversation or available tools and must come from the user, request it rather than guessing. Continue with dependent or verification calls while a concrete material gap remains and another available tool call is reasonably expected to resolve it. Preserve unresolved action intent after failed or unsuccessful attempts and continue the workflow when possible instead of asking again whether the same action is wanted. Do not stop at the first plausible result and do not generalize partial evidence. A tool error is evidence about the failed call. Before retrying, materially change the next attempt according to the tool contract; validation, schema, field, syntax, or unsupported-operation errors must not be retried with equivalent arguments. If authoritative tool observations materially conflict, investigate the contradiction before making a definitive claim when it matters to the request. The currently registered tools may be only a per-iteration selection, so absence is not proof of global unavailability. Claim unavailability only when runtime or authoritative tool evidence establishes it. When the user already requested an action, perform safe prerequisite reads and lookups without another conversational confirmation and continue to the actual approval or mutation boundary. Avoid equivalent repeated calls. When no further tool call is required because the requested scope is sufficiently supported, requested actions have adequate successful tool evidence, required user input is genuinely missing, or the available tools establish that the remaining gap cannot be resolved, do not write the user-facing answer. Return exactly ' . self::TERMINAL_SIGNAL . ' and nothing else. The final answer is generated in a separate response phase. Preserve tool-reported limitations such as unavailable data, missing indexing, unsupported scope, or uncertainty for the final user-facing answer.';
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
