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

namespace MissionBay\Orchestrator;

use AssistantFoundation\Api\IAiChatModel;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentTool;
use MissionBay\Event\MissionBayToolFailedEvent;
use MissionBay\Event\MissionBayToolFinishedEvent;
use MissionBay\Event\MissionBayToolStartedEvent;

/**
 * AgentToolOrchestrator
 *
 * Executes the non-stream tool phase for an agentic assistant.
 *
 * Design goals:
 * - keep one consistent working message stack for the current turn
 * - append every assistant tool-call message back into the stack
 * - append every tool result back into the stack
 * - stop when the model no longer returns tool calls
 * - keep the terminal assistant stop message separate for debugging
 * - return incomplete results instead of throwing on recoverable model/tool-loop failures
 *
 * This class is intentionally transport-neutral.
 * UI events can be emitted through an optional callback.
 */
class AgentToolOrchestrator {

	private ?ILogger $logger = null;
	private ?IEventManager $eventManager = null;

	public function __construct(?ILogger $logger = null, ?IEventManager $eventManager = null) {
		$this->logger = $logger;
		$this->eventManager = $eventManager;
	}

	/**
	 * Runs the tool orchestration loop.
	 *
	 * @param array<int,array<string,mixed>> $messages
	 * @param array<int,array<string,mixed>> $toolDefs
	 * @param array<int,mixed> $tools
	 * @param ?callable $eventCallback function(string $event, array $payload): void
	 */
	public function run(
		IAiChatModel $model,
		array $messages,
		array $toolDefs,
		array $tools,
		IAgentContext $context,
		?callable $eventCallback = null,
		int $maxLoops = 8,
		string $nodeId = ''
	): AgentToolOrchestratorResult {
		$iterations = 0;
		$callIndex = 0;
		$finalAssistantMessage = null;
		$executedToolCalls = [];
		$trace = $this->buildTrace($context);

		while ($iterations < $maxLoops) {
			$iterations++;

			$this->log('Tool phase iteration ' . $iterations . ' started.');

			try {
				$result = $model->raw($messages, $toolDefs);
			} catch (\Throwable $e) {
				$this->logError('Model raw call failed: ' . $e->getMessage());

				return new AgentToolOrchestratorResult(
					$messages,
					$finalAssistantMessage,
					false,
					$iterations,
					$executedToolCalls,
					'model_raw_error',
					'Model call failed during tool orchestration.',
					[
						'type' => get_class($e),
						'message' => $e->getMessage(),
						'code' => $e->getCode()
					]
				);
			}

			if (
				!is_array($result) ||
				!isset($result['choices'][0]['message']) ||
				!is_array($result['choices'][0]['message'])
			) {
				$this->logError('Malformed model response during tool orchestration.');

				return new AgentToolOrchestratorResult(
					$messages,
					$finalAssistantMessage,
					false,
					$iterations,
					$executedToolCalls,
					'malformed_model_response',
					'Model returned a malformed response during tool orchestration.',
					[]
				);
			}

			$assistant = $result['choices'][0]['message'];
			$toolCalls = $assistant['tool_calls'] ?? [];

			if (empty($toolCalls) || !is_array($toolCalls)) {
				$finalAssistantMessage = $assistant;
				$this->log('Tool phase completed after ' . $iterations . ' iteration(s).');

				return new AgentToolOrchestratorResult(
					$messages,
					$finalAssistantMessage,
					true,
					$iterations,
					$executedToolCalls
				);
			}

			$messages[] = $assistant;

			foreach ($toolCalls as $call) {
				$callIndex++;

				$this->handleToolCall(
					$call,
					$tools,
					$messages,
					$context,
					$eventCallback,
					$iterations,
					$callIndex,
					$executedToolCalls,
					$nodeId,
					$trace
				);
			}
		}

		$this->logError('Tool phase stopped due to max loop limit: ' . $maxLoops . '.');

		return new AgentToolOrchestratorResult(
			$messages,
			$finalAssistantMessage,
			false,
			$iterations,
			$executedToolCalls,
			'max_tool_loops',
			'Tool phase did not complete within the allowed tool-call loop limit.',
			[
				'max_loops' => $maxLoops
			]
		);
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
		array $trace
	): void {
		$callId = (string)($call['id'] ?? uniqid('toolcall_', true));
		$toolName = (string)($call['function']['name'] ?? '');
		$args = $this->decodeArguments($call['function']['arguments'] ?? '{}');

		$label = $toolName;
		$toolObj = $this->findTool($tools, $toolName);

		if ($toolObj instanceof IAgentTool) {
			try {
				foreach ($toolObj->getToolDefinitions() as $def) {
					if (($def['function']['name'] ?? '') === $toolName) {
						$label = $def['label'] ?? $toolName;
						break;
					}
				}
			} catch (\Throwable $e) {
				$this->logError('Reading tool definitions failed (' . $toolName . '): ' . $e->getMessage());
			}
		}

		$this->emitEvent($eventCallback, 'tool.started', $this->buildUiPayload([
			'call_id' => $callId,
			'tool' => $toolName,
			'label' => $label,
			'args' => $args,
			'iteration' => $iteration,
			'call_index' => $callIndex
		], $trace));

		$this->fireToolStartedEvent(
			$nodeId,
			$callId,
			$toolName,
			$label,
			$args,
			$iteration,
			$callIndex,
			$trace
		);

		$this->log('Tool started: ' . $toolName . ' [' . $callId . ']');

		if (!$toolObj instanceof IAgentTool) {
			$warn = 'Tool not found: ' . $toolName;
			$this->logError($warn);

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
			], $trace));

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
				$trace
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
			], $trace));

			$this->fireToolFinishedEvent(
				$nodeId,
				$callId,
				$toolName,
				$label,
				$args,
				$result,
				$iteration,
				$callIndex,
				$trace
			);

			$this->log('Tool finished: ' . $toolName . ' [' . $callId . ']');

			$messages[] = [
				'role' => 'tool',
				'tool_call_id' => $callId,
				'content' => $this->encodeContent($result)
			];

		} catch (\Throwable $e) {
			$this->logError('Tool failed (' . $toolName . '): ' . $e->getMessage());

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
			], $trace));

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
				$trace
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
	private function findTool(array $tools, string $name): ?IAgentTool {
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
				$this->logError('findTool failed while reading tool definitions: ' . $e->getMessage());
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
	 * @return array<string,mixed>
	 */
	private function buildTrace(IAgentContext $context): array {
		$configGroup = $this->readContextString($context, ['config_group', 'chatbot_config_group'], 'unknown_group');
		$configName = $this->readContextString($context, ['config_name', 'chatbot_config_name'], 'unknown_config');
		$chatbotKey = $this->readContextString($context, ['chatbot_key', 'chatbot_id', 'chatbot_name'], '');

		if ($chatbotKey === '') {
			$chatbotKey = $this->buildChatbotKey($configGroup, $configName);
		}

		return [
			'turn_id' => $this->readContextString($context, ['turn_id', 'chat_turn_id', 'message_id'], 'unknown_turn'),
			'chatbot_key' => $chatbotKey,
			'config_group' => $configGroup,
			'config_name' => $configName
		];
	}

	private function buildChatbotKey(string $configGroup, string $configName): string {
		if ($configGroup !== 'unknown_group' && $configName !== 'unknown_config') {
			return $configGroup . ':' . $configName;
		}

		if ($configName !== 'unknown_config') {
			return $configName;
		}

		return 'unknown_chatbot';
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

	private function emitEvent(?callable $eventCallback, string $event, array $payload): void {
		if ($eventCallback === null) {
			return;
		}

		try {
			$eventCallback($event, $payload);
		} catch (\Throwable $e) {
			$this->logError('Tool UI event callback failed (' . $event . '): ' . $e->getMessage());
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
		array $trace
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
			$this->logError('Tool started event failed (' . $toolName . '): ' . $e->getMessage());
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
		array $trace
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
			$this->logError('Tool finished event failed (' . $toolName . '): ' . $e->getMessage());
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
		array $trace
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
			$this->logError('Tool failed event failed (' . $toolName . '): ' . $e->getMessage());
		}
	}

	/**
	 * @param array<int,string> $keys
	 */
	private function readContextString(IAgentContext $context, array $keys, string $default): string {
		foreach ($keys as $key) {
			$value = $this->readContextVar($context, $key);

			if (is_scalar($value)) {
				$value = trim((string)$value);

				if ($value !== '') {
					return $value;
				}
			}
		}

		return $default;
	}

	private function readContextVar(IAgentContext $context, string $key): mixed {
		if (!method_exists($context, 'getVar')) {
			return null;
		}

		try {
			return $context->getVar($key);
		} catch (\Throwable $e) {
			return null;
		}
	}

	private function log(string $msg): void {
		if ($this->logger) {
			$this->logger->log('agenttoolorchestrator', $msg);
		}
	}

	private function logError(string $msg): void {
		$this->log('[ERROR] ' . $msg);
	}
}
