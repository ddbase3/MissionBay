<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Orchestrator\Decision;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentStageResult;
use MissionBay\Api\IAgentModelDecisionStrategy;
use MissionBay\Dto\Assistant\AgentExecutionLedger;
use MissionBay\Exception\AgentRunCancelledException;
use MissionBay\Dto\Orchestrator\AgentModelDecisionAssessment;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;

/**
 * Uses the provider-native streaming and tool-calling contract.
 *
 * Tool calls continue through the normal MissionBay stage pipeline. A response
 * without tool calls is the visible final answer and is not regenerated.
 */
final class NativeAgentModelDecisionStrategy extends AbstractAgentModelDecisionStrategy implements IAgentModelDecisionStrategy {

	private AgentToolDefinitionSemantics $toolDefinitionSemantics;

	public function __construct(?AgentToolDefinitionSemantics $toolDefinitionSemantics = null) {
		$this->toolDefinitionSemantics = $toolDefinitionSemantics ?? new AgentToolDefinitionSemantics();
	}

	public static function getName(): string {
		return AgentModelDecisionConfig::STRATEGY_NATIVE;
	}

	public function decide(IAgentContext $context, AgentModelDecisionConfig $config): AgentStageResult {
		try {
			$runtime = $this->readRuntime($context);
		}
		catch (\Throwable $e) {
			return $this->failure('stage_runtime_error', $e->getMessage(), []);
		}

		$this->log($runtime['logger'], 'Tool phase iteration ' . $runtime['iteration'] . ' started with native streaming model decision.');
		$messages = $this->buildMessages(
			$runtime['messages'],
			$this->buildDecisionInstruction($runtime['tool_definitions'])
		);
		$ledgerInstruction = $this->buildExecutionLedgerInstruction($context, $runtime['mutation_tool_names']);
		if ($ledgerInstruction !== '') {
			$messages = $this->buildMessages($messages, $ledgerInstruction);
		}
		if ($runtime['continuation_hint'] !== '') {
			$messages = $this->buildMessages($messages, $runtime['continuation_hint']);
		}
		$modelToolDefinitions = $this->buildModelToolDefinitions($runtime['tool_definitions']);

		$receivedContent = '';
		$publishedContent = '';
		$toolCallObserved = false;
		$liveDelivery = $this->canStreamTerminalContent($context, $runtime['event_callback'], $runtime['mutation_tool_names']);
		$delivery = $liveDelivery
			? AgentToolLoopContextKeys::FINAL_OUTPUT_DELIVERY_STREAMED
			: AgentToolLoopContextKeys::FINAL_OUTPUT_DELIVERY_BUFFERED;

		if ($this->isCancellationRequested($context)) {
			return $this->cancelledResult($runtime['model_results'], $publishedContent, $delivery);
		}

		try {
			$result = $this->streamModel(
				$runtime['model'],
				$messages,
				$modelToolDefinitions,
				function(string $delta) use (&$receivedContent, &$publishedContent, &$toolCallObserved, $liveDelivery, $runtime, $context): void {
					if ($this->isCancellationRequested($context)) {
						throw new AgentRunCancelledException();
					}
					$receivedContent .= $delta;
					if (!$liveDelivery || $toolCallObserved) {
						return;
					}
					$publishedContent .= $delta;
					$this->emitEvent($runtime['event_callback'], 'token', ['text' => $delta]);
				},
				function(array $meta) use (&$toolCallObserved, $runtime, $context): void {
					if ($this->isCancellationRequested($context)) {
						throw new AgentRunCancelledException();
					}
					$toolCallObserved = $toolCallObserved || $this->containsToolCallMetadata($meta);
					$this->emitEvent($runtime['event_callback'], 'meta', $meta);
				},
				$runtime['model_results']
			);
		}
		catch (AgentRunCancelledException $e) {
			return $this->cancelledResult($runtime['model_results'], $publishedContent, $delivery);
		}
		catch (\Throwable $e) {
			if ($this->isCancellationRequested($context)) {
				return $this->cancelledResult($runtime['model_results'], $publishedContent, $delivery);
			}
			$this->logError($runtime['logger'], 'Native model stream failed: ' . $e->getMessage());

			if ($publishedContent !== '') {
				$this->emitEvent($runtime['event_callback'], 'meta', [
					'event' => 'native_stream_interrupted',
					'text_length' => strlen($publishedContent),
					'error_type' => get_class($e),
					'error_message' => $e->getMessage()
				]);

				return AgentStageResult::patch([
					AgentToolLoopContextKeys::MODEL_RESULTS => $runtime['model_results'],
					AgentToolLoopContextKeys::FINAL_OUTPUT_CONTENT => $publishedContent,
					AgentToolLoopContextKeys::FINAL_OUTPUT_DELIVERY => AgentToolLoopContextKeys::FINAL_OUTPUT_DELIVERY_STREAMED,
					AgentToolLoopContextKeys::FAILURE_CODE => 'native_stream_interrupted',
					AgentToolLoopContextKeys::FAILURE_MESSAGE => 'Native model streaming was interrupted after visible output had already been delivered.',
					AgentToolLoopContextKeys::FAILURE_DETAIL => [
						'type' => get_class($e),
						'message' => $e->getMessage(),
						'code' => $e->getCode(),
						'text_length' => strlen($publishedContent)
					],
					AgentToolLoopContextKeys::COMPLETED => false,
					AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FAILED
				]);
			}

			return $this->recoverModelFailure($context, $e, $runtime['model_results']);
		}

		if ($this->isCancellationRequested($context)) {
			return $this->cancelledResult($runtime['model_results'], $publishedContent, $delivery);
		}

		$toolCalls = $result->getToolCalls();
		if ($toolCalls !== []) {
			if (trim($publishedContent) !== '') {
				$this->log(
					$runtime['logger'],
					'Native model stream emitted ' . strlen($publishedContent) . ' byte(s) of visible progress before returning tool calls. Continuing the tool phase.'
				);
			}

			$assessment = AgentModelDecisionAssessment::toolCall(
				$this->getToolNames($toolCalls),
				false,
				$runtime['mutation_tool_names']
			);

			return $this->toolCallResult(
				$context,
				$runtime['messages'],
				$result,
				$toolCalls,
				$runtime['model_results'],
				$assessment
			);
		}

		$content = $receivedContent !== '' ? $receivedContent : $result->getContent();
		if (trim($content) === '') {
			$this->logError($runtime['logger'], 'Native model decision returned neither tool calls nor visible assistant content.');
			return $this->failure(
				'native_model_decision_empty',
				'Native model decision returned neither tool calls nor visible assistant content.',
				[
					'iteration' => $runtime['iteration'],
					'strategy' => self::getName()
				]
			);
		}

		if ($liveDelivery && $publishedContent === '') {
			$publishedContent = $content;
			$this->emitEvent($runtime['event_callback'], 'token', ['text' => $content]);
		}

		$assessment = new AgentModelDecisionAssessment(
			AgentModelDecisionAssessment::DECISION_COMPLETE,
			AgentModelDecisionAssessment::INTENT_UNKNOWN,
			1.0,
			[],
			'The native model response contained no tool calls and is reused as the final assistant answer.'
		);
		$this->log(
			$runtime['logger'],
			'Native tool phase completed after ' . $runtime['iteration'] . ' iteration(s). Terminal assistant content is reused with ' . $delivery . ' delivery.'
		);

		return $this->completeResult(
			context: $context,
			result: $result,
			modelResults: $runtime['model_results'],
			assessment: $assessment,
			finalOutputContent: $content,
			finalOutputDelivery: $delivery
		);
	}


