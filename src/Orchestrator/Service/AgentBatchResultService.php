<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Orchestrator\Service;

use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiToolCall;

/** Aggregates normal child results into the one parent tool result expected by model protocols. */
final class AgentBatchResultService {

	/** @param array<int,AgentToolResult> $results @return array<int,AgentToolResult> */
	public function aggregate(array $results): array {
		$groups = [];
		$positions = [];
		$output = [];

		foreach ($results as $result) {
			if (!$result instanceof AgentToolResult) {
				$output[] = $result;
				continue;
			}
			$batch = $this->readBatchMetadata($result);
			$parent = is_array($batch['parent_call'] ?? null) ? $batch['parent_call'] : [];
			$parentId = trim((string)($parent['id'] ?? ''));
			if ($parentId === '') {
				$output[] = $result;
				continue;
			}
			if (!isset($groups[$parentId])) {
				$groups[$parentId] = ['parent' => $parent, 'results' => []];
				$positions[$parentId] = count($output);
				$output[] = null;
			}
			$groups[$parentId]['results'][] = $result;
		}

		foreach ($groups as $parentId => $group) {
			$parent = $group['parent'];
			$parentCall = new AiToolCall(
				$parentId,
				trim((string)($parent['name'] ?? 'execute_agent_tool_batch')),
				is_array($parent['arguments'] ?? null) ? $parent['arguments'] : []
			);
			$output[$positions[$parentId]] = $this->aggregateForParent($parentCall, $group['results']);
		}

		return array_values(array_filter($output, static fn(mixed $value): bool => $value instanceof AgentToolResult));
	}

	/** @param array<int,AgentToolResult> $results */
	public function aggregateForParent(AiToolCall $parentCall, array $results): AgentToolResult {
		$items = [];
		$succeeded = 0;
		foreach ($results as $position => $result) {
			if (!$result instanceof AgentToolResult) {
				continue;
			}
			$batch = $this->readBatchMetadata($result);
			$ok = $this->isSuccessfulResult($result);
			if ($ok) {
				$succeeded++;
			}
			$error = $this->readError($result, $ok);
			$items[] = [
				'index' => max(1, (int)($batch['index'] ?? $position + 1)),
				'label' => trim((string)($batch['label'] ?? '')),
				'call_id' => $result->getCallId(),
				'tool' => $result->getToolName(),
				'arguments' => $result->getArguments(),
				'ok' => $ok,
				'status' => $ok ? AgentToolResult::STATUS_SUCCESS : AgentToolResult::STATUS_FAILURE,
				'output' => $result->getOutput(),
				'error_code' => $error['code'],
				'error' => $error['message']
			];
		}
		usort($items, static fn(array $a, array $b): int => $a['index'] <=> $b['index']);

		$total = count($items);
		$failed = $total - $succeeded;
		$status = $failed === 0 ? 'success' : ($succeeded === 0 ? 'failed' : 'partial');
		$targetFunction = $items !== [] ? (string)$items[0]['tool'] : '';

		return AgentToolResult::success(
			$parentCall->getId(),
			$parentCall->getName(),
			$parentCall->getArguments(),
			[
				'ok' => $failed === 0,
				'status' => $status,
				'target_function' => $targetFunction,
				'total' => $total,
				'succeeded' => $succeeded,
				'failed' => $failed,
				'items' => $items
			],
			['batch' => true, 'batch_status' => $status]
		);
	}

	private function isSuccessfulResult(AgentToolResult $result): bool {
		if (!$result->isSuccess()) {
			return false;
		}

		$output = $result->getOutput();
		return !is_array($output) || !array_key_exists('ok', $output) || $output['ok'] !== false;
	}

	/** @return array{code:string,message:string} */
	private function readError(AgentToolResult $result, bool $ok): array {
		$code = trim($result->getErrorCode());
		$message = trim($result->getErrorMessage());
		if ($ok) {
			return ['code' => $code, 'message' => $message];
		}

		$output = $result->getOutput();
		if (!is_array($output)) {
			return ['code' => $code, 'message' => $message];
		}

		$error = $output['error'] ?? null;
		if ($code === '') {
			$code = trim((string)(
				$output['error_code']
				?? (is_array($error) ? ($error['code'] ?? '') : '')
			));
		}
		if ($message === '') {
			$message = trim((string)(is_array($error) ? ($error['message'] ?? '') : $error));
		}

		return ['code' => $code, 'message' => $message];
	}

	/** @return array<string,mixed> */
	private function readBatchMetadata(AgentToolResult $result): array {
		$metadata = $result->getMetadata();
		$toolCall = is_array($metadata['tool_call'] ?? null) ? $metadata['tool_call'] : [];
		if (is_array($toolCall['agent_batch'] ?? null)) {
			return $toolCall['agent_batch'];
		}

		$action = is_array($metadata['action'] ?? null) ? $metadata['action'] : [];
		$actionMetadata = is_array($action['metadata'] ?? null) ? $action['metadata'] : [];
		$toolCall = is_array($actionMetadata['tool_call'] ?? null) ? $actionMetadata['tool_call'] : [];
		return is_array($toolCall['agent_batch'] ?? null) ? $toolCall['agent_batch'] : [];
	}
}
