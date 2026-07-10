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

use MissionBay\Agent\AgentNodePort;
use MissionBay\Api\IAgentAssistantFallbackBuilder;
use MissionBay\Api\IAgentAssistantFinalResponseService;
use MissionBay\Api\IAgentAssistantMemoryService;
use MissionBay\Api\IAgentAssistantTurnService;
use AssistantFoundation\Api\IAgentContext;
use MissionBay\Dto\Assistant\AgentAssistantTurnResult;

class AiAssistantNode extends AbstractAiAssistantNode {

	public function __construct(
		IAgentAssistantTurnService $turnService,
		IAgentAssistantFinalResponseService $finalResponseService,
		IAgentAssistantMemoryService $memoryService,
		IAgentAssistantFallbackBuilder $fallbackBuilder,
		?string $id = null
	) {
		parent::__construct($turnService, $finalResponseService, $memoryService, $fallbackBuilder, $id);
	}

	public static function getName(): string {
		return 'aiassistantnode';
	}

	public function getDescription(): string {
		return 'Assistant node with non-stream tool orchestration and direct final response output.';
	}

	public function getInputDefinitions(): array {
		return $this->getCommonInputDefinitions(true);
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
				name: 'warning',
				description: 'Warning code, if the response had to use a fallback.',
				type: 'string',
				default: null,
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
		return $this->getCommonDockDefinitions();
	}

	public function execute(array $inputs, array $resources, IAgentContext $context): array {
		$assistantId = $this->createAssistantMessageId();

		try {
			$turnResources = $this->buildTurnResources($resources, 'Missing required chat model.');

			if ($this->readInputPrompt($inputs) === '') {
				$err = 'Prompt is required.';
				$this->logError($err);
				return [
					'error' => $err,
					'tool_calls' => []
				];
			}

			$mode = $this->readInputMode($inputs);
			$isSuggestions = ($mode === 'suggestions');

			$this->log('Mode: ' . $mode . '.');

			$options = $this->buildTurnOptions(
				inputs: $inputs,
				assistantMessageId: $assistantId,
				toolsEnabled: !$isSuggestions,
				memoryReadEnabled: true,
				memoryWriteEnabled: !$isSuggestions,
				mode: $mode
			);

			$turnResult = $this->turnService->run(
				$turnResources,
				$context,
				$options,
				null
			);

			if (!$turnResult->isCompleted()) {
				return $this->handleIncompleteTurn($turnResult);
			}

			try {
				$finalContent = $this->finalResponseService->createDirectResponse($turnResources->getModel(), $turnResult);
			} catch (\Throwable $e) {
				$this->logError('Direct final response failed: ' . $e->getMessage());
				$finalContent = $this->buildDirectFailureFallback($turnResult);
				$assistantMessage = $this->finalResponseService->createAssistantMessage($turnResult, $finalContent);
				$this->appendAssistantMessageToMemory($turnResult, $assistantMessage);

				return [
					'message' => $assistantMessage,
					'tool_calls' => $turnResult->getToolCalls(),
					'warning' => 'fallback_direct_response_error'
				];
			}

			$assistantMessage = $this->finalResponseService->createAssistantMessage($turnResult, $finalContent);
			$this->appendAssistantMessageToMemory($turnResult, $assistantMessage);

			if ($isSuggestions) {
				$this->log('Suggestions mode: memory write skipped.');
			}

			return [
				'message' => $assistantMessage,
				'tool_calls' => $turnResult->getToolCalls()
			];

		} catch (\Throwable $e) {
			$this->logError($e->getMessage());

			return [
				'error' => $e->getMessage(),
				'tool_calls' => []
			];
		}
	}

	private function handleIncompleteTurn(AgentAssistantTurnResult $turnResult): array {
		$finalContent = $turnResult->getFallbackContent() ?? 'Ich konnte die Anfrage nicht vollständig abschließen. Bitte versuche es erneut oder grenze die Anfrage etwas ein.';
		$assistantMessage = $this->finalResponseService->createAssistantMessage($turnResult, $finalContent);
		$this->appendAssistantMessageToMemory($turnResult, $assistantMessage);

		return [
			'message' => $assistantMessage,
			'tool_calls' => $turnResult->getToolCalls(),
			'warning' => $turnResult->getFailureCode() !== '' ? $turnResult->getFailureCode() : 'tool_phase_incomplete'
		];
	}

	private function buildDirectFailureFallback(AgentAssistantTurnResult $turnResult): string {
		$orchestrationResult = $turnResult->getOrchestrationResult();

		if ($orchestrationResult !== null) {
			return $this->fallbackBuilder->build($orchestrationResult);
		}

		return 'Ich konnte die Anfrage nicht vollständig abschließen. Bitte versuche es erneut oder grenze die Anfrage etwas ein.';
	}
}
