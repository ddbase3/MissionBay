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
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentTool;
use MissionBay\Event\MissionBayToolFailedEvent;
use MissionBay\Event\MissionBayToolFinishedEvent;
use MissionBay\Event\MissionBayToolStartedEvent;

/**
 * AgentToolExecutionStage
 *
 * Executes the tool calls produced by the preceding model decision stage
 * and appends every tool result to the working message stack.
 */
final class AgentToolExecutionStage implements IAgentStage {

	public function __construct(
		private readonly ?IEventManager $eventManager = null,
		private readonly string $id = 'tool-execution',
		private readonly string $stageName = 'tool-execution'
	) {}

	public static function getName(): string {
		return 'agenttoolexecutionstage';
	}

	public function id(): string {
		return $this->id;
	}

	public function name(): string {
		return $this->stageName;
	}

	public function supports(IAgentContext $context): bool {
		$toolCalls = $context->getVar(AgentToolLoopContextKeys::PENDING_TOOL_CALLS);

		return $context->getVar(AgentToolLoopContextKeys::PHASE) === AgentToolLoopContextKeys::PHASE_TOOLS
			&& is_array($toolCalls)
			&& $toolCalls !== []
			&& $context->getVar(AgentToolLoopContextKeys::COMPLETED) !== true
			&& (string)($context->getVar(AgentToolLoopContextKeys::FAILURE_CODE) ?? '') === '';
	}

	public function process(IAgentContext $context): AgentStageResult {
		$toolCalls = $context->getVar(AgentToolLoopContextKeys::PENDING_TOOL_CALLS);
		$tools = $context->getVar(AgentToolLoopContextKeys::TOOLS);
		$messages = $context->getVar(AgentToolLoopContextKeys::MESSAGES);
		$executedToolCalls = $context->getVar(AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS);
		$eventCallback = $context->getVar(AgentToolLoopContextKeys::EVENT_CALLBACK);
		$iteration = (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0);
		$callIndex = (int)($context->getVar(AgentToolLoopContextKeys::CALL_INDEX) ?? 0);
		$nodeId = (string)($context->getVar(AgentToolLoopContextKeys::NODE_ID) ?? '');
		$trace = $context->getVar(AgentToolLoopContextKeys::TRACE);
		$logger = $context->getVar(AgentToolLoopContextKeys::LOGGER);

		if (!is_array($toolCalls)) {
			$toolCalls = [];
		}

		if (!is_array($tools)) {
			$tools = [];
		}

		if (!is_array($messages)) {
			$messages = [];
		}

		if (!is_array($executedToolCalls)) {
			$executedToolCalls = [];
		}

		if (!is_callable($eventCallback)) {
			$eventCallback = null;
		}

		if (!is_array($trace)) {
			$trace = [];
		}

		foreach ($toolCalls as $call) {
			$callIndex++;

			$this->handleToolCall(
				$call,
				$tools,
				$messages,
				$context,
				$eventCallback,
				$iteration,
				$callIndex,
				$executedToolCalls,
				$nodeId,
				$trace,
				$logger
			);
		}

		return AgentStageResult::patch([
			AgentToolLoopContextKeys::MESSAGES => $messages,
			AgentToolLoopContextKeys::PENDING_TOOL_CALLS => [],
			AgentToolLoopContextKeys::EXECUTED_TOOL_CALLS => $executedToolCalls,
			AgentToolLoopContextKeys::CALL_INDEX => $callIndex,
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_AFTER_TOOLS
		]);
	}

