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

		$firstDecision = $this->inspectResult($first, false, $runtime['mutation_tool_names'], $runtime['tool_definitions']);
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

		$repairDecision = $this->inspectResult($repair, true, $runtime['mutation_tool_names'], $runtime['tool_definitions']);
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

	/**
	 * @param array<int,string> $mutationToolNames
	 * @param array<int,array<string,mixed>> $toolDefinitions
	 * @return array{tool_calls:array<int,AiToolCall>,assessment:AgentModelDecisionAssessment}
	 */
	private function inspectResult(
		AiChatResult $result,
		bool $repairAttempted,
		array $mutationToolNames,
		array $toolDefinitions
	): array {
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
			$assessment = AgentModelDecisionAssessment::fromControlCall(
				$controlCall,
				$repairAttempted,
				$mutationToolNames
			);
			if ($assessment->isClarificationRequired() && !$this->isGroundedClarification($assessment, $toolDefinitions)) {
				$assessment = AgentModelDecisionAssessment::unresolved(
					$repairAttempted,
					'The clarification decision was not grounded in missing required tool arguments.',
					$assessment->indicatesMutationIntent()
				);
			}

			return [
				'tool_calls' => [],
				'assessment' => $assessment
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


	/** @param array<int,array<string,mixed>> $toolDefinitions */
	private function isGroundedClarification(
		AgentModelDecisionAssessment $assessment,
		array $toolDefinitions
	): bool {
		if (trim($assessment->getClarification()) === '') {
			return false;
		}

		$candidateToolNames = $assessment->getCandidateToolNames();
		if ($assessment->getIntent() === AgentModelDecisionAssessment::INTENT_CONVERSATION && $candidateToolNames === []) {
			return true;
		}

		$missingArgumentNames = $assessment->getMissingArgumentNames();
		if ($candidateToolNames === [] || $missingArgumentNames === []) {
			return false;
		}

		$candidateMap = array_fill_keys($candidateToolNames, true);
		$requiredArgumentMap = [];
		foreach ($toolDefinitions as $definition) {
			$name = trim((string)($definition['function']['name'] ?? ''));
			if ($name === '' || !isset($candidateMap[$name])) {
				continue;
			}
			foreach ((array)($definition['function']['parameters']['required'] ?? []) as $requiredName) {
				if (is_string($requiredName) && trim($requiredName) !== '') {
					$requiredArgumentMap[trim($requiredName)] = true;
				}
			}
		}

		if ($requiredArgumentMap === []) {
			return false;
		}
		foreach ($missingArgumentNames as $missingArgumentName) {
			if (!isset($requiredArgumentMap[$missingArgumentName])) {
				return false;
			}
		}

		return true;
	}

	private function buildPrimaryInstruction(string $continuationHint): string {
		$instruction = implode("\n", [
			'You are in the tool-decision phase. Do not write the user-facing final answer in this phase.',
			'If the user request requires an available tool and the required arguments are known, call that real tool now. If a prerequisite must first be established by another available tool, call that prerequisite tool and continue the dependent sequence in later iterations.',
			'Treat tool descriptions, schemas, returned identifiers, constraints, limitations, and explicit next-step information as authoritative. Never guess tool-owned facts or values. Previous assistant statements are conversational context, not factual evidence. If the user asks to check or verify current state and an authoritative read tool is available, call it even when an earlier assistant response stated a value. Resolve short or ambiguous follow-ups against the immediate active topic before inventing a new entity or domain. If a required value cannot be established from the conversation or available tools and must come from the user, request that value rather than guessing.',
			'The currently registered tools may be only a per-iteration capability selection. Their absence does not prove global unavailability. Claim that a capability is unavailable only when the runtime or authoritative tool evidence establishes that limitation.',
			'Do not use decision=complete merely because one result is relevant or plausible. Complete only when the material requested scope is sufficiently supported, requested actions have successful tool results, required user input is genuinely missing, or the available tools establish that the remaining gap cannot be resolved.',
			'If no tool is required, call ' . self::CONTROL_TOOL_NAME . ' with decision=complete.',
			'If a required tool argument is missing, call ' . self::CONTROL_TOOL_NAME . ' with decision=clarification_required, provide the clarification question, and list the missing required argument names.',
			'When the user already explicitly requested an action, perform safe prerequisite reads and lookups without asking for an additional conversational confirmation, then continue to the actual approval or mutation boundary.',
			'Approval is enforced by the host action policy after a real tool call. Approval is not missing input and must not be requested in this phase.',
			'Use decision=tool_required only when a tool action is necessary but no executable tool call can be emitted.',
			'Always provide the semantic intent, confidence, candidate tool names, and a short reason in the control call.',
			'Never claim that an action was executed unless a real tool call is emitted and later succeeds. Distinguish requested, awaiting approval, approved, attempted, succeeded, and verified. A successful result supports only what its output actually establishes.',
			'If a requested action remains incomplete after a failed, rejected, or unsuccessful attempt, preserve the original user intent and continue the available workflow instead of asking whether the same action is still wanted, unless new approval, genuinely missing input, or a changed action requires it.',
			'Do not generalize an example into a definition, one result into a complete set, or a partial observation into a verified conclusion. Continue with materially useful follow-up or verification calls when evidence is incomplete.',
			'A tool error is evidence about the failed call. Before retrying, identify what the error says about the previous arguments or operation and materially change the next attempt. For validation, schema, field, syntax, or unsupported-operation errors, adapt to the tool contract or use available discovery, schema, help, or inspection tools. Do not repeat an equivalent failed call with cosmetic wording changes.',
			'If authoritative tool observations materially conflict, investigate the contradiction before making a definitive claim when the conflict matters to the request. Prefer fresh authoritative evidence over earlier assistant statements.',
			'Avoid equivalent repeated calls. Rephrasing a read request without a concrete reason to expect new evidence is not progress.',
			'When a tool result reports unavailable data, missing indexing, unsupported scope, uncertainty, or another limitation, preserve and explain that limitation in the final user-facing answer instead of silently omitting it.'
		]);
		if ($continuationHint !== '') {
			$instruction .= "\n\n" . $continuationHint;
		}
		return $instruction;
	}

	private function buildRepairInstruction(): string {
		return implode("\n", [
			'The previous tool-decision response did not produce a reliable executable or terminal decision.',
			'Re-evaluate the current user request using the complete conversation, accumulated observations, and the available tools. Previous assistant statements may resolve references but are not factual evidence for current state or successful actions.',
			'If a tool is required and its arguments can be determined, call the real tool now. Follow prerequisite tool steps instead of guessing missing tool-owned values.',
			'If the previous attempt failed because of a tool validation, schema, field, syntax, or unsupported-operation error, do not repeat an equivalent call. Use the error as evidence and materially correct the next attempt according to the tool contract.',
			'When the user already requested an action, continue safe prerequisite reads and lookups without another conversational confirmation. Approval is handled only at the actual host approval boundary.',
			'Do not repair an unresolved decision by declaring completion while a concrete material evidence or action gap remains.',
			'If a required tool argument cannot be established from the conversation or any available prerequisite tool and must come from the user, use decision=clarification_required and list the missing required argument names.',
			'Approval is handled by the host action policy after a real tool call and is not a clarification reason.',
			'Otherwise call ' . self::CONTROL_TOOL_NAME . ' with decision=complete.',
			'Do not produce a user-facing answer and do not claim that any state change already happened.',
			'Preserve tool-reported limitations such as unavailable data, missing indexing, unsupported scope, or uncertainty for the final user-facing answer.'
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
						'missing_arguments' => ['type' => 'array', 'items' => ['type' => 'string']],
						'reason' => ['type' => 'string'],
						'clarification' => ['type' => 'string']
					],
					'required' => ['decision', 'intent', 'confidence', 'candidate_tools', 'reason']
				]
			]
		];
	}
}
