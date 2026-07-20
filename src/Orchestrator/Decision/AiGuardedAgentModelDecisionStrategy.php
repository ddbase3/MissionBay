<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Orchestrator\Decision;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Api\IAgentModelDecisionStrategy;
use MissionBay\Dto\Orchestrator\AgentModelDecisionAssessment;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;

final class AiGuardedAgentModelDecisionStrategy extends AbstractAgentModelDecisionStrategy implements IAgentModelDecisionStrategy {

	private const CONTROL_TOOL_NAME = 'missionbay_tool_phase_decision';

	public static function getName(): string {
		return AgentModelDecisionConfig::STRATEGY_AI_GUARDED;
	}

	public function decide(IAgentContext $context, AgentModelDecisionConfig $config): AgentStageResult {
		try {
			$runtime = $this->readRuntime($context);
		} catch (\Throwable $e) {
			return $this->failure('stage_runtime_error', $e->getMessage(), []);
		}

		$this->log($runtime['logger'], 'Tool phase iteration ' . $runtime['iteration'] . ' started with AI-guarded model decision.');
		$tools = array_merge($runtime['tool_definitions'], [$this->getControlToolDefinition()]);

		try {
			$first = $this->callModel(
				$runtime['model'],
				$this->buildMessages($runtime['messages'], $this->buildPrimaryInstruction($runtime['continuation_hint'])),
				$tools,
				$runtime['model_results']
			);
		} catch (\Throwable $e) {
			$this->logError($runtime['logger'], 'Model completion call failed: ' . $e->getMessage());
			return $this->recoverModelFailure($context, $e, $runtime['model_results']);
		}

		$firstDecision = $this->inspectResult($first, false, $runtime['mutation_tool_names']);
		if ($firstDecision['tool_calls'] !== []) {
			return $this->toolCallResult(
				$context,
				$runtime['messages'],
				$first,
				$firstDecision['tool_calls'],
				$runtime['model_results'],
				$firstDecision['assessment']
			);
		}

		if ($firstDecision['assessment']->isClarificationRequired()) {
			return $this->completeResult(
				$context,
				$first,
				$runtime['model_results'],
				$firstDecision['assessment'],
				$this->buildClarificationInstruction($firstDecision['assessment'])
			);
		}

		if ($firstDecision['assessment']->isAcceptedCompletion($config->getConfidenceThreshold())) {
			return $this->completeResult($context, $first, $runtime['model_results'], $firstDecision['assessment']);
		}

		if (!$config->isRepairEnabled()) {
			$unresolved = AgentModelDecisionAssessment::unresolved(
				false,
				'Model decision repair is disabled and the initial decision was not safely terminal.',
				$firstDecision['assessment']->indicatesMutationIntent()
			);
			return $this->completeResult(
				$context,
				$first,
				$runtime['model_results'],
				$unresolved,
				$this->buildUnresolvedInstruction()
			);
		}

		$this->log($runtime['logger'], 'Initial model decision was not safely terminal. Starting one guarded repair call.');
		try {
			$repair = $this->callModel(
				$runtime['model'],
				$this->buildMessages($runtime['messages'], $this->buildRepairInstruction()),
				$tools,
				$runtime['model_results']
			);
		} catch (\Throwable $e) {
			$this->logError($runtime['logger'], 'Model decision repair call failed: ' . $e->getMessage());
			$unresolved = AgentModelDecisionAssessment::unresolved(
				true,
				'The guarded repair call failed before producing a reliable decision.',
				$firstDecision['assessment']->indicatesMutationIntent()
			);
			return $this->recoverModelFailure(
				$context,
				$e,
				$runtime['model_results'],
				[$firstDecision['assessment'], $unresolved]
			);
		}

		$repairDecision = $this->inspectResult($repair, true, $runtime['mutation_tool_names']);
		if ($repairDecision['tool_calls'] !== []) {
			return $this->toolCallResult(
				$context,
				$runtime['messages'],
				$repair,
				$repairDecision['tool_calls'],
				$runtime['model_results'],
				$repairDecision['assessment'],
				[$firstDecision['assessment']]
			);
		}

		if ($repairDecision['assessment']->isClarificationRequired()) {
			return $this->completeResult(
				$context,
				$repair,
				$runtime['model_results'],
				$repairDecision['assessment'],
				$this->buildClarificationInstruction($repairDecision['assessment']),
				[$firstDecision['assessment']]
			);
		}

		if ($repairDecision['assessment']->isAcceptedCompletion($config->getConfidenceThreshold())) {
			return $this->completeResult(
				$context,
				$repair,
				$runtime['model_results'],
				$repairDecision['assessment'],
				'',
				[$firstDecision['assessment']]
			);
		}

		$unresolved = $repairDecision['assessment']->getDecision() === AgentModelDecisionAssessment::DECISION_UNRESOLVED
			? $repairDecision['assessment']
			: AgentModelDecisionAssessment::unresolved(
				true,
				'The guarded repair call did not emit an executable tool call or a reliable terminal decision.',
				$repairDecision['assessment']->indicatesMutationIntent()
			);

		return $this->completeResult(
			$context,
			$repair,
			$runtime['model_results'],
			$unresolved,
			$this->buildUnresolvedInstruction(),
			[$firstDecision['assessment']]
		);
	}