	/**
	 * @param array<string,mixed> $call
	 * @param array<int,mixed> $tools
	 * @param array<int,array<string,mixed>> $messages
	 * @param array<int,array<string,mixed>> $executedToolCalls
	 * @param ?callable $eventCallback
	 * @param array<string,mixed> $trace
	 */
	private function handleToolCall(
		array $call,
		array $tools,
		array &$messages,
		IAgentContext $context,
		?callable $eventCallback,
		int $iteration,
		int $callIndex,
		array &$executedToolCalls,
		string $nodeId,
		array $trace,
		mixed $logger
	): void {
		$callId = (string)($call['id'] ?? uniqid('toolcall_', true));
		$toolName = (string)($call['function']['name'] ?? '');
		$args = $this->decodeArguments($call['function']['arguments'] ?? '{}');

		$label = $toolName;
		$toolObj = $this->findTool($tools, $toolName, $logger);

		if ($toolObj instanceof IAgentTool) {
			try {
				foreach ($toolObj->getToolDefinitions() as $def) {
					if (($def['function']['name'] ?? '') === $toolName) {
						$label = $def['label'] ?? $toolName;
						break;
					}
				}
			} catch (\Throwable $e) {
				$this->logError($logger, 'Reading tool definitions failed (' . $toolName . '): ' . $e->getMessage());
			}
		}

		$this->emitEvent($eventCallback, 'tool.started', $this->buildUiPayload([
			'call_id' => $callId,
			'tool' => $toolName,
			'label' => $label,
			'args' => $args,
			'iteration' => $iteration,
			'call_index' => $callIndex
		], $trace), $logger);

		$this->fireToolStartedEvent(
			$nodeId,
			$callId,
			$toolName,
			$label,
			$args,
			$iteration,
			$callIndex,
			$trace,
			$logger
		);

		$this->log($logger, 'Tool started: ' . $toolName . ' [' . $callId . ']');

		if (!$toolObj instanceof IAgentTool) {
			$warn = 'Tool not found: ' . $toolName;
			$this->logError($logger, $warn);

			$executedToolCalls[] = [
				'tool' => $toolName,
				'arguments' => $args,
				'error' => $warn
			];

			$this->emitEvent($eventCallback, 'tool.error', $this->buildUiPayload([
				'call_id' => $callId,
				'tool' => $toolName,
				'label' => $label,
				'args' => $args,
				'error' => $warn,
				'iteration' => $iteration,
				'call_index' => $callIndex
			], $trace), $logger);

			$this->fireToolFailedEvent(
				$nodeId,
				$callId,
				$toolName,
				$label,
				$args,
				$warn,
				\RuntimeException::class,
				0,
				$iteration,
				$callIndex,
				$trace,
				$logger
			);

			$messages[] = [
				'role' => 'tool',
				'tool_call_id' => $callId,
				'content' => $this->encodeContent([
					'ok' => false,
					'error_code' => 'tool_not_found',
					'error' => $warn
				])
			];

			return;
		}

		try {
			$result = $toolObj->callTool($toolName, $args, $context);

			$executedToolCalls[] = [
				'tool' => $toolName,
				'arguments' => $args,
				'result' => $result
			];

			$this->emitEvent($eventCallback, 'tool.finished', $this->buildUiPayload([
				'call_id' => $callId,
				'tool' => $toolName,
				'label' => $label,
				'args' => $args,
				'result' => $result,
				'iteration' => $iteration,
				'call_index' => $callIndex
			], $trace), $logger);

			$this->fireToolFinishedEvent(
				$nodeId,
				$callId,
				$toolName,
				$label,
				$args,
				$result,
				$iteration,
				$callIndex,
				$trace,
				$logger
			);

			$this->log($logger, 'Tool finished: ' . $toolName . ' [' . $callId . ']');

			$messages[] = [
				'role' => 'tool',
				'tool_call_id' => $callId,
				'content' => $this->encodeContent($result)
			];

		} catch (\Throwable $e) {
			$this->logError($logger, 'Tool failed (' . $toolName . '): ' . $e->getMessage());

			$errorResult = [
				'ok' => false,
				'error_code' => 'tool_exception',
				'error' => $e->getMessage(),
				'type' => get_class($e),
				'code' => $e->getCode()
			];

			$executedToolCalls[] = [
				'tool' => $toolName,
				'arguments' => $args,
				'error' => $e->getMessage(),
				'type' => get_class($e),
				'code' => $e->getCode()
			];

			$this->emitEvent($eventCallback, 'tool.error', $this->buildUiPayload([
				'call_id' => $callId,
				'tool' => $toolName,
				'label' => $label,
				'args' => $args,
				'error' => $e->getMessage(),
				'type' => get_class($e),
				'code' => $e->getCode(),
				'iteration' => $iteration,
				'call_index' => $callIndex
			], $trace), $logger);

			$this->fireToolFailedEvent(
				$nodeId,
				$callId,
				$toolName,
				$label,
				$args,
				$e->getMessage(),
				get_class($e),
				$e->getCode(),
				$iteration,
				$callIndex,
				$trace,
				$logger
			);

			$messages[] = [
				'role' => 'tool',
				'tool_call_id' => $callId,
				'content' => $this->encodeContent($errorResult)
			];
		}
	}

