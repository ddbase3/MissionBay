<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Tool\Profile;

use AssistantFoundation\Api\IAgentConfirmableToolSet;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionReview;
use AssistantFoundation\Dto\AgentCapabilityCatalog;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentInteractionRequest;
use AssistantFoundation\Dto\AgentInteractionResponse;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiToolCall;
use Base3\Event\Api\IEventManager;
use MissionBay\Api\IAgentTool;
use MissionBay\Audit\AgentToolAuditContext;
use MissionBay\Event\MissionBayAgentActionAuditEvent;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;

/**
 * Run-local MissionBay tool set exposed to replaceable agent runtimes.
 *
 * Read-only calls execute immediately. Explicit mutations create a durable,
 * server-owned approval suspension and can execute only after the exact action
 * is resumed with an explicit decision.
 */
final class MissionBayAgentToolSet implements IAgentConfirmableToolSet {

	/**
	 * @param array<string,IAgentTool> $toolsByName
	 * @param array<int,IAgentTool> $tools
	 * @param array<int,string> $warnings
	 */
	public function __construct(
		private readonly AgentCapabilityCatalog $catalog,
		private readonly array $toolsByName,
		private readonly array $tools,
		private readonly IAgentContext $context,
		private readonly AgentToolContractValidationService $contractValidationService,
		private readonly AgentToolDefinitionSemantics $definitionSemantics,
		private readonly AgentActionFingerprint $fingerprint,
		private readonly AgentMutationCommitGuardService $mutationCommitGuardService,
		private readonly ?IEventManager $eventManager = null,
		private readonly array $warnings = []
	) {}

	public function getCatalog(): AgentCapabilityCatalog {
		return $this->catalog;
	}

	public function getWarnings(): array {
		return $this->warnings;
	}

	public function execute(
		string $callId,
		string $toolName,
		array $arguments,
		array $metadata = []
	): AgentToolResult {
		$call = $this->createCall($callId, $toolName, $arguments, $metadata);
		$definition = $this->findDefinition($call->getName());
		if (is_array($definition) && $this->definitionSemantics->isMutationDefinition($definition)) {
			return AgentToolResult::failure(
				$call->getId(),
				$call->getName(),
				$call->getArguments(),
				'approval_required',
				'Mutating tools require an explicit approval suspension before execution.'
			);
		}

		return $this->executeCall($call, $metadata);
	}

	public function prepareSuspension(
		string $callId,
		string $toolName,
		array $arguments,
		array $metadata = []
	): ?AgentSuspension {
		$call = $this->createCall($callId, $toolName, $arguments, $metadata);
		$definition = $this->findDefinition($call->getName());
		if (!is_array($definition) || !$this->definitionSemantics->isMutationDefinition($definition)) {
			return null;
		}
		if (!$this->definitionSemantics->requiresApprovalDefinition($definition)) {
			throw new \RuntimeException(
				'Mutating tool is not available to external runtimes because it does not require approval: ' . $call->getName()
			);
		}

		$this->applyCallContext($call);
		$inputValidation = $this->contractValidationService->validateInput($call, $this->tools);
		if (!$inputValidation->passes()) {
			throw new \RuntimeException($inputValidation->getSummary());
		}

		$action = $this->createAction($call);
		$actionFingerprint = $this->fingerprint->create($action);
		$snapshot = $this->mutationCommitGuardService->capture($action, $call, $this->context);
		$review = $snapshot !== null
			? $this->mutationCommitGuardService->getActionReview($action, $call, $snapshot, $this->context)
			: $this->buildDefaultReview($action);
		$requestId = 'air-' . substr($actionFingerprint, 0, 16) . '-' . $action->getId();
		$risk = $this->readRisk($definition);
		$request = new AgentInteractionRequest(
			$requestId,
			AgentInteractionRequest::KIND_APPROVAL,
			$action,
			$actionFingerprint,
			$review->getTitle(),
			$review->getMessage(),
			$review->getSummary(),
			$risk,
			['tool_call' => $call->toArray()]
		);

		$this->emitAudit(
			MissionBayAgentActionAuditEvent::TYPE_APPROVAL_REQUESTED,
			$action,
			$review->getMessage(),
			['interaction_request_id' => $requestId, 'risk' => $risk]
		);

		$state = [
			'tool_call' => $call->toArray(),
			'action_fingerprint' => $actionFingerprint,
			'input_contract' => $inputValidation->toArray(),
			'binding' => $this->buildBinding()
		];
		if ($snapshot !== null) {
			$state[AgentMutationCommitGuardService::TOOL_CALL_METADATA_SNAPSHOT] = $snapshot->toArray();
		}

		return new AgentSuspension(
			id: uniqid('tool-suspension-', true),
			status: AgentExecutionStatus::AWAITING_APPROVAL,
			requests: [$request],
			state: $state,
			createdAt: gmdate('c'),
			metadata: [
				'runtime_id' => $this->readContextString(['runtime_id', 'agent_runtime']),
				'turn_id' => $this->readContextString(['turn_id', 'chat_turn_id', 'message_id']),
				'tool_name' => $call->getName()
			]
		);
	}

