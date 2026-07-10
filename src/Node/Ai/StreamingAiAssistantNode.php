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
use EventTransport\Api\IEventStream;
use EventTransport\Api\IEventStreamFactory;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Api\IAgentAssistantFallbackBuilder;
use MissionBay\Api\IAgentAssistantFinalResponseService;
use MissionBay\Api\IAgentAssistantMemoryService;
use MissionBay\Api\IAgentAssistantTurnService;
use AssistantFoundation\Api\IAgentContext;
use MissionBay\Dto\Assistant\AgentAssistantTurnResult;

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
 * - incomplete tool phases are converted into a useful fallback response when possible
 */
class StreamingAiAssistantNode extends AbstractAiAssistantNode {

	private IEventStreamFactory $streamFactory;

	public function __construct(
		IEventStreamFactory $streamFactory,
		IEventManager $eventManager,
		IAgentAssistantTurnService $turnService,
		IAgentAssistantFinalResponseService $finalResponseService,
		IAgentAssistantMemoryService $memoryService,
		IAgentAssistantFallbackBuilder $fallbackBuilder,
		?string $id = null
	) {
		parent::__construct($turnService, $finalResponseService, $memoryService, $fallbackBuilder, $id);
		$this->streamFactory = $streamFactory;
	}

	public static function getName(): string {
		return 'streamingaiassistantnode';
	}

	public function getDescription(): string {
		return 'Assistant node with non-stream tool orchestration and final streaming response.';
	}

	public function getInputDefinitions(): array {
		return $this->getCommonInputDefinitions();
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
		return $this->getCommonDockDefinitions();
	}

	public function execute(array $inputs, array $resources, IAgentContext $context): array {
		$stream = null;
		$assistantId = $this->createAssistantMessageId();

		try {
			$turnResources = $this->buildTurnResources($resources, 'Missing chat model.');

			if ($this->readInputPrompt($inputs) === '') {
				$err = 'Prompt is required.';
				$this->logError($err);
				return ['error' => $err];
			}

			$options = $this->buildTurnOptions(
				inputs: $inputs,
				assistantMessageId: $assistantId,
				toolsEnabled: true,
				memoryReadEnabled: true,
				memoryWriteEnabled: true,
				mode: 'chat'
			);

			$stream = $this->streamFactory->createStream(
				'streamingaiassistant',
				uniqid('chat-', true)
			);

			$stream->start();

			$context->setVar('eventstream', $stream);
			$stream->push('msgid', ['id' => $assistantId]);

			$turnResult = $this->turnService->run(
				$turnResources,
				$context,
				$options,
				$this->buildStreamEventCallback($stream)
			);

			if (!$turnResult->isCompleted()) {
				return $this->handleIncompleteTurn($stream, $turnResult);
			}

			try {
				$finalContent = $this->runStreamingFinalResponse($stream, $turnResources->getModel(), $turnResult);
			} catch (\Throwable $e) {
				$this->logError('Streaming phase failed: ' . $e->getMessage());

				$finalContent = $this->buildStreamingFailureFallback($turnResult);
				$this->pushTextAndDone($stream, $finalContent, 'fallback_stream_error');
			}

			$assistantMessage = $this->finalResponseService->createAssistantMessage($turnResult, $finalContent);
			$this->appendAssistantMessageToMemory($turnResult, $assistantMessage);

			return [
				'stream_ready' => true
			];

		} catch (\Throwable $e) {
			$this->logError($e->getMessage());

			if ($stream !== null && !$stream->isDisconnected()) {
				$userMessage = 'Es ist ein technischer Fehler aufgetreten. Die Anfrage konnte nicht vollständig abgeschlossen werden.';
				$stream->push('token', ['text' => $userMessage]);
				$stream->push('error', [
					'message' => $e->getMessage(),
					'user_message' => $userMessage,
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

	private function handleIncompleteTurn(IEventStream $stream, AgentAssistantTurnResult $turnResult): array {
		$finalContent = $turnResult->getFallbackContent() ?? 'Ich konnte die Anfrage nicht vollständig abschließen. Bitte versuche es erneut oder grenze die Anfrage etwas ein.';
		$this->pushTextAndDone($stream, $finalContent, 'fallback');

		$assistantMessage = $this->finalResponseService->createAssistantMessage($turnResult, $finalContent);
		$this->appendAssistantMessageToMemory($turnResult, $assistantMessage);

		return [
			'stream_ready' => true,
			'warning' => $turnResult->getFailureCode() !== '' ? $turnResult->getFailureCode() : 'tool_phase_incomplete'
		];
	}

	private function runStreamingFinalResponse(IEventStream $stream, IAiChatModel $model, AgentAssistantTurnResult $turnResult): string {
		$finalContent = $this->finalResponseService->createStreamingResponse(
			$model,
			$turnResult,
			function (string $delta) use ($stream) {
				if ($stream->isDisconnected()) {
					return;
				}

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

	private function buildStreamingFailureFallback(AgentAssistantTurnResult $turnResult): string {
		$orchestrationResult = $turnResult->getOrchestrationResult();

		if ($orchestrationResult !== null) {
			return $this->fallbackBuilder->build($orchestrationResult);
		}

		return 'Ich konnte die Anfrage nicht vollständig abschließen. Bitte versuche es erneut oder grenze die Anfrage etwas ein.';
	}

	private function buildStreamEventCallback(IEventStream $stream): callable {
		return function (string $event, array $payload) use ($stream) {
			if ($stream->isDisconnected()) {
				return;
			}

			$stream->push($event, $payload);
		};
	}

	private function pushTextAndDone(IEventStream $stream, string $text, string $status): void {
		if ($stream->isDisconnected()) {
			return;
		}

		$stream->push('token', ['text' => $text]);
		$stream->push('done', ['status' => $status]);
	}
}
