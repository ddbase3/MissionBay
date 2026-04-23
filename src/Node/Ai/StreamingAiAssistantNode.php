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

namespace MissionBay\Node\Ai;

use AssistantFoundation\Api\IAiChatModel;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use EventTransport\Api\IEventStream;
use EventTransport\Api\IEventStreamFactory;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentMemory;
use MissionBay\Api\IAgentProfileSelector;
use MissionBay\Api\IAgentTool;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;
use MissionBay\Orchestrator\AgentToolOrchestrator;
use MissionBay\Orchestrator\AgentToolOrchestratorResult;
use MissionBay\Profile\ProfilePlan;
use MissionBay\Profile\ToolDefFilter;
use MissionBay\Profile\ToolGuardAgentTool;

/**
 * StreamingAiAssistantNode
 *
 * Two-phase logic:
 * Phase 1: non-stream tool orchestration
 * Phase 2: final assistant answer via token streaming
 *
 * Important:
 * - phase 1 keeps one consistent working message stack
 * - every tool result stays in the current turn working set
 * - follow-up tool calls can therefore depend on previous tool results
 * - phase 2 receives the exact phase-1 working messages, but without tools
 * - persistent memory stores only visible dialogue messages
 */
class StreamingAiAssistantNode extends AbstractAgentNode {

	private ?ILogger $logger = null;
	private IEventStreamFactory $streamFactory;
	private IEventManager $eventManager;

	public function __construct(IEventStreamFactory $streamFactory, IEventManager $eventManager, ?string $id = null) {
		parent::__construct($id);
		$this->streamFactory = $streamFactory;
		$this->eventManager = $eventManager;
	}

	public static function getName(): string {
		return 'streamingaiassistantnode';
	}

	public function getDescription(): string {
		return 'Assistant node with non-stream tool orchestration and final streaming response.';
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
			),
			new AgentNodeDock(
				name: 'profileselector',
				description: 'Optional profile selector that returns ProfilePlans (supports multi-profile).',
				interface: IAgentProfileSelector::class,
				maxConnections: 1,
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context): array {
		$stream = null;
		$assistantId = uniqid('msg_', true);

		try {
			$model = $resources['chatmodel'][0] ?? null;
			$memories = $resources['memory'] ?? [];
			$tools = $resources['tools'] ?? [];

			if (isset($resources['logger'][0]) && $resources['logger'][0] instanceof ILogger) {
				$this->logger = $resources['logger'][0];
			}

			if (!$model instanceof IAiChatModel) {
				$err = 'Missing chat model.';
				$this->logError($err);
				return ['error' => $err];
			}

			usort($memories, fn(IAgentMemory $a, IAgentMemory $b) => $a->getPriority() <=> $b->getPriority());

			$prompt = trim((string)($inputs['prompt'] ?? ''));
			$system = trim((string)($inputs['system'] ?? 'You are a helpful assistant.'));

			if ($prompt === '') {
				$err = 'Prompt is required.';
				$this->logError($err);
				return ['error' => $err];
			}

			$stream = $this->streamFactory->createStream(
				'streamingaiassistant',
				uniqid('chat-', true)
			);

			$stream->start();

			$context->setVar('eventstream', $stream);
			$stream->push('msgid', ['id' => $assistantId]);

			$profileSelector = $resources['profileselector'][0] ?? null;
			$effectivePlan = $this->buildEffectiveProfilePlan($profileSelector, $prompt, $system, $context);

			$filter = new ToolDefFilter();
			$filtered = $filter->filter($tools, $effectivePlan);

			$toolDefs = $filtered['toolDefs'];
			$report = $filtered['report'];
			$allowedToolNames = $filtered['allowedToolNames'];

			if (!$report->isFeasible()) {
				$stream->push('profile.unavailable', [
					'message' => 'Requested profiles cannot be fulfilled due to missing tools. Falling back to default behavior.',
					'missing_required_tools' => $report->getMissingRequiredTools()
				]);

				$effectivePlan = new ProfilePlan('default');
				$filtered = $filter->filter($tools, $effectivePlan);

				$toolDefs = $filtered['toolDefs'];
				$allowedToolNames = $filtered['allowedToolNames'];
			}

			$systemAppend = $effectivePlan->getSystemAppend();
			if ($systemAppend !== null && trim($systemAppend) !== '') {
				$system = rtrim($system) . "\n\n" . trim($systemAppend);
			}

			if (is_array($allowedToolNames) && count($allowedToolNames) > 0) {
				$tools = array_map(
					fn($tool) => new ToolGuardAgentTool($tool, $allowedToolNames),
					$tools
				);
			}

			$this->log('Number of Tools: ' . count($toolDefs) . '.');

			$messages = $this->buildInitialMessages($system, $memories);

			$userMessage = $this->createUserMessage($prompt);
			$messages[] = $userMessage;

			$this->appendVisibleMessageToMemories($memories, $this->getId(), $userMessage);

			$orchestrator = new AgentToolOrchestrator($this->logger, $this->eventManager);
			$orchestrationResult = $orchestrator->run(
				$model,
				$messages,
				$toolDefs,
				$tools,
				$context,
				function (string $event, array $payload) use ($stream) {
					if ($stream->isDisconnected()) {
						return;
					}

					$stream->push($event, $payload);
				},
				8,
				$this->getId()
			);

			if (!$orchestrationResult->isCompleted()) {
				throw new \RuntimeException('Phase 1 did not complete within the allowed tool-call loop limit.');
			}

			$context->setVar('orchestrator_messages', $orchestrationResult->getMessages());
			$context->setVar('orchestrator_final_assistant', $orchestrationResult->getFinalAssistantMessage());
			$context->setVar('orchestrator_iterations', $orchestrationResult->getIterations());

			$finalContent = $this->runStreamingPhase($model, $orchestrationResult, $stream);

			$assistantMessage = [
				'id' => $assistantId,
				'role' => 'assistant',
				'content' => $finalContent,
				'timestamp' => (new \DateTimeImmutable())->format('c'),
				'feedback' => null
			];

			$this->appendVisibleMessageToMemories($memories, $this->getId(), $assistantMessage);

			return [
				'stream_ready' => true
			];

		} catch (\Throwable $e) {
			$this->logError($e->getMessage());

			if ($stream !== null && !$stream->isDisconnected()) {
				$stream->push('error', [
					'message' => $e->getMessage(),
					'user_message' => 'Fehler: ' . $e->getMessage(),
					'type' => get_class($e),
					'code' => $e->getCode(),
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				]);

				$stream->push('done', ['status' => 'error']);
			}

			return [
				'error' => $e->getMessage()
			];
		}
	}