	public function resumeSuspension(
		AgentSuspension $suspension,
		AgentInteractionResponse $response,
		array $metadata = []
	): AgentToolResult {
		$requests = $suspension->getRequests();
		if (count($requests) !== 1) {
			throw new \RuntimeException('Tool suspension must contain exactly one interaction request.');
		}
		$request = $requests[0];
		if ($response->getRequestId() !== $request->getId()) {
			throw new \RuntimeException('Tool suspension response does not match the pending interaction request.');
		}

		$action = $request->getAction();
		$computedFingerprint = $this->fingerprint->create($action);
		if (!hash_equals($request->getActionFingerprint(), $computedFingerprint)) {
			throw new \RuntimeException('Tool suspension action fingerprint is invalid.');
		}
		$this->assertBinding($suspension->getState()['binding'] ?? null);

		$callData = $suspension->getState()['tool_call'] ?? null;
		if (!is_array($callData)) {
			throw new \RuntimeException('Tool suspension contains no valid tool call.');
		}
		$call = AiToolCall::fromArray($callData);
		if (
			$call->getId() !== $action->getId()
			|| $call->getName() !== $action->getName()
			|| $call->getArguments() !== $action->getInput()
		) {
			throw new \RuntimeException('Tool suspension call no longer matches the reviewed action.');
		}

		if ($response->getDecision() === AgentInteractionResponse::DECISION_DENY) {
			$reason = trim($response->getNote()) !== ''
				? $response->getNote()
				: 'The user declined the pending action.';
			$this->emitAudit(
				MissionBayAgentActionAuditEvent::TYPE_APPROVAL_DENIED,
				$action,
				$reason,
				['interaction_request_id' => $request->getId()]
			);
			return AgentToolResult::failure(
				$call->getId(),
				$call->getName(),
				$call->getArguments(),
				'action_declined_by_user',
				$reason,
				array_replace($this->resultMetadata($call), [
					'interaction_request_id' => $request->getId(),
					'user_decision' => $response->toArray()
				]),
				['ok' => false, 'blocked' => true, 'decision' => 'deny', 'reason' => $reason]
			);
		}
		if ($response->getDecision() !== AgentInteractionResponse::DECISION_APPROVE) {
			throw new \RuntimeException('Approval suspension accepts only approve or deny decisions.');
		}

		$callMetadata = array_replace($call->getMetadata(), $metadata);
		$callMetadata[AgentMutationCommitGuardService::TOOL_CALL_METADATA_APPROVAL_FINGERPRINT] = $request->getActionFingerprint();
		$callMetadata[AgentMutationCommitGuardService::TOOL_CALL_METADATA_INTERACTION_REQUEST] = $request->getId();
		$snapshot = $suspension->getState()[AgentMutationCommitGuardService::TOOL_CALL_METADATA_SNAPSHOT] ?? null;
		if (is_array($snapshot)) {
			$callMetadata[AgentMutationCommitGuardService::TOOL_CALL_METADATA_SNAPSHOT] = $snapshot;
		}
		$approvedCall = new AiToolCall(
			$call->getId(),
			$call->getName(),
			$call->getArguments(),
			$callMetadata
		);

		$this->emitAudit(
			MissionBayAgentActionAuditEvent::TYPE_APPROVAL_GRANTED,
			$action,
			trim($response->getNote()) !== '' ? $response->getNote() : 'The user approved the exact pending action.',
			['interaction_request_id' => $request->getId()]
		);

		return $this->executeCall($approvedCall, $metadata, true);
	}

	/** @param array<string,mixed> $metadata */
	private function createCall(string $callId, string $toolName, array $arguments, array $metadata): AiToolCall {
		$callId = trim($callId);
		$toolName = trim($toolName);
		return new AiToolCall($callId, $toolName, $arguments, array_replace([
			'runtime' => 'external',
			'tool_mode' => 'governed'
		], $metadata));
	}

