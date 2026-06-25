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
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Api\IAgentAssistantFallbackBuilder;
use MissionBay\Api\IAgentAssistantFinalResponseService;
use MissionBay\Api\IAgentAssistantMemoryService;
use MissionBay\Api\IAgentAssistantTurnService;
use MissionBay\Api\IAgentMemory;
use MissionBay\Api\IAgentProfileSelector;
use MissionBay\Api\IAgentTool;
use MissionBay\Dto\Assistant\AgentAssistantTurnOptions;
use MissionBay\Dto\Assistant\AgentAssistantTurnResources;
use MissionBay\Dto\Assistant\AgentAssistantTurnResult;
use MissionBay\Node\AbstractAgentNode;

abstract class AbstractAiAssistantNode extends AbstractAgentNode {

	protected ?ILogger $logger = null;

	public function __construct(
		protected IAgentAssistantTurnService $turnService,
		protected IAgentAssistantFinalResponseService $finalResponseService,
		protected IAgentAssistantMemoryService $memoryService,
		protected IAgentAssistantFallbackBuilder $fallbackBuilder,
		?string $id = null
	) {
		parent::__construct($id);
	}

	/**
	 * @return array<int,AgentNodePort>
	 */
	protected function getCommonInputDefinitions(bool $includeMode = false): array {
		$definitions = [
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
			),
			new AgentNodePort(
				name: 'maxtoolloops',
				description: 'Maximum number of tool orchestration loops.',
				type: 'int',
				default: 8,
				required: false
			)
		];

		if ($includeMode) {
			$definitions[] = new AgentNodePort(
				name: 'mode',
				description: 'Operation mode: "chat" (default) or "suggestions" (read-only memory, no tools).',
				type: 'string',
				default: 'chat',
				required: false
			);
		}

		return $definitions;
	}

	/**
	 * @return array<int,AgentNodeDock>
	 */
	protected function getCommonDockDefinitions(): array {
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

	protected function buildTurnResources(array $resources, string $missingModelMessage): AgentAssistantTurnResources {
		$this->logger = $this->readLogger($resources);
		$model = $resources['chatmodel'][0] ?? null;

		if (!$model instanceof IAiChatModel) {
			throw new \RuntimeException($missingModelMessage);
		}

		return new AgentAssistantTurnResources(
			model: $model,
			memories: $this->readMemories($resources),
			tools: $this->readTools($resources),
			logger: $this->logger,
			profileSelector: $this->readProfileSelector($resources)
		);
	}

	protected function buildTurnOptions(
		array $inputs,
		string $assistantMessageId,
		bool $toolsEnabled = true,
		bool $memoryReadEnabled = true,
		bool $memoryWriteEnabled = true,
		string $mode = 'chat'
	): AgentAssistantTurnOptions {
		return new AgentAssistantTurnOptions(
			prompt: trim((string)($inputs['prompt'] ?? '')),
			system: trim((string)($inputs['system'] ?? 'You are a helpful assistant.')),
			maxToolLoops: $this->readMaxToolLoops($inputs),
			toolsEnabled: $toolsEnabled,
			memoryReadEnabled: $memoryReadEnabled,
			memoryWriteEnabled: $memoryWriteEnabled,
			mode: $mode,
			nodeId: $this->getId(),
			assistantMessageId: $assistantMessageId
		);
	}

	protected function appendAssistantMessageToMemory(AgentAssistantTurnResult $turnResult, array $assistantMessage): void {
		if (!$turnResult->shouldWriteMemory()) {
			return;
		}

		$this->memoryService->appendVisibleMessage(
			$turnResult->getMemories(),
			$turnResult->getNodeId(),
			$assistantMessage,
			$this->logger
		);
	}

	protected function createAssistantMessageId(): string {
		return uniqid('msg_', true);
	}

	protected function readInputPrompt(array $inputs): string {
		return trim((string)($inputs['prompt'] ?? ''));
	}

	protected function readInputMode(array $inputs): string {
		$mode = strtolower(trim((string)($inputs['mode'] ?? 'chat')));

		if ($mode === '') {
			return 'chat';
		}

		return $mode;
	}

	protected function readMaxToolLoops(array $inputs): int {
		$value = $inputs['maxtoolloops'] ?? 8;

		if ($value === null || $value === '') {
			return 8;
		}

		if (!is_int($value) && !is_float($value) && !is_string($value)) {
			throw new \RuntimeException('Input maxtoolloops must be numeric.');
		}

		if (!is_numeric($value)) {
			throw new \RuntimeException('Input maxtoolloops must be numeric.');
		}

		$maxToolLoops = (int)$value;

		if ($maxToolLoops < 1) {
			throw new \RuntimeException('Input maxtoolloops must be greater than zero.');
		}

		return $maxToolLoops;
	}

	protected function log(string $msg): void {
		if ($this->logger) {
			$this->logger->log(static::getName(), '[' . $this->id . '] ' . $msg);
		}
	}

	protected function logError(string $msg): void {
		$this->log('[ERROR] ' . $msg);
	}

	private function readLogger(array $resources): ?ILogger {
		$logger = $resources['logger'][0] ?? null;

		return $logger instanceof ILogger ? $logger : null;
	}

	/**
	 * @return array<int,IAgentMemory>
	 */
	private function readMemories(array $resources): array {
		$memories = [];

		foreach (($resources['memory'] ?? []) as $memory) {
			if ($memory instanceof IAgentMemory) {
				$memories[] = $memory;
			}
		}

		return $memories;
	}

	/**
	 * @return array<int,IAgentTool>
	 */
	private function readTools(array $resources): array {
		$tools = [];

		foreach (($resources['tools'] ?? []) as $tool) {
			if ($tool instanceof IAgentTool) {
				$tools[] = $tool;
			}
		}

		return $tools;
	}

	private function readProfileSelector(array $resources): ?IAgentProfileSelector {
		$profileSelector = $resources['profileselector'][0] ?? null;

		return $profileSelector instanceof IAgentProfileSelector ? $profileSelector : null;
	}
}
