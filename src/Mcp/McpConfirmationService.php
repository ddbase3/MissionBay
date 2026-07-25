<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use AssistantFoundation\Dto\AiToolCall;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentMutationGuardedTool;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IConfirmableAgentTool;
use MissionBay\Audit\AgentToolAuditContext;
use MissionBay\Event\MissionBayAgentActionAuditEvent;
use MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;

/**
 * McpConfirmationService
 *
 * Implements a synchronous, multi-call confirmation workflow for MCP tools.
 */
class McpConfirmationService {

	private const LOG_SCOPE = 'missionbay_mcp';
	public const TOOL_NAME = 'missionbay_confirm_action';

	public function __construct(
		private readonly McpConfirmationStore $store,
		private readonly ILogger $logger,
		private readonly AgentMutationCommitGuardService $mutationCommitGuard,
		private readonly AgentToolDefinitionSemantics $toolDefinitionSemantics,
		private readonly ?IEventManager $eventManager = null
	) {}

	public static function getName(): string {
		return 'mcpconfirmationservice';
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getToolDefinition(): array {
		return [
			'type' => 'function',
			'label' => 'Confirm Pending Action',
			'category' => 'control',
			'tags' => ['confirmation', 'human-in-the-loop', 'control'],
			'priority' => 5,
			'function' => [
				'name' => self::TOOL_NAME,
				'description' => 'Accept or decline a pending MissionBay action that requires explicit confirmation before execution.',
				'annotations' => [
					'readOnlyHint' => false,
					'destructiveHint' => true,
					'idempotentHint' => false,
					'openWorldHint' => true
				],
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'confirmation_id' => [
							'type' => 'string',
							'description' => 'Pending confirmation id returned by a previous tool call.'
						],
						'decision' => [
							'type' => 'string',
							'enum' => ['accept', 'decline'],
							'description' => 'Whether to execute or decline the pending action.'
						],
						'note' => [
							'type' => 'string',
							'description' => 'Optional note from the user or client.'
						]
					],
					'required' => ['confirmation_id', 'decision']
				],
				'outputSchema' => [
					'type' => 'object',
					'properties' => [
						'ok' => ['type' => 'boolean'],
						'confirmed' => ['type' => 'boolean'],
						'confirmation_id' => ['type' => 'string'],
						'status' => [
							'type' => 'string',
							'enum' => ['accepted', 'declined', 'blocked']
						],
						'error_code' => ['type' => 'string'],
						'message' => ['type' => 'string'],
						'tool' => ['type' => 'string'],
						'result' => new \stdClass()
					],
					'required' => ['ok', 'confirmed', 'confirmation_id', 'status']
				]
			]
		];
	}

	/**
	 * @param array<string,mixed> $arguments
	 * @return array<string,mixed>|null
	 */
	public function createPendingIfNeeded(string $profileId, string $toolName, array $arguments, McpToolCatalog $catalog, IAgentContext $context): ?array {
		$tool = $catalog->getTool($toolName);
		$definition = $catalog->getDefinition($toolName);

		$annotations = $this->toolDefinitionSemantics->getAnnotations($definition);
		$explicitCommitGuard = ($annotations['commitGuardRequired'] ?? $annotations['commit_guard_required'] ?? null) === true;

		if(
			$this->toolDefinitionSemantics->isMutationDefinition($definition)
			&& $this->toolDefinitionSemantics->requiresApprovalDefinition($definition)
			&& $explicitCommitGuard
		) {
			if(!$tool instanceof IAgentMutationGuardedTool) {
				throw new \RuntimeException(
					'MCP mutation requires a commit guard but the tool does not implement IAgentMutationGuardedTool: ' . $toolName
				);
			}

			return $this->createGuardedPending($profileId, $toolName, $arguments, $definition, $catalog, $context);
		}

		if(!$tool instanceof IConfirmableAgentTool) {
			return null;
		}

		$confirmation = $tool->getConfirmationRequest($toolName, $arguments, $context);

		if($confirmation === null) {
			return null;
		}

		$callId = AgentToolAuditContext::generateCallId('mcp-call');
		$nodeId = $tool instanceof IAgentResource ? $tool->getId() : 'mcp_' . $this->normalizeId($profileId);
		$record = $this->store->create(
			$profileId,
			$toolName,
			$arguments,
			$confirmation,
			$callId,
			$nodeId
		);

		$this->recordPending($record);

		return $this->buildPendingResponse($record);
	}

	/**
	 * @param array<string,mixed> $arguments
	 * @return array<string,mixed>
	 */
	public function handleConfirmationTool(string $profileId, array $arguments, McpToolCatalog $catalog, IAgentContext $context): array {
		$confirmationId = trim((string)($arguments['confirmation_id'] ?? ''));
		$decision = strtolower(trim((string)($arguments['decision'] ?? '')));
		$note = trim((string)($arguments['note'] ?? ''));

		if($confirmationId === '') {
			throw new \InvalidArgumentException('Missing confirmation_id.');
		}

		if(!in_array($decision, ['accept', 'decline'], true)) {
			throw new \InvalidArgumentException('Invalid decision. Expected accept or decline.');
		}

		$record = $this->store->getPending($confirmationId, $profileId);

		if($decision === 'decline') {
			$this->store->mark($confirmationId, 'declined', [
				'decision' => 'decline',
				'note' => $note
			]);
			$this->emitAudit(
				MissionBayAgentActionAuditEvent::TYPE_APPROVAL_DENIED,
				$record,
				$note !== '' ? $note : 'Pending MCP action was declined.',
				['decision' => 'decline', 'note' => $note]
			);

			return [
				'ok' => true,
				'confirmed' => false,
				'confirmation_id' => $confirmationId,
				'status' => 'declined',
				'message' => 'Pending action was declined.'
			];
		}

		$toolName = trim((string)($record['tool'] ?? ''));
		$toolArguments = $record['arguments'] ?? [];

		if($toolName === '' || !is_array($toolArguments)) {
			$this->store->mark($confirmationId, 'invalid');
			throw new \RuntimeException('Stored confirmation action is invalid.');
		}

		$this->emitAudit(
			MissionBayAgentActionAuditEvent::TYPE_APPROVAL_GRANTED,
			$record,
			$note !== '' ? $note : 'Pending MCP action was approved.',
			['decision' => 'accept', 'note' => $note]
		);

		if(is_array($record['guarded_mutation'] ?? null)) {
			return $this->handleGuardedConfirmation($record, $note, $catalog, $context);
		}

		$previousAudit = AgentToolAuditContext::push($context, $this->buildToolAuditMetadata($record));

		try {
			$result = $catalog->call($toolName, $toolArguments, $context);
			$this->store->mark($confirmationId, 'accepted', [
				'decision' => 'accept',
				'note' => $note
			]);

			return [
				'ok' => true,
				'confirmed' => true,
				'confirmation_id' => $confirmationId,
				'status' => 'accepted',
				'tool' => $toolName,
				'result' => $result
			];
		}
		catch(\Throwable $e) {
			$this->store->mark($confirmationId, 'failed', [
				'decision' => 'accept',
				'note' => $note,
				'error' => $e->getMessage()
			]);
			throw $e;
		}
		finally {
			AgentToolAuditContext::restore($context, $previousAudit);
		}
	}

	/**
	 * @param array<string,mixed> $definition
	 * @return array<string,mixed>
	 */
	private function createGuardedPending(
		string $profileId,
		string $toolName,
		array $arguments,
		array $definition,
		McpToolCatalog $catalog,
		IAgentContext $context
	): array {
		$tool = $catalog->getTool($toolName);
		$callId = AgentToolAuditContext::generateCallId('mcp-call');
		$nodeId = $tool instanceof IAgentResource ? $tool->getId() : 'mcp_' . $this->normalizeId($profileId);
		$action = new AgentAction(
			$callId,
			AgentAction::TYPE_TOOL_CALL,
			$toolName,
			$arguments,
			[
				'iteration' => 0,
				'call_index' => 0,
				'mcp_profile_id' => $profileId
			]
		);
		$call = new AiToolCall($callId, $toolName, $arguments);
		$recordContext = [
			'call_id' => $callId,
			'node_id' => $nodeId,
			'profile' => $profileId,
			'tool' => $toolName,
			'arguments' => $arguments
		];
		$this->prepareMutationContext($recordContext, $catalog, $context);

		$snapshot = $this->mutationCommitGuard->capture($action, $call, $context);
		if(!$snapshot instanceof AgentMutationCommitSnapshot) {
			throw new \RuntimeException('MCP mutation commit snapshot could not be captured: ' . $toolName);
		}

		$review = $this->mutationCommitGuard->getActionReview($action, $call, $snapshot, $context);
		$annotations = $this->toolDefinitionSemantics->getAnnotations($definition);
		$confirmation = [
			'title' => $review->getTitle(),
			'message' => $review->getMessage(),
			'summary' => $review->getSummary(),
			'risk' => ($annotations['destructiveHint'] ?? false) === true ? 'high' : 'medium'
		];
		$record = $this->store->create(
			$profileId,
			$toolName,
			$arguments,
			$confirmation,
			$callId,
			$nodeId,
			[
				'action_fingerprint' => $snapshot->getActionFingerprint(),
				'snapshot' => $snapshot->toArray()
			]
		);

		$this->recordPending($record);

		return $this->buildPendingResponse($record);
	}

	/**
	 * @param array<string,mixed> $record
	 * @return array<string,mixed>
	 */
	private function handleGuardedConfirmation(array $record, string $note, McpToolCatalog $catalog, IAgentContext $context): array {
		$confirmationId = trim((string)($record['id'] ?? ''));
		$toolName = trim((string)($record['tool'] ?? ''));
		$toolArguments = is_array($record['arguments'] ?? null) ? $record['arguments'] : [];
		$guardedMutation = is_array($record['guarded_mutation'] ?? null) ? $record['guarded_mutation'] : [];
		$definition = $catalog->getDefinition($toolName);
		$tool = $catalog->getTool($toolName);

		if(
			!$this->toolDefinitionSemantics->isMutationDefinition($definition)
			|| !$this->toolDefinitionSemantics->requiresApprovalDefinition($definition)
			|| !$this->toolDefinitionSemantics->isCommitGuardRequired($definition)
			|| !$tool instanceof IAgentMutationGuardedTool
		) {
			$this->store->mark($confirmationId, 'blocked', [
				'decision' => 'accept',
				'note' => $note,
				'error_code' => 'mutation_commit_guard_unavailable'
			]);

			return [
				'ok' => false,
				'confirmed' => true,
				'confirmation_id' => $confirmationId,
				'status' => 'blocked',
				'error_code' => 'mutation_commit_guard_unavailable',
				'message' => 'The approved mutation no longer has its required commit guard.',
				'tool' => $toolName
			];
		}

		$snapshot = is_array($guardedMutation['snapshot'] ?? null) ? $guardedMutation['snapshot'] : [];
		$approvedFingerprint = trim((string)($guardedMutation['action_fingerprint'] ?? ''));
		$call = new AiToolCall(
			(string)($record['call_id'] ?? ''),
			$toolName,
			$toolArguments,
			[
				AgentMutationCommitGuardService::TOOL_CALL_METADATA_SNAPSHOT => $snapshot,
				AgentMutationCommitGuardService::TOOL_CALL_METADATA_APPROVAL_FINGERPRINT => $approvedFingerprint,
				AgentMutationCommitGuardService::TOOL_CALL_METADATA_INTERACTION_REQUEST => $confirmationId
			]
		);

		$this->prepareMutationContext($record, $catalog, $context);
		$previousAudit = AgentToolAuditContext::push($context, $this->buildToolAuditMetadata($record));
		$executionStarted = false;

		try {
			$commitDecision = $this->mutationCommitGuard->validate($call, $context);

			if(!$commitDecision->isAllowed()) {
				$this->store->mark($confirmationId, 'blocked', [
					'decision' => 'accept',
					'note' => $note,
					'error_code' => $commitDecision->getCode(),
					'error' => $commitDecision->getReason()
				]);

				return [
					'ok' => false,
					'confirmed' => true,
					'confirmation_id' => $confirmationId,
					'status' => 'blocked',
					'error_code' => $commitDecision->getCode(),
					'message' => $commitDecision->getReason(),
					'tool' => $toolName
				];
			}

			$executionStarted = true;
			$result = $catalog->call($toolName, $toolArguments, $context);
			$this->mutationCommitGuard->recordCommitResult(
				$call,
				$context,
				true,
				'MCP mutation committed.',
				['confirmation_id' => $confirmationId]
			);
			$this->store->mark($confirmationId, 'accepted', [
				'decision' => 'accept',
				'note' => $note
			]);

			return [
				'ok' => true,
				'confirmed' => true,
				'confirmation_id' => $confirmationId,
				'status' => 'accepted',
				'tool' => $toolName,
				'result' => $result
			];
		}
		catch(\Throwable $e) {
			if($executionStarted) {
				$this->mutationCommitGuard->recordCommitResult(
					$call,
					$context,
					false,
					$e->getMessage(),
					['confirmation_id' => $confirmationId, 'type' => get_class($e)]
				);
			}

			$this->store->mark($confirmationId, 'failed', [
				'decision' => 'accept',
				'note' => $note,
				'error' => $e->getMessage()
			]);
			throw $e;
		}
		finally {
			AgentToolAuditContext::restore($context, $previousAudit);
		}
	}

	/**
	 * @param array<string,mixed> $record
	 */
	private function prepareMutationContext(array $record, McpToolCatalog $catalog, IAgentContext $context): void {
		$toolName = trim((string)($record['tool'] ?? ''));
		$context->setVar(AgentToolLoopContextKeys::TOOL_DEFINITIONS, [$catalog->getDefinition($toolName)]);
		$context->setVar(AgentToolLoopContextKeys::TOOLS, [$catalog->getTool($toolName)]);
		$context->setVar(AgentToolLoopContextKeys::ITERATION, 0);
		$context->setVar(AgentToolLoopContextKeys::NODE_ID, (string)($record['node_id'] ?? 'mcp_tool'));
		$context->setVar(AgentToolLoopContextKeys::TRACE, $this->buildTrace($record));
	}

	/**
	 * @param array<string,mixed> $record
	 */
	private function recordPending(array $record): void {
		$this->emitAudit(
			MissionBayAgentActionAuditEvent::TYPE_APPROVAL_REQUESTED,
			$record,
			'Explicit MCP confirmation is required.',
			[
				'confirmation' => $record['confirmation'],
				'expires_at' => $record['expires_at']
			]
		);

		$this->logger->logLevel(ILogger::INFO, 'MCP confirmation created.', [
			'scope' => self::LOG_SCOPE,
			'profile' => (string)($record['profile'] ?? ''),
			'tool' => (string)($record['tool'] ?? ''),
			'confirmation_id' => (string)($record['id'] ?? ''),
			'call_id' => (string)($record['call_id'] ?? '')
		]);
	}

	/**
	 * @param array<string,mixed> $record
	 * @return array<string,mixed>
	 */
	private function buildPendingResponse(array $record): array {
		return [
			'requires_confirmation' => true,
			'confirmation_id' => $record['id'],
			'tool' => $record['tool'],
			'title' => $record['confirmation']['title'] ?? 'Confirm tool call',
			'message' => $record['confirmation']['message'] ?? '',
			'summary' => $record['confirmation']['summary'] ?? [],
			'risk' => $record['confirmation']['risk'] ?? 'medium',
			'expires_at' => $record['expires_at'],
			'next_tool' => self::TOOL_NAME
		];
	}

	/**
	 * @param array<string,mixed> $record
	 * @param array<string,mixed> $metadata
	 */
	private function emitAudit(string $type, array $record, string $reason, array $metadata = []): void {
		$callId = trim((string)($record['call_id'] ?? ''));
		$toolName = trim((string)($record['tool'] ?? ''));
		$arguments = is_array($record['arguments'] ?? null) ? $record['arguments'] : [];

		if($callId === '' || $toolName === '') {
			return;
		}

		$action = new AgentAction(
			$callId,
			AgentAction::TYPE_TOOL_CALL,
			$toolName,
			$arguments,
			[
				'iteration' => 0,
				'call_index' => 0,
				'confirmation_id' => (string)($record['id'] ?? '')
			]
		);

		if (!$this->eventManager instanceof IEventManager) {
			return;
		}

		try {
			$this->eventManager->fire(new MissionBayAgentActionAuditEvent(
				$type,
				$action,
				$reason,
				$this->buildTrace($record),
				array_replace([
					'confirmation_id' => (string)($record['id'] ?? ''),
					'mcp_profile_id' => (string)($record['profile'] ?? '')
				], $metadata)
			));
		}
		catch(\Throwable) {
		}
	}

	/**
	 * @param array<string,mixed> $record
	 * @return array<string,mixed>
	 */
	private function buildToolAuditMetadata(array $record): array {
		return [
			'source' => AgentToolAuditContext::SOURCE_MCP,
			'node_id' => (string)($record['node_id'] ?? 'mcp_tool'),
			'call_id' => (string)($record['call_id'] ?? AgentToolAuditContext::generateCallId('mcp-call')),
			'label' => (string)($record['tool'] ?? ''),
			'iteration' => 0,
			'call_index' => 0,
			'trace' => $this->buildTrace($record)
		];
	}

	/**
	 * @param array<string,mixed> $record
	 * @return array<string,mixed>
	 */
	private function buildTrace(array $record): array {
		$profileId = trim((string)($record['profile'] ?? ''));
		$callId = trim((string)($record['call_id'] ?? ''));

		return [
			'source' => AgentToolAuditContext::SOURCE_MCP,
			'node_id' => (string)($record['node_id'] ?? 'mcp_tool'),
			'turn_id' => $callId !== '' ? $callId : 'unknown_turn',
			'chatbot_key' => $profileId !== '' ? 'mcp:' . $profileId : 'mcp',
			'config_group' => 'mcp',
			'config_name' => $profileId !== '' ? $profileId : 'unknown_profile',
			'mcp_profile_id' => $profileId,
			'confirmation_id' => (string)($record['id'] ?? '')
		];
	}

	private function normalizeId(string $value): string {
		$value = (string)preg_replace('/[^A-Za-z0-9_]+/', '_', trim($value));
		$value = trim($value, '_');
		return $value !== '' ? strtolower($value) : 'profile';
	}
}