	/** @param array<string,mixed> $metadata */
	private function executeCall(AiToolCall $call, array $metadata, bool $approvedMutation = false): AgentToolResult {
		$callId = $call->getId();
		$toolName = $call->getName();
		$arguments = $call->getArguments();
		if ($toolName === '' || !$this->catalog->has($toolName)) {
			return AgentToolResult::failure($callId, $toolName, $arguments, 'unknown_tool', 'The requested tool is not part of the resolved tool set.');
		}
		$tool = $this->toolsByName[$toolName] ?? null;
		if (!$tool instanceof IAgentTool) {
			return AgentToolResult::failure($callId, $toolName, $arguments, 'tool_not_executable', 'The requested tool has no executable implementation.');
		}

		$this->applyCallContext($call);
		$definition = $this->findDefinition($toolName);
		$isMutation = is_array($definition) && $this->definitionSemantics->isMutationDefinition($definition);
		if ($isMutation && !$approvedMutation) {
			return AgentToolResult::failure($callId, $toolName, $arguments, 'approval_required', 'Mutating tools require explicit approval before execution.');
		}

		$inputValidation = $this->contractValidationService->validateInput($call, $this->tools);
		if (!$inputValidation->passes()) {
			return AgentToolResult::failure(
				$callId,
				$toolName,
				$arguments,
				$inputValidation->getReasonCode(),
				$inputValidation->getSummary(),
				array_replace($this->resultMetadata($call), ['input_contract' => $inputValidation->toArray()])
			);
		}

		if ($isMutation) {
			$commitDecision = $this->mutationCommitGuardService->validate($call, $this->context);
			if (!$commitDecision->isAllowed()) {
				return AgentToolResult::failure(
					$callId,
					$toolName,
					$arguments,
					$commitDecision->getCode(),
					$commitDecision->getReason(),
					array_replace($this->resultMetadata($call), ['commit_guard' => $commitDecision->toArray()])
				);
			}
		}

		$auditMetadata = $this->buildAuditMetadata($callId, $metadata);
		$previousAuditMetadata = AgentToolAuditContext::push($this->context, $auditMetadata);
		try {
			$output = $tool->callTool($toolName, $arguments, $this->context);
		}
		catch (\Throwable $e) {
			if ($isMutation) {
				$this->mutationCommitGuardService->recordCommitResult($call, $this->context, false, $e->getMessage(), ['type' => get_class($e)]);
			}
			return AgentToolResult::failure(
				$callId,
				$toolName,
				$arguments,
				'tool_execution_failed',
				$e->getMessage(),
				array_replace($this->resultMetadata($call), ['exception' => get_class($e)])
			);
		}
		finally {
			AgentToolAuditContext::restore($this->context, $previousAuditMetadata);
		}

		$outputValidation = $this->contractValidationService->validateOutput($call, $output, $this->tools);
		if (!$outputValidation->passes()) {
			if ($isMutation) {
				$this->mutationCommitGuardService->recordCommitResult($call, $this->context, false, $outputValidation->getSummary());
			}
			return AgentToolResult::failure(
				$callId,
				$toolName,
				$arguments,
				$outputValidation->getReasonCode(),
				$outputValidation->getSummary(),
				array_replace($this->resultMetadata($call), [
					'input_contract' => $inputValidation->toArray(),
					'output_contract' => $outputValidation->toArray()
				]),
				$output
			);
		}

		if ($isMutation) {
			$this->mutationCommitGuardService->recordCommitResult($call, $this->context, true, 'Mutation tool completed successfully.');
		}
		return AgentToolResult::success(
			$callId,
			$toolName,
			$arguments,
			$output,
			array_replace($this->resultMetadata($call), [
				'input_contract' => $inputValidation->toArray(),
				'output_contract' => $outputValidation->toArray(),
				'mutation' => $isMutation
			])
		);
	}

	private function createAction(AiToolCall $call): AgentAction {
		return new AgentAction(
			trim($call->getId()),
			AgentAction::TYPE_TOOL_CALL,
			trim($call->getName()),
			$call->getArguments(),
			[
				'iteration' => (int)($call->getMetadata()['iteration'] ?? 0),
				'tool_call' => $call->getMetadata()
			]
		);
	}

	private function buildDefaultReview(AgentAction $action): AgentActionReview {
		$capability = $this->catalog->get($action->getName());
		$title = trim((string)($capability?->getTitle() ?? $action->getName()));
		return new AgentActionReview(
			'Confirm: ' . ($title !== '' ? $title : 'Tool action'),
			'This action may change data and will execute only after explicit approval.',
			$action->getInput()
		);
	}

	/** @return ?array<string,mixed> */
	private function findDefinition(string $toolName): ?array {
		return $this->definitionSemantics->findDefinition($this->catalog->getToolDefinitions(), $toolName);
	}

	/** @param array<string,mixed> $definition */
	private function readRisk(array $definition): string {
		$annotations = $this->definitionSemantics->getAnnotations($definition);
		$riskHint = strtolower(trim((string)($annotations['riskHint'] ?? '')));
		if (in_array($riskHint, ['high', 'medium'], true)) {
			return $riskHint;
		}

		return (($annotations['destructiveHint'] ?? $annotations['destructive'] ?? false) === true)
			? 'high'
			: 'medium';
	}

