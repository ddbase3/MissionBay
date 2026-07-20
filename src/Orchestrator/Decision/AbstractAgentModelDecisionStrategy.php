<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Orchestrator\Decision;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentStageResult;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AiToolCall;
use Base3\Logger\Api\ILogger;
use MissionBay\Ai\AgentChatMessageAdapter;
use MissionBay\Dto\Orchestrator\AgentModelDecisionAssessment;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;

abstract class AbstractAgentModelDecisionStrategy {

	protected const TERMINAL_SIGNAL = 'TOOL_PHASE_COMPLETE';

	/** @return array{model:IAiChatModel,messages:array,tool_definitions:array,iteration:int,logger:mixed,continuation_hint:string,model_results:array,mutation_tool_names:array} */
	protected function readRuntime(IAgentContext $context): array {
		$model = $context->getVar(AgentToolLoopContextKeys::MODEL);
		if (!$model instanceof IAiChatModel) {
			throw new \RuntimeException('Model decision stage did not receive an AI chat model.');
		}
		$messages = $context->getVar(AgentToolLoopContextKeys::MESSAGES);
		$toolDefinitions = $context->getVar(AgentToolLoopContextKeys::TOOL_DEFINITIONS);
		$modelResults = $context->getVar(AgentToolLoopContextKeys::MODEL_RESULTS);
		$mutationToolNames = $context->getVar(AgentToolLoopContextKeys::MUTATION_TOOL_NAMES);
		$continuationHint = $context->getVar(AgentToolLoopContextKeys::CONTINUATION_HINT);

		return [
			'model' => $model,
			'messages' => is_array($messages) ? $messages : [],
			'tool_definitions' => is_array($toolDefinitions) ? $toolDefinitions : [],
			'iteration' => (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0),
			'logger' => $context->getVar(AgentToolLoopContextKeys::LOGGER),
			'continuation_hint' => is_scalar($continuationHint) ? trim((string)$continuationHint) : '',
			'model_results' => is_array($modelResults) ? $modelResults : [],
			'mutation_tool_names' => is_array($mutationToolNames) ? array_values(array_filter($mutationToolNames, 'is_string')) : []
		];
	}

	/** @param array<int,array<string,mixed>> $messages @param array<int,array<string,mixed>> $toolDefinitions */
	protected function callModel(IAiChatModel $model, array $messages, array $toolDefinitions, array &$modelResults): AiChatResult {
		$result = $model->complete($messages, $toolDefinitions);
		$modelResults[] = $result->getMetadata()->toArray();
		return $result;
	}

	/** @param array<int,array<string,mixed>> $messages */
	protected function buildMessages(array $messages, string $instruction): array {
		$result = $messages;
		foreach ($result as $index => $message) {
			if (
				!is_array($message)
				|| ($message['role'] ?? null) !== 'system'
				|| !is_scalar($message['content'] ?? null)
			) {
				continue;
			}
			$content = trim((string)$message['content']);
			$result[$index]['content'] = $content === '' ? $instruction : $content . "\n\n" . $instruction;
			return $result;
		}
		array_unshift($result, ['role' => 'system', 'content' => $instruction]);
		return $result;
	}

	/** @param array<int,AiToolCall> $toolCalls @return array<int,string> */
	protected function getToolNames(array $toolCalls): array {
		$result = [];
		foreach ($toolCalls as $toolCall) {
			if (!$toolCall instanceof AiToolCall) {
				continue;
			}
			$name = trim($toolCall->getName());
			if ($name !== '') {
				$result[$name] = $name;
			}
		}
		return array_values($result);
	}

