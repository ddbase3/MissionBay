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
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentMemory;
use MissionBay\Api\IAgentProfileSelector;
use MissionBay\Api\IAgentTool;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;
use MissionBay\Orchestrator\AgentToolOrchestrator;
use MissionBay\Profile\ProfilePlan;
use MissionBay\Profile\ToolDefFilter;
use MissionBay\Profile\ToolGuardAgentTool;

class AiAssistantNode extends AbstractAgentNode {

	private ?ILogger $logger = null;

	public static function getName(): string {
		return 'aiassistantnode';
	}

	public function getDescription(): string {
		return 'Assistant node with non-stream tool orchestration and direct final response output.';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'prompt',
				description: 'The user message.',
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
			),
			new AgentNodePort(
				name: 'mode',
				description: 'Operation mode: "chat" (default) or "suggestions" (read-only memory, no tools).',
				type: 'string',
				default: 'chat',
				required: false
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'message',
				description: 'The complete assistant message object (id, role, content, timestamp, feedback).',
				type: 'array',
				default: null,
				required: false
			),
			new AgentNodePort(
				name: 'tool_calls',
				description: 'List of tool calls executed during this interaction.',
				type: 'array',
				default: [],
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
				description: 'Docked assistant chat model.',
				interface: IAiChatModel::class,
				maxConnections: 1,
				required: true
			),
			new AgentNodeDock(
				name: 'memory',
				description: 'Optional memory for storing previous messages.',
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
				description: 'Callable tools for function calling.',
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
		try {
			$model = $resources['chatmodel'][0] ?? null;
			$memories = $resources['memory'] ?? [];
			$tools = $resources['tools'] ?? [];

			if (isset($resources['logger'][0]) && $resources['logger'][0] instanceof ILogger) {
				$this->logger = $resources['logger'][0];
			}

			if (!$model instanceof IAiChatModel) {
				$err = 'Missing required chat model.';
				$this->logError($err);
				return ['error' => $err];
			}

			usort($memories, fn(IAgentMemory $a, IAgentMemory $b) => $a->getPriority() <=> $b->getPriority());

			$prompt = trim((string)($inputs['prompt'] ?? ''));
			$system = trim((string)($inputs['system'] ?? 'You are a helpful assistant.'));
			$mode = strtolower(trim((string)($inputs['mode'] ?? 'chat')));

			if ($mode === '') {
				$mode = 'chat';
			}

			$isSuggestions = ($mode === 'suggestions');
			$this->log('Mode: ' . $mode);

			if ($prompt === '') {
				$err = 'Prompt is required.';
				$this->logError($err);
				return ['error' => $err];
			}

			$toolDefs = [];

			if (!$isSuggestions) {
				$profileSelector = $resources['profileselector'][0] ?? null;
				$effectivePlan = $this->buildEffectiveProfilePlan($profileSelector, $prompt, $system, $context);

				$filter = new ToolDefFilter();
				$filtered = $filter->filter($tools, $effectivePlan);

				$toolDefs = $filtered['toolDefs'];
				$report = $filtered['report'];
				$allowedToolNames = $filtered['allowedToolNames'];

				if (!$report->isFeasible()) {
					$this->logError('Requested profiles cannot be fulfilled. Falling back to default behavior.');

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

				$this->log('Number of tools: ' . count($toolDefs) . '.');
			} else {
				$tools = [];
				$toolDefs = [];
				$this->log('Suggestions mode: tools disabled.');
			}

			$messages = $this->buildInitialMessages($system, $memories);

			$userMessage = $this->createUserMessage($prompt);
			$messages[] = $userMessage;

			if (!$isSuggestions) {
				$this->appendVisibleMessageToMemories($memories, $this->getId(), $userMessage);
			}

			$orchestrator = new AgentToolOrchestrator($this->logger);
			$orchestrationResult = $orchestrator->run(
				$model,
				$messages,
				$toolDefs,
				$tools,
				$context,
				null,
				8
			);

			if (!$orchestrationResult->isCompleted()) {
				throw new \RuntimeException('Tool phase did not complete within the allowed tool-call loop limit.');
			}

			$context->setVar('orchestrator_messages', $orchestrationResult->getMessages());
			$context->setVar('orchestrator_final_assistant', $orchestrationResult->getFinalAssistantMessage());
			$context->setVar('orchestrator_iterations', $orchestrationResult->getIterations());

			$finalAssistantMessage = $orchestrationResult->getFinalAssistantMessage();
			if (!is_array($finalAssistantMessage)) {
				throw new \RuntimeException('Missing final assistant message.');
			}

			$assistantMessage = [
				'id' => uniqid('msg_', true),
				'role' => 'assistant',
				'content' => $this->normalizeMessageContent($finalAssistantMessage['content'] ?? ''),
				'timestamp' => (new \DateTimeImmutable())->format('c'),
				'feedback' => null
			];

			if (!$isSuggestions) {
				$this->appendVisibleMessageToMemories($memories, $this->getId(), $assistantMessage);
			} else {
				$this->log('Suggestions mode: memory write skipped.');
			}

			return [
				'message' => $assistantMessage,
				'tool_calls' => $orchestrationResult->getToolCalls()
			];

		} catch (\Throwable $e) {
			$this->logError($e->getMessage());

			return [
				'error' => $e->getMessage(),
				'tool_calls' => []
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

	private function normalizeMessageContent(mixed $content): string {
		if ($content === null) {
			return '';
		}

		if (is_string($content)) {
			return $content;
		}

		if (is_bool($content)) {
			return $content ? 'true' : 'false';
		}

		if (is_int($content) || is_float($content)) {
			return (string)$content;
		}

		$json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false || $json === 'null') {
			return '';
		}

		return $json;
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