	/**
	 * Builds the initial working messages for the current turn.
	 *
	 * Memory is treated as visible dialogue memory only.
	 * Older tool traces are intentionally filtered out here.
	 *
	 * @param array<int,IAgentMemory> $memories
	 * @return array<int,array<string,mixed>>
	 */
	private function buildInitialMessages(string $system, array $memories): array {
		$messages = [
			['role' => 'system', 'content' => $system]
		];

		$nodeId = $this->getId();

		foreach ($memories as $memory) {
			foreach ($this->safeLoadHistory($memory, $nodeId) as $entry) {
				if (!$this->isVisibleHistoryEntry($entry)) {
					continue;
				}

				$messages[] = $entry;
			}
		}

		return $messages;
	}

	/**
	 * Runs the final streaming phase.
	 *
	 * Phase 2 receives the exact phase-1 working messages.
	 * No tools are passed into the streaming call.
	 */
	private function runStreamingPhase(
		IAiChatModel $model,
		AgentToolOrchestratorResult $orchestrationResult,
		IEventStream $stream
	): string {
		$finalContent = '';
		$streamMessages = $orchestrationResult->getMessages();

		$model->stream(
			$streamMessages,
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

		return $finalContent;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function createUserMessage(string $prompt): array {
		return [
			'id' => uniqid('msg_', true),
			'role' => 'user',
			'content' => $prompt,
			'timestamp' => (new \DateTimeImmutable())->format('c'),
			'feedback' => null
		];
	}

	/**
	 * Only visible dialogue entries should be reloaded from persistent memory.
	 *
	 * Allowed:
	 * - system
	 * - user
	 * - assistant without tool_calls
	 *
	 * Rejected:
	 * - tool messages
	 * - assistant planning messages with tool_calls
	 *
	 * @param mixed $entry
	 */
	private function isVisibleHistoryEntry(mixed $entry): bool {
		if (!is_array($entry) || !isset($entry['role'])) {
			return false;
		}

		$role = (string)$entry['role'];

		if ($role === 'system' || $role === 'user') {
			return true;
		}

		if ($role !== 'assistant') {
			return false;
		}

		if (!empty($entry['tool_calls']) && is_array($entry['tool_calls'])) {
			return false;
		}

		return true;
	}

	/**
	 * @param array<int,IAgentMemory> $memories
	 * @param array<string,mixed> $message
	 */
	private function appendVisibleMessageToMemories(array $memories, string $nodeId, array $message): void {
		foreach ($memories as $memory) {
			$this->safeAppendHistory($memory, $nodeId, $message);
		}
	}

	private function buildEffectiveProfilePlan(mixed $profileSelector, string $prompt, string $system, IAgentContext $context): ProfilePlan {
		if (!$profileSelector instanceof IAgentProfileSelector) {
			return new ProfilePlan('default');
		}

		$plans = $profileSelector->selectPlans($prompt, $system, $context);
		if (count($plans) === 0) {
			return new ProfilePlan('default');
		}

		$mergedSystemAppend = [];
		$mergedAllowed = null;
		$mergedRequired = [];

		foreach ($plans as $plan) {
			if (!$plan instanceof ProfilePlan) {
				continue;
			}

			$append = $plan->getSystemAppend();
			if ($append !== null && trim($append) !== '') {
				$mergedSystemAppend[] = trim($append);
			}

			$allowed = $plan->getAllowedTools();
			if (is_array($allowed)) {
				if ($mergedAllowed === null) {
					$mergedAllowed = [];
				}

				foreach ($allowed as $name) {
					$name = (string)$name;
					if ($name !== '') {
						$mergedAllowed[$name] = true;
					}
				}
			}

			foreach ($plan->getRequiredTools() as $req) {
				$req = (string)$req;
				if ($req !== '') {
					$mergedRequired[$req] = true;
				}
			}
		}

		$effectiveAllowedTools = $mergedAllowed === null ? null : array_keys($mergedAllowed);
		if (is_array($effectiveAllowedTools)) {
			sort($effectiveAllowedTools);
		}

		$effectiveRequiredTools = array_keys($mergedRequired);
		sort($effectiveRequiredTools);

		return new ProfilePlan(
			profileName: 'effective',
			systemAppend: count($mergedSystemAppend) > 0 ? implode("\n\n", $mergedSystemAppend) : null,
			allowedTools: $effectiveAllowedTools,
			requiredTools: $effectiveRequiredTools
		);
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