	/** @param array<int,array<string,mixed>> $messages @param array<int,AiToolCall> $toolCalls @param array<int,array<string,mixed>> $modelResults @param array<int,AgentModelDecisionAssessment> $priorAssessments */
	protected function toolCallResult(IAgentContext $context, array $messages, AiChatResult $result, array $toolCalls, array $modelResults, AgentModelDecisionAssessment $assessment, array $priorAssessments = []): AgentStageResult {
		$assistantResult = new AiChatResult($result->getContent(), $toolCalls, $result->getMetadata());
		$messages[] = AgentChatMessageAdapter::assistantMessage($assistantResult);

		$existing = $context->getVar(AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS);
		$assessments = $this->appendAssessments(is_array($existing) ? $existing : [], $priorAssessments, $assessment);

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::MESSAGES => $messages,
			AgentToolLoopContextKeys::MODEL_RESULTS => $modelResults,
			AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS => $assessments,
			AgentToolLoopContextKeys::CONTINUATION_HINT => '',
			AgentToolLoopContextKeys::PENDING_TOOL_CALLS => $toolCalls,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_TOOLS
		]);
	}

	/** @param array<int,array<string,mixed>> $modelResults @param array<int,AgentModelDecisionAssessment> $priorAssessments */
	protected function completeResult(IAgentContext $context, AiChatResult $result, array $modelResults, AgentModelDecisionAssessment $assessment, string $instruction = '', array $priorAssessments = []): AgentStageResult {
		$existing = $context->getVar(AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS);
		$assessments = $this->appendAssessments(is_array($existing) ? $existing : [], $priorAssessments, $assessment);
		$assistant = AgentChatMessageAdapter::assistantMessage(new AiChatResult($result->getContent(), [], $result->getMetadata()));
		$patch = [
			AgentToolLoopContextKeys::FINAL_ASSISTANT_MESSAGE => $assistant,
			AgentToolLoopContextKeys::FINAL_RESPONSE_MODE => AgentToolLoopContextKeys::FINAL_RESPONSE_COMPLETE,
			AgentToolLoopContextKeys::MODEL_RESULTS => $modelResults,
			AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS => $assessments,
			AgentToolLoopContextKeys::CONTINUATION_HINT => '',
			AgentToolLoopContextKeys::PENDING_TOOL_CALLS => [],
			AgentToolLoopContextKeys::COMPLETED => true,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FINAL
		];
		if (trim($instruction) !== '') {
			$patch[AgentToolLoopContextKeys::FINAL_RESPONSE_INSTRUCTION] = trim($instruction);
		}
		return AgentStageResult::patch($patch);
	}

	/** @param array<int,array<string,mixed>> $existing @param array<int,AgentModelDecisionAssessment> $priorAssessments @return array<int,array<string,mixed>> */
	protected function appendAssessments(array $existing, array $priorAssessments, AgentModelDecisionAssessment $assessment): array {
		foreach ($priorAssessments as $priorAssessment) {
			if ($priorAssessment instanceof AgentModelDecisionAssessment) {
				$existing[] = $priorAssessment->toArray();
			}
		}
		$existing[] = $assessment->toArray();
		return $existing;
	}

	/** @param array<int,array<string,mixed>> $modelResults @param array<int,AgentModelDecisionAssessment> $assessments */
	protected function recoverModelFailure(IAgentContext $context, \Throwable $e, array $modelResults = [], array $assessments = []): AgentStageResult {
		$observations = $context->getVar(AgentToolLoopContextKeys::OBSERVATIONS);
		$existingAssessments = $context->getVar(AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS);
		$serializedAssessments = is_array($existingAssessments) ? $existingAssessments : [];
		foreach ($assessments as $assessment) {
			if ($assessment instanceof AgentModelDecisionAssessment) {
				$serializedAssessments[] = $assessment->toArray();
			}
		}
		if (is_array($observations) && $observations !== []) {
			return AgentStageResult::patch([
				AgentToolLoopContextKeys::MODEL_RESULTS => $modelResults,
				AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS => $serializedAssessments,
				AgentToolLoopContextKeys::FINAL_RESPONSE_MODE => AgentToolLoopContextKeys::FINAL_RESPONSE_PARTIAL,
				AgentToolLoopContextKeys::TERMINAL_EVIDENCE_READY => true,
				AgentToolLoopContextKeys::FINAL_RESPONSE_INSTRUCTION => implode("\n", [
					'The next tool-decision call failed after successful tool observations were already collected.',
					'Produce the most useful direct answer from the available observations.',
					'State uncertainty where evidence is incomplete. Do not expose internal timeout or orchestration details.'
				]),
				AgentToolLoopContextKeys::PENDING_TOOL_CALLS => [],
				AgentToolLoopContextKeys::COMPLETED => true,
				AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FINAL
			], [
				'recovered_from_model_error' => true,
				'error_type' => get_class($e),
				'error_message' => $e->getMessage(),
				'error_code' => $e->getCode()
			]);
		}
		return AgentStageResult::patch([
			AgentToolLoopContextKeys::MODEL_RESULTS => $modelResults,
			AgentToolLoopContextKeys::MODEL_DECISION_ASSESSMENTS => $serializedAssessments,
			AgentToolLoopContextKeys::FAILURE_CODE => 'model_raw_error',
			AgentToolLoopContextKeys::FAILURE_MESSAGE => 'Model call failed during tool orchestration.',
			AgentToolLoopContextKeys::FAILURE_DETAIL => [
				'type' => get_class($e),
				'message' => $e->getMessage(),
				'code' => $e->getCode()
			],
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FAILED
		]);
	}

	/** @param array<string,mixed> $detail */
	protected function failure(string $code, string $message, array $detail): AgentStageResult {
		return AgentStageResult::patch([
			AgentToolLoopContextKeys::FAILURE_CODE => $code,
			AgentToolLoopContextKeys::FAILURE_MESSAGE => $message,
			AgentToolLoopContextKeys::FAILURE_DETAIL => $detail,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_FAILED
		]);
	}

	protected function log(mixed $logger, string $message): void {
		if ($logger instanceof ILogger) {
			$logger->log('agentmodeldecision', $message);
		}
	}

	protected function logError(mixed $logger, string $message): void {
		$this->log($logger, '[ERROR] ' . $message);
	}
}
