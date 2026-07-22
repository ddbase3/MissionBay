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

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Api\IAiChatModel;
use AssistantRuntime\Service\AgentEventDispatcher;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Api\IAgentAssistantFallbackBuilder;
use MissionBay\Api\IAgentAssistantFinalResponseService;
use MissionBay\Api\IAgentAssistantMemoryService;
use MissionBay\Api\IAgentAssistantTurnService;
use MissionBay\Dto\Assistant\AgentAssistantTurnResult;

/**
 * Assistant node for both buffered and incremental agent execution.
 *
 * The flow and orchestration path is identical in both modes. Incremental
 * output is enabled only by providing an IAgentEventSink in the run context.
 */
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
		return 'Assistant node with tool orchestration and optional incremental event output.';
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
				name: 'status',
				description: 'Provider-neutral agent execution status.',
				type: 'string',
				default: null,
				required: false
			),
			new AgentNodePort(
				name: 'interaction_requests',
				description: 'Structured approval or clarification requests.',
				type: 'array',
				default: [],
				required: false
			),
			new AgentNodePort(
				name: 'resume_handle',
				description: 'Opaque one-time handle used to resume the suspended agent turn.',
				type: 'string',
				default: null,
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
		$eventSink = AgentEventDispatcher::fromContext($context);
		$assistantId = $this->createAssistantMessageId();

		try {
			$turnResources = $this->buildTurnResources($resources, 'Missing required chat model.');

			if ($this->readInputPrompt($inputs) === '' && !$this->hasResumeInput($inputs)) {
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
			AgentEventDispatcher::emit($eventSink, 'msgid', ['id' => $assistantId]);

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
				$this->buildEventCallback($eventSink)
			);

			if ($turnResult->isSuspended()) {
				return $this->handleSuspendedTurn($context, $eventSink, $turnResult);
			}

			if (!$turnResult->canGenerateFinalResponse()) {
				$this->storeModelResults($context, $turnResult);
				return $this->handleIncompleteTurn($context, $eventSink, $turnResult);
			}

			if ($turnResult->isPartialFinalResponse()) {
				AgentEventDispatcher::emit($eventSink, 'response.partial', [
					'reason' => $turnResult->getFailureCode(),
					'message' => $turnResult->getFailureMessage()
				]);
			}

			[$finalContent, $responseWarning, $streamStatus] = $this->createFinalResponse(
				$eventSink,
				$turnResources->getModel(),
				$turnResult
			);

			$assistantMessage = $this->finalResponseService->createAssistantMessage($turnResult, $finalContent);
			$this->appendAssistantMessageToMemory($turnResult, $assistantMessage);
			$this->storeModelResults($context, $turnResult);
			$this->finalizeTypedAgentResult($context, $turnResult, $assistantMessage, $finalContent);
			AgentEventDispatcher::emit($eventSink, 'done', ['status' => $streamStatus]);

			if ($isSuggestions) {
				$this->log('Suggestions mode: memory write skipped.');
			}

			$output = [
				'message' => $assistantMessage,
				'tool_calls' => $turnResult->getToolCalls(),
				'status' => $turnResult->getExecutionStatus()
			];

			if ($responseWarning !== '') {
				$output['warning'] = $responseWarning;
			}
			elseif ($turnResult->isPartialFinalResponse()) {
				$output['warning'] = $turnResult->getFailureCode() !== ''
					? $turnResult->getFailureCode()
					: 'partial_response';
			}

			return $output;
		}
		catch (\Throwable $e) {
			$this->logError($e->getMessage());
			$userMessage = 'Es ist ein technischer Fehler aufgetreten. Die Anfrage konnte nicht vollständig abgeschlossen werden.';
			AgentEventDispatcher::emit($eventSink, 'token', ['text' => $userMessage]);
			AgentEventDispatcher::emit($eventSink, 'error', [
				'message' => $e->getMessage(),
				'user_message' => $userMessage,
				'type' => get_class($e),
				'code' => $e->getCode(),
				'file' => $e->getFile(),
				'line' => $e->getLine()
			]);
			AgentEventDispatcher::emit($eventSink, 'done', ['status' => 'error']);

			return [
				'error' => $e->getMessage(),
				'tool_calls' => []
			];
		}
	}

	private function handleSuspendedTurn(
		IAgentContext $context,
		?IAgentEventSink $eventSink,
		AgentAssistantTurnResult $turnResult
	): array {
		$this->storeModelResults($context, $turnResult);
		$requests = array_map(
			static fn($request): array => $request->toArray(),
			$turnResult->getInteractionRequests()
		);
		$resumeHandle = $turnResult->getResumeHandle();

		AgentEventDispatcher::emit($eventSink, 'agent.interaction.required', [
			'status' => $turnResult->getExecutionStatus(),
			'interaction_requests' => $requests,
			'resume_handle' => $resumeHandle
		]);
		AgentEventDispatcher::emit($eventSink, 'done', ['status' => $turnResult->getExecutionStatus()]);

		return [
			'status' => $turnResult->getExecutionStatus(),
			'interaction_requests' => $requests,
			'resume_handle' => $resumeHandle,
			'tool_calls' => $turnResult->getToolCalls()
		];
	}

	private function handleIncompleteTurn(
		IAgentContext $context,
		?IAgentEventSink $eventSink,
		AgentAssistantTurnResult $turnResult
	): array {
		$finalContent = $turnResult->getFallbackContent()
			?? 'Ich konnte die Anfrage nicht vollständig abschließen. Bitte versuche es erneut oder grenze die Anfrage etwas ein.';
		AgentEventDispatcher::emit($eventSink, 'token', ['text' => $finalContent]);
		AgentEventDispatcher::emit($eventSink, 'done', ['status' => 'fallback']);

		$assistantMessage = $this->finalResponseService->createAssistantMessage($turnResult, $finalContent);
		$this->appendAssistantMessageToMemory($turnResult, $assistantMessage);
		$this->finalizeTypedAgentResult($context, $turnResult, $assistantMessage, $finalContent);

		return [
			'message' => $assistantMessage,
			'tool_calls' => $turnResult->getToolCalls(),
			'status' => $turnResult->getExecutionStatus(),
			'warning' => $turnResult->getFailureCode() !== ''
				? $turnResult->getFailureCode()
				: 'tool_phase_incomplete'
		];
	}

	/** @return array{0:string,1:string,2:string} */
	private function createFinalResponse(
		?IAgentEventSink $eventSink,
		IAiChatModel $model,
		AgentAssistantTurnResult $turnResult
	): array {
		if ($eventSink === null) {
			try {
				return [$this->finalResponseService->createDirectResponse($model, $turnResult), '', 'complete'];
			}
			catch (\Throwable $e) {
				$this->logError('Direct final response failed: ' . $e->getMessage());
				return [$this->buildFailureFallback($turnResult), 'fallback_direct_response_error', 'fallback_direct_response_error'];
			}
		}

		try {
			$finalContent = $this->finalResponseService->createStreamingResponse(
				$model,
				$turnResult,
				function(string $delta) use ($eventSink): void {
					AgentEventDispatcher::emit($eventSink, 'token', ['text' => $delta]);
				},
				function(array $meta) use ($eventSink): void {
					AgentEventDispatcher::emit($eventSink, 'meta', $meta);
				}
			);

			if (trim($finalContent) !== '') {
				return [$finalContent, '', 'complete'];
			}

			$this->logError('Streaming response completed without visible content. Starting one buffered recovery request.');
			try {
				$recoveredContent = $this->finalResponseService->createDirectResponse($model, $turnResult);
			}
			catch (\Throwable $e) {
				$this->logError('Buffered final-response recovery failed: ' . $e->getMessage());
				$recoveredContent = '';
			}

			if (trim($recoveredContent) === '') {
				$recoveredContent = $this->buildFailureFallback($turnResult);
				$status = 'fallback_empty_stream';
			}
			else {
				$status = 'recovered_empty_stream';
			}

			AgentEventDispatcher::emit($eventSink, 'token', ['text' => $recoveredContent]);
			return [$recoveredContent, $status === 'fallback_empty_stream' ? $status : '', $status];
		}
		catch (\Throwable $e) {
			$this->logError('Streaming final response failed: ' . $e->getMessage());
			$finalContent = $this->buildFailureFallback($turnResult);
			AgentEventDispatcher::emit($eventSink, 'token', ['text' => $finalContent]);
			return [$finalContent, 'fallback_stream_error', 'fallback_stream_error'];
		}
	}

	private function buildFailureFallback(AgentAssistantTurnResult $turnResult): string {
		$orchestrationResult = $turnResult->getOrchestrationResult();
		if ($orchestrationResult !== null) {
			return $this->fallbackBuilder->build($orchestrationResult);
		}
		return 'Ich konnte die Anfrage nicht vollständig abschließen. Bitte versuche es erneut oder grenze die Anfrage etwas ein.';
	}

	private function buildEventCallback(?IAgentEventSink $eventSink): ?callable {
		if ($eventSink === null) {
			return null;
		}

		return function(string $event, array $payload) use ($eventSink): void {
			AgentEventDispatcher::emit($eventSink, $event, $payload);
		};
	}

}
