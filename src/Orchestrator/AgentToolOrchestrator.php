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
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentTool;

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
 *
 * This class is intentionally transport-neutral.
 * UI events can be emitted through an optional callback.
 */
class AgentToolOrchestrator {

	private ?ILogger $logger = null;

	public function __construct(?ILogger $logger = null) {
		$this->logger = $logger;
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
		int $maxLoops = 8
	): AgentToolOrchestratorResult {
		$iterations = 0;
		$finalAssistantMessage = null;
		$executedToolCalls = [];

		while ($iterations < $maxLoops) {
			$iterations++;

			$this->log('Tool phase iteration ' . $iterations . ' started.');

			$result = $model->raw($messages, $toolDefs);

			if (
				!is_array($result) ||
				!isset($result['choices'][0]['message']) ||
				!is_array($result['choices'][0]['message'])
			) {
				throw new \RuntimeException('Malformed model response.');
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
				$this->handleToolCall(
					$call,
					$tools,
					$messages,
					$context,
					$eventCallback,
					$iterations,
					$executedToolCalls
				);
			}
		}

		$this->logError('Tool phase stopped due to max loop limit: ' . $maxLoops . '.');

		return new AgentToolOrchestratorResult(
			$messages,
			$finalAssistantMessage,
			false,
			$iterations,
			$executedToolCalls
		);
	}

	/**
	 * @param array<string,mixed> $call
	 * @param array<int,mixed> $tools
	 * @param array<int,array<string,mixed>> $messages
	 * @param array<int,array<string,mixed>> $executedToolCalls
	 * @param ?callable $eventCallback
	 */
	private function handleToolCall(
		array $call,
		array $tools,
		array &$messages,
		IAgentContext $context,
		?callable $eventCallback,
		int $iteration,
		array &$executedToolCalls
	): void {
		$callId = (string)($call['id'] ?? uniqid('toolcall_', true));
		$toolName = (string)($call['function']['name'] ?? '');
		$args = $this->decodeArguments($call['function']['arguments'] ?? '{}');

		$label = $toolName;
		$toolObj = $this->findTool($tools, $toolName);

		if ($toolObj instanceof IAgentTool) {
			foreach ($toolObj->getToolDefinitions() as $def) {
				if (($def['function']['name'] ?? '') === $toolName) {
					$label = $def['label'] ?? $toolName;
					break;
				}
			}
		}

		$this->emitEvent($eventCallback, 'tool.started', [
			'call_id' => $callId,
			'tool' => $toolName,
			'label' => $label,
			'args' => $args,
			'iteration' => $iteration
		]);

		$this->log('Tool started: ' . $toolName . ' [' . $callId . ']');

		if (!$toolObj instanceof IAgentTool) {
			$warn = 'Tool not found: ' . $toolName;
			$this->logError($warn);

			$executedToolCalls[] = [
				'tool' => $toolName,
				'arguments' => $args,
				'error' => $warn
			];

			$this->emitEvent($eventCallback, 'tool.error', [
				'call_id' => $callId,
				'tool' => $toolName,
				'label' => $label,
				'args' => $args,
				'error' => $warn,
				'iteration' => $iteration
			]);

			$messages[] = [
				'role' => 'tool',
				'tool_call_id' => $callId,
				'content' => $this->encodeContent(['error' => $warn])
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

			$this->emitEvent($eventCallback, 'tool.finished', [
				'call_id' => $callId,
				'tool' => $toolName,
				'label' => $label,
				'args' => $args,
				'result' => $result,
				'iteration' => $iteration
			]);

			$this->log('Tool finished: ' . $toolName . ' [' . $callId . ']');

			$messages[] = [
				'role' => 'tool',
				'tool_call_id' => $callId,
				'content' => $this->encodeContent($result)
			];

		} catch (\Throwable $e) {
			$this->logError('Tool failed (' . $toolName . '): ' . $e->getMessage());

			$executedToolCalls[] = [
				'tool' => $toolName,
				'arguments' => $args,
				'error' => $e->getMessage(),
				'type' => get_class($e),
				'code' => $e->getCode()
			];

			$this->emitEvent($eventCallback, 'tool.error', [
				'call_id' => $callId,
				'tool' => $toolName,
				'label' => $label,
				'args' => $args,
				'error' => $e->getMessage(),
				'type' => get_class($e),
				'code' => $e->getCode(),
				'iteration' => $iteration
			]);

			$messages[] = [
				'role' => 'tool',
				'tool_call_id' => $callId,
				'content' => $this->encodeContent([
					'error' => $e->getMessage(),
					'type' => get_class($e),
					'code' => $e->getCode()
				])
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

			foreach ($tool->getToolDefinitions() as $def) {
				if (($def['function']['name'] ?? '') === $name) {
					return $tool;
				}
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

	private function emitEvent(?callable $eventCallback, string $event, array $payload): void {
		if ($eventCallback === null) {
			return;
		}

		$eventCallback($event, $payload);
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