	/** @param array<int,string> $mutationToolNames @return array{tool_calls:array<int,AiToolCall>,assessment:AgentModelDecisionAssessment} */
	private function inspectResult(AiChatResult $result, bool $repairAttempted, array $mutationToolNames): array {
		$actualCalls = [];
		$controlCall = null;
		foreach ($result->getToolCalls() as $call) {
			if (!$call instanceof AiToolCall) {
				continue;
			}
			if ($call->getName() === self::CONTROL_TOOL_NAME) {
				$controlCall = $call;
				continue;
			}
			$actualCalls[] = $call;
		}

		if ($actualCalls !== []) {
			return [
				'tool_calls' => $actualCalls,
				'assessment' => AgentModelDecisionAssessment::toolCall(
					$this->getToolNames($actualCalls),
					$repairAttempted,
					$mutationToolNames
				)
			];
		}

		if ($controlCall instanceof AiToolCall) {
			return [
				'tool_calls' => [],
				'assessment' => AgentModelDecisionAssessment::fromControlCall(
					$controlCall,
					$repairAttempted,
					$mutationToolNames
				)
			];
		}

		return [
			'tool_calls' => [],
			'assessment' => AgentModelDecisionAssessment::unresolved(
				$repairAttempted,
				'The model returned neither an executable tool call nor the required structured tool-phase decision.'
			)
		];
	}

	private function buildPrimaryInstruction(string $continuationHint): string {
		$instruction = implode("\n", [
			'You are in the tool-decision phase. Do not write the user-facing final answer in this phase.',
			'If the user request requires an available tool and the required arguments are known, call that real tool now.',
			'If no tool is required, call ' . self::CONTROL_TOOL_NAME . ' with decision=complete.',
			'If required information is missing, call ' . self::CONTROL_TOOL_NAME . ' with decision=clarification_required and provide the clarification question.',
			'Use decision=tool_required only when a tool action is necessary but no executable tool call can be emitted.',
			'Always provide the semantic intent, confidence, candidate tool names, and a short reason in the control call.',
			'Never claim that an action was executed unless a real tool call is emitted and later succeeds.'
		]);
		if ($continuationHint !== '') {
			$instruction .= "\n\n" . $continuationHint;
		}
		return $instruction;
	}

	private function buildRepairInstruction(): string {
		return implode("\n", [
			'The previous tool-decision response did not produce a reliable executable or terminal decision.',
			'Re-evaluate the current user request using the complete conversation and the available tools.',
			'If a tool is required and its arguments can be determined, call the real tool now.',
			'Otherwise call ' . self::CONTROL_TOOL_NAME . ' with either decision=complete or decision=clarification_required.',
			'Do not produce a user-facing answer and do not claim that any state change already happened.'
		]);
	}

	private function buildClarificationInstruction(AgentModelDecisionAssessment $assessment): string {
		$clarification = trim($assessment->getClarification());
		return $clarification === ''
			? 'Ask the user a concise clarification question. No tool action was executed in this turn.'
			: 'Ask the user this clarification question without claiming that an action was executed: ' . $clarification;
	}

	private function buildUnresolvedInstruction(): string {
		return implode("\n", [
			'The tool-decision phase remained unresolved after one guarded repair attempt.',
			'No additional tool action was executed.',
			'Do not claim that any external state, configuration, record, plugin, file, or account was changed.'
		]);
	}

	/** @return array<string,mixed> */
	private function getControlToolDefinition(): array {
		return [
			'type' => 'function',
			'label' => 'MissionBay Tool Phase Decision',
			'annotations' => ['readOnlyHint' => true],
			'function' => [
				'name' => self::CONTROL_TOOL_NAME,
				'description' => 'Internal control decision used only to terminate or clarify the tool phase. This is not an executable user tool.',
				'parameters' => [
					'type' => 'object',
					'additionalProperties' => false,
					'properties' => [
						'decision' => [
							'type' => 'string',
							'enum' => [
								AgentModelDecisionAssessment::DECISION_COMPLETE,
								AgentModelDecisionAssessment::DECISION_TOOL_REQUIRED,
								AgentModelDecisionAssessment::DECISION_CLARIFICATION_REQUIRED
							]
						],
						'intent' => [
							'type' => 'string',
							'enum' => [
								AgentModelDecisionAssessment::INTENT_MUTATION,
								AgentModelDecisionAssessment::INTENT_READ,
								AgentModelDecisionAssessment::INTENT_CONVERSATION,
								AgentModelDecisionAssessment::INTENT_UNKNOWN
							]
						],
						'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
						'candidate_tools' => ['type' => 'array', 'items' => ['type' => 'string']],
						'reason' => ['type' => 'string'],
						'clarification' => ['type' => 'string']
					],
					'required' => ['decision', 'intent', 'confidence', 'candidate_tools', 'reason']
				]
			]
		];
	}
}
