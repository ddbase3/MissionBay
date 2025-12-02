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
 * Phase 1: tool-calling (non-stream) — stream is already opened and emits tool events.
 * Phase 2: final assistant answer via token streaming (same stream).
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

		// Put stream into context so tools can push UI events directly (canvas.open/render/close/etc.)
		// Key is intentionally simple/internal: "eventstream"
		$context->setVar('eventstream', $stream);

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
			foreach ($this->safeLoadHistory($memory, $nodeId) as $entry) {
				if (!isset($entry['role'])) {
					continue;
				}
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
			$this->safeAppendHistory($memory, $nodeId, $userMessage);
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

		$this->log('Number of Tools: ' . count($toolDefs) . '.');

		// ----------------------------------------------------
		// PHASE 1 — TOOL CALLING
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

			foreach ($memories as $memory) {
				$this->safeAppendHistory($memory, $nodeId, $assistant);
			}

			if (!empty($assistant['tool_calls'])) {

				$messages[] = $assistant;

				foreach ($assistant['tool_calls'] as $call) {

					$toolName = $call['function']['name'] ?? '';
					$args     = json_decode($call['function']['arguments'] ?? '{}', true) ?? [];

					// ------------------------------------------------------------------
					// LABEL EXTRACTION DIRECTLY FROM THE TOOL
					// ------------------------------------------------------------------
					$label = $toolName;
					$toolObj = $this->findTool($tools, $toolName);
					if ($toolObj) {
						foreach ($toolObj->getToolDefinitions() as $def) {
							if (($def['function']['name'] ?? '') === $toolName) {
								$label = $def['label'] ?? $toolName;
								break;
							}
						}
					}

					// Notify UI
					$stream->push('tool.started', [
						'tool'  => $toolName,
						'label' => $label,
						'args'  => $args
					]);

					if ($toolObj) {
						$res = $toolObj->callTool($toolName, $args, $context);

						$stream->push('tool.finished', [
							'tool'  => $toolName,
							'label' => $label
						]);

						$toolMsg = [
							'role'         => 'tool',
							'tool_call_id' => $call['id'],
							'content'      => json_encode($res)
						];

						$messages[] = $toolMsg;

						foreach ($memories as $memory) {
							$this->safeAppendHistory($memory, $nodeId, $toolMsg);
						}

					} else {
						$this->log("[WARN] Tool not found: $toolName");
					}
				}

				continue;
			}

			break;
		}

		// ----------------------------------------------------
		// PHASE 2 — FINAL STREAMING RESPONSE
		// ----------------------------------------------------

		$finalContent = '';

		$model->stream(
			$messages,
			[],
			function (string $delta) use ($stream, &$finalContent) {
				if ($stream->isDisconnected()) {
					return;
				}
				$finalContent .= $delta;
				$stream->push('token', ['text' => $delta]);
			},
			function (array $meta) use ($stream) {
				if ($stream->isDisconnected()) {
					return;
				}
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
			$this->safeAppendHistory($memory, $nodeId, $assistantMessage);
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

	private function safeLoadHistory(IAgentMemory $memory, string $nodeId): array {
		try {
			return $memory->loadNodeHistory($nodeId) ?? [];
		} catch (\Throwable $e) {
			$this->logError('Memory loadNodeHistory failed: ' . $e->getMessage());
			return [];
		}
	}

	private function safeAppendHistory(IAgentMemory $memory, string $nodeId, array $message): void {
		try {
			$memory->appendNodeHistory($nodeId, $message);
		} catch (\Throwable $e) {
			$this->logError('Memory appendNodeHistory failed: ' . $e->getMessage());
		}
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