	/**
	 * @param array<int,mixed> $tools
	 */
	private function findTool(array $tools, string $name, mixed $logger): ?IAgentTool {
		foreach ($tools as $tool) {
			if (!$tool instanceof IAgentTool) {
				continue;
			}

			try {
				foreach ($tool->getToolDefinitions() as $def) {
					if (($def['function']['name'] ?? '') === $name) {
						return $tool;
					}
				}
			} catch (\Throwable $e) {
				$this->logError($logger, 'findTool failed while reading tool definitions: ' . $e->getMessage());
			}
		}

		return null;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function decodeArguments(mixed $rawArguments): array {
		if (is_array($rawArguments)) {
			return $rawArguments;
		}

		if (!is_string($rawArguments) || trim($rawArguments) === '') {
			return [];
		}

		$decoded = json_decode($rawArguments, true);
		return is_array($decoded) ? $decoded : [];
	}

	private function encodeContent(mixed $value): string {
		if (is_string($value)) {
			return $value;
		}

		$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			return '{}';
		}

		return $json;
	}

	/**
	 * @param array<string,mixed> $payload
	 * @param array<string,mixed> $trace
	 * @return array<string,mixed>
	 */
	private function buildUiPayload(array $payload, array $trace): array {
		$payload['turn_id'] = (string)($trace['turn_id'] ?? 'unknown_turn');
		$payload['chatbot_key'] = (string)($trace['chatbot_key'] ?? 'unknown_chatbot');

		return $payload;
	}

	private function emitEvent(?callable $eventCallback, string $event, array $payload, mixed $logger): void {
		if ($eventCallback === null) {
			return;
		}

		try {
			$eventCallback($event, $payload);
		} catch (\Throwable $e) {
			$this->logError($logger, 'Tool UI event callback failed (' . $event . '): ' . $e->getMessage());
		}
	}

	/**
	 * @param array<string,mixed> $arguments
	 * @param array<string,mixed> $trace
	 */
	private function fireToolStartedEvent(
		string $nodeId,
		string $callId,
		string $toolName,
		string $label,
		array $arguments,
		int $iteration,
		int $callIndex,
		array $trace,
		mixed $logger
	): void {
		if ($this->eventManager === null) {
			return;
		}

		try {
			$this->eventManager->fire(
				new MissionBayToolStartedEvent(
					$nodeId,
					$callId,
					$toolName,
					$label,
					$arguments,
					$iteration,
					'',
					$callIndex,
					$trace
				)
			);
		} catch (\Throwable $e) {
			$this->logError($logger, 'Tool started event failed (' . $toolName . '): ' . $e->getMessage());
		}
	}

	/**
	 * @param array<string,mixed> $arguments
	 * @param array<string,mixed> $trace
	 */
	private function fireToolFinishedEvent(
		string $nodeId,
		string $callId,
		string $toolName,
		string $label,
		array $arguments,
		mixed $result,
		int $iteration,
		int $callIndex,
		array $trace,
		mixed $logger
	): void {
		if ($this->eventManager === null) {
			return;
		}

		try {
			$this->eventManager->fire(
				new MissionBayToolFinishedEvent(
					$nodeId,
					$callId,
					$toolName,
					$label,
					$arguments,
					$result,
					$iteration,
					'',
					$callIndex,
					$trace
				)
			);
		} catch (\Throwable $e) {
			$this->logError($logger, 'Tool finished event failed (' . $toolName . '): ' . $e->getMessage());
		}
	}

	/**
	 * @param array<string,mixed> $arguments
	 * @param array<string,mixed> $trace
	 */
	private function fireToolFailedEvent(
		string $nodeId,
		string $callId,
		string $toolName,
		string $label,
		array $arguments,
		string $errorMessage,
		string $errorType,
		int|string $errorCode,
		int $iteration,
		int $callIndex,
		array $trace,
		mixed $logger
	): void {
		if ($this->eventManager === null) {
			return;
		}

		try {
			$this->eventManager->fire(
				new MissionBayToolFailedEvent(
					$nodeId,
					$callId,
					$toolName,
					$label,
					$arguments,
					$errorMessage,
					$errorType,
					$errorCode,
					$iteration,
					'',
					$callIndex,
					$trace
				)
			);
		} catch (\Throwable $e) {
			$this->logError($logger, 'Tool failed event failed (' . $toolName . '): ' . $e->getMessage());
		}
	}

	private function log(mixed $logger, string $message): void {
		if (!$logger instanceof ILogger) {
			return;
		}

		$logger->log('agenttoolorchestrator', $message);
	}

	private function logError(mixed $logger, string $message): void {
		$this->log($logger, '[ERROR] ' . $message);
	}
}
