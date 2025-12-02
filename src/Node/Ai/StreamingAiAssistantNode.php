<?php declare(strict_types=1);

namespace MissionBay\Node\Ai;

use AssistantFoundation\Api\IAiChatModel;
use Base3\Logger\Api\ILogger;
use EventTransport\Api\IEventStreamFactory;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentMemory;
use MissionBay\Api\IAgentTool;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

/**
 * StreamingAiAssistantNode
 *
 * Two-phase logic:
 * Phase 1: Tool-calling (non-stream) — stream is already opened and emits tool events.
 * Phase 2: Final assistant answer via token streaming (same stream).
 *
 * Memory updates:
 * - user messages immediately
 * - assistant tool-call messages
 * - tool results
 * - final streamed assistant output
 */
class StreamingAiAssistantNode extends AbstractAgentNode {

	private ?ILogger $logger = null;
	private IEventStreamFactory $streamFactory;

	public function __construct(IEventStreamFactory $streamFactory, ?string $id = null) {
		parent::__construct($id);
		$this->streamFactory = $streamFactory;
	}

	public static function getName(): string {
		return 'streamingaiassistantnode';
	}

	public function getDescription(): string {
		return 'Assistant node with early opened stream, tool-calling events and final streaming.';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'prompt',
				description: 'User message.',
				type: 'string',
				default: null,
				required: true
			),
			new AgentNodePort(
				name: 'system',
				description: 'Optional system message.',
				type: 'string',
				default: 'You are a helpful assistant.',
				required: false
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'stream_ready',
				description: 'Indicates that the stream has been opened and is running.',
				type: 'bool',
				default: true,
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message, if any.',
				type: 'string',
				default: null,
				required: false
			)
		];
	}

	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'chatmodel',
				description: 'Chat model implementing IAiChatModel.',
				interface: IAiChatModel::class,
				maxConnections: 1,
				required: true
			),
			new AgentNodeDock(
				name: 'memory',
				description: 'Optional memory for chat history.',
				interface: IAgentMemory::class,
				maxConnections: 99,
				required: false
			),
			new AgentNodeDock(
				name: 'logger',
				description: 'Optional logger.',
				interface: ILogger::class,
				maxConnections: 1,
				required: false
			),
			new AgentNodeDock(
				name: 'tools',
				description: 'Callable tools (function calling).',
				interface: IAgentTool::class,
				maxConnections: 99,
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context): array {

		$model    = $resources['chatmodel'][0] ?? null;
		$memories = $resources['memory'] ?? [];
		$tools    = $resources['tools'] ?? [];

		if (isset($resources['logger'][0]) && $resources['logger'][0] instanceof ILogger) {
			$this->logger = $resources['logger'][0];
		}

		if (!$model) {
			$err = 'Missing chat model.';
			$this->logError($err);
			return ['error' => $this->error($err)];
		}

		usort($memories, fn(IAgentMemory $a, IAgentMemory $b) => $a->getPriority() <=> $b->getPriority());

		$prompt = trim($inputs['prompt'] ?? '');
		$system = trim($inputs['system'] ?? 'You are a helpful assistant.');

		if ($prompt === '') {
			$err = 'Prompt is required.';
			$this->logError($err);
			return ['error' => $this->error($err)];
		}

		// ----------------------------------------------------
		// OPEN STREAM IMMEDIATELY (PHASE 1 + PHASE 2)
		// ----------------------------------------------------

		$assistantId = uniqid('msg_', true);

		$stream = $this->streamFactory->createStream(
			'streamingaiassistant',
			uniqid('chat-', true)
		);

		$stream->start();

		// Let UI know the final message id before any tool events
		$stream->push('msgid', ['id' => $assistantId]);

		// ----------------------------------------------------
		// BUILD MESSAGE CONTEXT
		// ----------------------------------------------------

		$messages = [
			['role' => 'system', 'content' => $system]
		];

		$nodeId = $this->getId();

		// Load memory history
		foreach ($memories as $memory) {
			foreach ($memory->loadNodeHistory($nodeId) as $entry) {
				if (!isset($entry['role'])) continue;
				$messages[] = $entry;
			}
		}

		// Create user message
		$userMessage = [
			'id'        => uniqid('msg_', true),
			'role'      => 'user',
			'content'   => $prompt,
			'timestamp' => (new \DateTimeImmutable())->format('c'),
			'feedback'  => null
		];

		$messages[] = $userMessage;

		// Store user message immediately
		foreach ($memories as $memory) {
			$memory->appendNodeHistory($nodeId, $userMessage);
		}

		// ----------------------------------------------------
		// TOOL DEFINITIONS
		// ----------------------------------------------------

		$toolDefs = [];
		foreach ($tools as $tool) {
			foreach ($tool->getToolDefinitions() as $def) {
				$toolDefs[] = $def;
			}
		}

		// ----------------------------------------------------
		// PHASE 1 — TOOL CALLING (STREAM IS ALREADY OPEN)
		// ----------------------------------------------------

		$loopGuard = 0;
		$maxLoops  = 5;

		while ($loopGuard++ < $maxLoops) {

			$result = $model->raw($messages, $toolDefs);

			if (!isset($result['choices'][0]['message'])) {
				$err = 'Malformed model response.';
				$this->logError($err);
				return ['error' => $this->error($err)];
			}

			$assistant = $result['choices'][0]['message'];

			// Save assistant tool-call message in memory
			foreach ($memories as $memory) {
				$memory->appendNodeHistory($nodeId, $assistant);
			}

			// CASE: assistant wants to call tools
			if (!empty($assistant['tool_calls'])) {

				$messages[] = $assistant;

				foreach ($assistant['tool_calls'] as $call) {

					$toolName = $call['function']['name'] ?? '';
					$args     = json_decode($call['function']['arguments'] ?? '{}', true) ?? [];

					// Notify UI: tool started
					$stream->push('tool.started', [
						'tool' => $toolName,
						'args' => $args
					]);

					$tool = $this->findTool($tools, $toolName);

					if ($tool) {
						$res = $tool->callTool($toolName, $args, $context);

						// Notify UI: tool finished
						$stream->push('tool.finished', [
							'tool' => $toolName
						]);

						$toolMsg = [
							'role'         => 'tool',
							'tool_call_id' => $call['id'],
							'content'      => json_encode($res)
						];

						$messages[] = $toolMsg;

						// Store tool result
						foreach ($memories as $memory) {
							$memory->appendNodeHistory($nodeId, $toolMsg);
						}

					} else {
						$this->log("[WARN] Tool not found: $toolName");
					}
				}

				continue;
			}

			// CASE: No more tool calls → final answer phase begins
			break;
		}

		// ----------------------------------------------------
		// PHASE 2 — FINAL STREAMING RESPONSE (same stream)
		// ----------------------------------------------------

		$finalContent = '';

		$model->stream(
			$messages,
			[],
			function (string $delta) use ($stream, &$finalContent) {
				if ($stream->isDisconnected()) return;
				$finalContent .= $delta;
				$stream->push('token', ['text' => $delta]);
			},
			function (array $meta) use ($stream) {
				if ($stream->isDisconnected()) return;
				$stream->push('meta', $meta);
			}
		);

		if (!$stream->isDisconnected()) {
			$stream->push('done', ['status' => 'complete']);
		}

		// ----------------------------------------------------
		// SAVE FINAL ASSISTANT MESSAGE
		// ----------------------------------------------------

		$assistantMessage = [
			'id'        => $assistantId,
			'role'      => 'assistant',
			'content'   => $finalContent,
			'timestamp' => (new \DateTimeImmutable())->format('c'),
			'feedback'  => null
		];

		foreach ($memories as $memory) {
			$memory->appendNodeHistory($nodeId, $assistantMessage);
		}

		return [
			'stream_ready' => true
		];
	}

	// ----------------------------------------------------
	// UTILITIES
	// ----------------------------------------------------

	private function findTool(array $tools, string $name): ?IAgentTool {
		foreach ($tools as $tool) {
			foreach ($tool->getToolDefinitions() as $def) {
				if (($def['function']['name'] ?? '') === $name) {
					return $tool;
				}
			}
		}
		return null;
	}

	private function log(string $msg): void {
		if ($this->logger) {
			$this->logger->log(static::getName(), '[' . $this->id . '] ' . $msg);
		}
	}

	private function logError(string $msg): void {
		$this->log('[ERROR] ' . $msg);
	}
}