	/** @param array<int,array<string,mixed>> $modelResults */
	private function cancelledResult(array $modelResults, string $publishedContent, string $delivery): AgentStageResult {
		return AgentStageResult::patch([
			AgentToolLoopContextKeys::MODEL_RESULTS => $modelResults,
			AgentToolLoopContextKeys::FINAL_OUTPUT_CONTENT => $publishedContent,
			AgentToolLoopContextKeys::FINAL_OUTPUT_DELIVERY => $publishedContent !== ''
				? $delivery
				: AgentToolLoopContextKeys::FINAL_OUTPUT_DELIVERY_NONE,
			AgentToolLoopContextKeys::PENDING_TOOL_CALLS => [],
			AgentToolLoopContextKeys::EXECUTION_STATUS => AgentExecutionStatus::CANCELLED,
			AgentToolLoopContextKeys::SUSPENDED => false,
			AgentToolLoopContextKeys::INTERACTION_REQUESTS => [],
			AgentToolLoopContextKeys::RESUME_HANDLE => '',
			AgentToolLoopContextKeys::FINAL_RESPONSE_MODE => AgentToolLoopContextKeys::FINAL_RESPONSE_NONE,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::FAILURE_CODE => '',
			AgentToolLoopContextKeys::FAILURE_MESSAGE => '',
			AgentToolLoopContextKeys::FAILURE_DETAIL => []
		]);
	}