	/** @return array<string,string> */
	private function buildBinding(): array {
		$result = [
			'runtime_id' => $this->readContextString(['runtime_id', 'agent_runtime']),
			'conversation_channel_id' => $this->readContextString(['conversation_channel_id']),
			'conversation_id' => $this->readContextString(['conversation_id']),
			'config_group' => $this->readContextString(['config_group', 'chatbot_config_group']),
			'config_name' => $this->readContextString(['config_name', 'chatbot_config_name'])
		];
		return array_filter($result, static fn(string $value): bool => $value !== '');
	}

	private function assertBinding(mixed $binding): void {
		if (!is_array($binding)) {
			throw new \RuntimeException('Tool suspension binding is missing.');
		}
		$current = $this->buildBinding();
		foreach ($binding as $key => $value) {
			if (!is_scalar($value) && $value !== null) {
				throw new \RuntimeException('Tool suspension binding is invalid.');
			}
			$value = trim((string)$value);
			if ($value !== '' && !hash_equals($value, (string)($current[$key] ?? ''))) {
				throw new \RuntimeException('Tool suspension does not belong to the current agent execution context.');
			}
		}
	}

	private function applyCallContext(AiToolCall $call): void {
		$metadata = $call->getMetadata();
		$this->context->setVar(AgentToolLoopContextKeys::ITERATION, max(0, (int)($metadata['iteration'] ?? 0)));
		$this->context->setVar(AgentToolLoopContextKeys::CALL_INDEX, max(0, (int)($metadata['call_index'] ?? 0)));
		$trace = array_replace(
			$this->buildBaseTrace(),
			is_array($metadata['trace'] ?? null) ? $metadata['trace'] : []
		);
		$this->context->setVar(AgentToolLoopContextKeys::TRACE, $trace);
	}

	/** @return array<string,mixed> */
	private function resultMetadata(AiToolCall $call): array {
		$metadata = $call->getMetadata();
		return [
			'iteration' => max(0, (int)($metadata['iteration'] ?? 0)),
			'call_index' => max(0, (int)($metadata['call_index'] ?? 0))
		];
	}

	/** @param array<string,mixed> $metadata @return array<string,mixed> */
	private function buildAuditMetadata(string $callId, array $metadata): array {
		$trace = array_replace($this->buildBaseTrace(), is_array($metadata['trace'] ?? null) ? $metadata['trace'] : []);
		return [
			'source' => $this->readMetadataString($metadata, 'source', AgentToolAuditContext::SOURCE_DIRECT),
			'call_id' => $callId,
			'label' => $this->readMetadataString($metadata, 'label'),
			'iteration' => max(0, (int)($metadata['iteration'] ?? 0)),
			'call_index' => max(0, (int)($metadata['call_index'] ?? 0)),
			'trace' => $trace
		];
	}

	/** @return array<string,mixed> */
	private function buildBaseTrace(): array {
		$trace = [
			'runtime_id' => $this->readContextString(['runtime_id', 'agent_runtime']),
			'turn_id' => $this->readContextString(['turn_id', 'chat_turn_id', 'message_id']),
			'chatbot_key' => $this->readContextString(['conversation_channel_id']),
			'config_group' => $this->readContextString(['config_group', 'chatbot_config_group']),
			'config_name' => $this->readContextString(['config_name', 'chatbot_config_name']),
			'conversation_id' => $this->readContextString(['conversation_id']),
			'prompt_text' => $this->readContextString(['prompt_text'])
		];
		return array_filter($trace, static fn(mixed $value): bool => is_scalar($value) && trim((string)$value) !== '');
	}

	/** @param array<int,string> $keys */
	private function readContextString(array $keys): string {
		foreach ($keys as $key) {
			try {
				$value = $this->context->getVar($key);
			}
			catch (\Throwable) {
				continue;
			}
			if (!is_scalar($value) && $value !== null) {
				continue;
			}
			$value = trim((string)$value);
			if ($value !== '') {
				return $value;
			}
		}
		return '';
	}

	/** @param array<string,mixed> $metadata */
	private function readMetadataString(array $metadata, string $key, string $default = ''): string {
		$value = $metadata[$key] ?? null;
		if (is_scalar($value) || $value === null) {
			$value = trim((string)$value);
			if ($value !== '') {
				return $value;
			}
		}
		return $default;
	}

	/** @param array<string,mixed> $metadata */
	private function emitAudit(string $type, AgentAction $action, string $reason, array $metadata = []): void {
		if (!$this->eventManager instanceof IEventManager) {
			return;
		}
		try {
			$this->eventManager->fire(new MissionBayAgentActionAuditEvent(
				$type,
				$action,
				$reason,
				$this->buildBaseTrace(),
				$metadata
			));
		}
		catch (\Throwable) {
		}
	}
}