	private function isCancellationRequested(IAgentContext $context): bool {
		$sink = $context->getVar(IAgentEventSink::CONTEXT_KEY);

		return $sink instanceof IAgentEventSink && $sink->isCancelled();
	}

	/** @param array<int,array<string,mixed>> $toolDefinitions */
	private function buildDecisionInstruction(array $toolDefinitions): string {
		$guidelines = [
			'<BASE3-TOOL-GUIDELINES>',
			'Use only tool names that are actually registered for this turn. Never invent, translate, alias or guess a tool name.',
			'Treat each registered tool description, schema, returned identifier, constraint, limitation, and explicit next-step instruction as an authoritative runtime contract.',
			'The registered tools are the current per-iteration capability selection, not proof of the complete agent catalog. Do not tell the user that a named capability is globally unavailable merely because it is absent from this iteration. Claim unavailability only when the runtime or authoritative tool evidence establishes it.',
			'Use previous assistant messages only for conversational continuity. They are not factual evidence and never prove current runtime state, verification, or successful execution.',
			'When the user asks to check, verify, re-check, or confirm current state and a matching authoritative read tool is registered, call it even if an earlier assistant message already stated a value. Resolve short or ambiguous follow-ups against the immediate active topic before inventing a new domain or entity.',
			'When the user explicitly requests an action and a matching registered tool exists, call that tool as soon as its required arguments are established. If prerequisite information must be discovered first, obtain it with the available tools and then continue the dependent sequence.',
			'For tools that require approval, do not ask for confirmation in natural language. Call the tool once. The host application will pause execution and display physical approval and cancel controls to the user.',
			'Do not claim that a mutation was completed before the tool returns a successful result. Distinguish requested, awaiting approval, approved, attempted, succeeded, and verified actions. A successful result proves only what that result actually establishes; do not invent a post-condition.',
			'If the user already requested an action and it remains incomplete after a failed, rejected, or unsuccessful attempt, keep that action intent active and continue the available workflow. Do not ask whether the user still wants the same action unless new approval, missing user input, or a changed action requires it.',
			'When a material factual claim can be established by a matching registered read tool, use that tool rather than guessing or filling the gap from model knowledge.',
			'A relevant or plausible result is not automatically sufficient. Do not generalize an example into a definition, one result into a complete set, or a partial observation into a verified conclusion.',
			'Continue with materially useful dependent or verification calls while a concrete gap remains and another available call is reasonably expected to resolve it. Do not stop at the first plausible result.',
			'A tool error is evidence about the failed call. Before retrying, identify what the error says about the previous arguments or operation and materially change the next attempt. For validation, schema, field, syntax, or unsupported-operation errors, adapt to the tool contract or use available discovery, schema, help, or inspection tools. Do not repeat an equivalent failed call with cosmetic wording changes.',
			'If authoritative tool observations materially conflict, investigate the contradiction before making a definitive claim when the conflict matters to the request. Prefer fresh authoritative evidence over earlier assistant statements.',
			'Avoid equivalent repeated calls. A dependent follow-up or materially different verification call is not repetition, but rephrasing a read request without a concrete reason to expect new evidence is not progress.',
			'If a required value cannot be established from the conversation or any registered tool and must come from the user, ask for that value rather than guessing. If no registered tool can establish a required fact or perform a requested action, say so clearly instead of fabricating a result or tool call.',
			'When a tool result reports unavailable data, missing indexing, unsupported scope, uncertainty, or another limitation, preserve and explain that limitation in the final answer instead of silently omitting it.',
			'When a tool is required, you may emit brief plain-language progress text before the tool call. Do not start structured renderer blocks, JSON payloads, tables, charts or other final-answer formatting before required tools have returned.',
			'After progress text, emit the tool call without further narration. Never include confirmation questions, approval requests or completion claims in a tool-call turn. For approval-bound actions, progress text must not imply that approval was granted or that the action already ran.',
			'A normal assistant response without tool calls ends the orchestration and is shown directly to the user. End only when the requested scope is sufficiently supported, requested actions have verified successful results, required user input is genuinely missing, or the available tools establish that the remaining gap cannot be resolved. Normal conversation that does not require a tool remains valid without a tool call.'
		];

		$approvalTools = $this->getApprovalToolGuidelines($toolDefinitions);
		if ($approvalTools !== []) {
			$guidelines[] = 'Registered approval-bound tools for this turn:';
			$guidelines = array_merge($guidelines, $approvalTools);
		}
		$guidelines[] = '</BASE3-TOOL-GUIDELINES>';

		return implode("\n", $guidelines);
	}

	/** @param array<int,array<string,mixed>> $toolDefinitions @return array<int,string> */
	private function getApprovalToolGuidelines(array $toolDefinitions): array {
		$result = [];
		foreach ($toolDefinitions as $definition) {
			if (!is_array($definition) || !$this->toolDefinitionSemantics->requiresApprovalDefinition($definition)) {
				continue;
			}

			$name = $this->toolDefinitionSemantics->getToolName($definition);
			if ($name === '') {
				continue;
			}

			$description = $this->getToolDescription($definition);
			$line = '- `' . $name . '`';
			if ($description !== '') {
				$line .= ': ' . $description;
			}
			$result[] = $line;
		}

		return $result;
	}

	/** @param array<int,array<string,mixed>> $toolDefinitions @return array<int,array<string,mixed>> */
	private function buildModelToolDefinitions(array $toolDefinitions): array {
		$result = [];
		foreach ($toolDefinitions as $definition) {
			if (!is_array($definition)) {
				continue;
			}

			if ($this->toolDefinitionSemantics->requiresApprovalDefinition($definition)) {
				$function = is_array($definition['function'] ?? null)
					? $definition['function']
					: $definition;
				$description = trim((string)($function['description'] ?? ''));
				$approvalInstruction = implode(' ', [
					'Host approval is handled after this function call.',
					'When the user requests this action and the required arguments are available, call this function immediately.',
					'Do not ask for confirmation in natural language.'
				]);
				$function['description'] = trim($approvalInstruction . ($description !== '' ? ' ' . $description : ''));

				if (is_array($definition['function'] ?? null)) {
					$definition['function'] = $function;
				}
				else {
					$definition = $function;
				}
			}

			$result[] = $definition;
		}

		return $result;
	}

	/** @param array<string,mixed> $definition */
	private function getToolDescription(array $definition): string {
		$function = is_array($definition['function'] ?? null)
			? $definition['function']
			: $definition;

		return trim((string)($function['description'] ?? ''));
	}

	/** @param array<string,mixed> $metadata */
	private function containsToolCallMetadata(array $metadata): bool {
		$event = strtolower(trim((string)($metadata['event'] ?? '')));
		if (in_array($event, ['toolcall', 'tool_call', 'tool_calls', 'function_call'], true)) {
			return true;
		}

		return is_array($metadata['tool_calls'] ?? null) && $metadata['tool_calls'] !== [];
	}

	/** @param array<int,string> $mutationToolNames */
	private function buildExecutionLedgerInstruction(IAgentContext $context, array $mutationToolNames): string {
		$toolResults = [];
		foreach ([AgentToolLoopContextKeys::OBSERVATIONS, AgentToolLoopContextKeys::TOOL_RESULTS] as $key) {
			$values = $context->getVar($key);
			if (is_array($values)) {
				$toolResults = array_merge($toolResults, $values);
			}
		}
		$assessments = $context->getVar(AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS);
		$assessments = is_array($assessments) ? $assessments : [];
		if ($toolResults === [] && $assessments === []) {
			return '';
		}

		return AgentExecutionLedger::fromEvidence(
			$mutationToolNames,
			$toolResults,
			$assessments
		)->buildFinalResponseInstruction();
	}

	/** @param array<int,string> $mutationToolNames */
	private function canStreamTerminalContent(IAgentContext $context, mixed $eventCallback, array $mutationToolNames): bool {
		if (!is_callable($eventCallback)) {
			return false;
		}

		return !$this->requiresBufferedMutationGuard($context, $mutationToolNames);
	}

	/** @param array<int,string> $mutationToolNames */
	private function requiresBufferedMutationGuard(IAgentContext $context, array $mutationToolNames): bool {
		$toolResults = [];
		foreach ([AgentToolLoopContextKeys::OBSERVATIONS, AgentToolLoopContextKeys::TOOL_RESULTS] as $key) {
			$values = $context->getVar($key);
			if (is_array($values)) {
				$toolResults = array_merge($toolResults, $values);
			}
		}
		$assessments = $context->getVar(AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS);
		$ledger = AgentExecutionLedger::fromEvidence(
			$mutationToolNames,
			$toolResults,
			is_array($assessments) ? $assessments : []
		);

		return $ledger->requiresBufferedStreaming();
	}

}
