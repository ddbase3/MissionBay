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

namespace MissionBay\Orchestrator\Service;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentMutationCommitDecision;
use AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use AssistantFoundation\Dto\AiToolCall;
use Base3\Event\Api\IEventManager;
use MissionBay\Api\IAgentMutationGuardedTool;
use MissionBay\Api\IAgentTool;
use MissionBay\Event\MissionBayAgentActionAuditEvent;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;

/** Captures and validates mutation preconditions without becoming a pipeline stage. */
final class AgentMutationCommitGuardService {

	public const TOOL_CALL_METADATA_SNAPSHOT = 'mutation_commit_snapshot';
	public const TOOL_CALL_METADATA_APPROVAL_FINGERPRINT = 'approved_action_fingerprint';
	public const TOOL_CALL_METADATA_INTERACTION_REQUEST = 'approved_interaction_request_id';

	public function __construct(
		private readonly AgentActionFingerprint $fingerprint,
		private readonly ?IEventManager $eventManager = null
	) {}

	public function capture(
		AgentAction $action,
		AiToolCall $call,
		IAgentContext $context
	): ?AgentMutationCommitSnapshot {
		$definition = $this->findToolDefinition($call->getName(), $context);
		if (!$this->isMutationDefinition($definition) || !$this->isCommitGuardRequired($definition)) {
			return null;
		}

		$tool = $this->findTool($call->getName(), $context);
		if (!$tool instanceof IAgentMutationGuardedTool) {
			if ($this->isCommitGuardRequired($definition)) {
				throw new \RuntimeException(
					'Mutation tool requires a commit guard but does not implement IAgentMutationGuardedTool: ' . $call->getName()
				);
			}
			return null;
		}

		$fingerprint = $this->fingerprint->create($action);
		$snapshot = $tool->captureMutationCommitSnapshot(
			$action,
			$fingerprint,
			$context
		);
		if ($snapshot->getActionId() !== $action->getId()) {
			throw new \RuntimeException('Mutation commit snapshot belongs to a different action id.');
		}
		if (!hash_equals($fingerprint, $snapshot->getActionFingerprint())) {
			throw new \RuntimeException('Mutation commit snapshot fingerprint does not match the reviewed action.');
		}

		return $snapshot;
	}

	public function isMutation(AiToolCall $call, IAgentContext $context): bool {
		return $this->isMutationDefinition($this->findToolDefinition($call->getName(), $context));
	}


	public function validate(AiToolCall $call, IAgentContext $context): AgentMutationCommitDecision {
		$definition = $this->findToolDefinition($call->getName(), $context);
		if (!$this->isMutationDefinition($definition)) {
			return AgentMutationCommitDecision::allow('Tool definition is read-only.');
		}

		$action = $this->createAction($call, $context);
		if (!$this->isCommitGuardRequired($definition)) {
			$decision = AgentMutationCommitDecision::allow(
				'Mutation tool explicitly opts out of commit snapshot validation.',
				['guarded' => false]
			);
			$this->emitAudit(MissionBayAgentActionAuditEvent::TYPE_COMMIT_ALLOWED, $action, $decision, $context);
			return $decision;
		}

		$metadata = $call->getMetadata();
		$approvedFingerprint = trim((string)($metadata[self::TOOL_CALL_METADATA_APPROVAL_FINGERPRINT] ?? ''));
		$currentFingerprint = $this->fingerprint->create($action);
		if ($approvedFingerprint === '' || !hash_equals($approvedFingerprint, $currentFingerprint)) {
			return $this->deny(
				$action,
				AgentMutationCommitDecision::CODE_INVALID_SNAPSHOT,
				'Mutation execution is not bound to the exact action approved by the user.',
				$context
			);
		}

		$snapshotData = $metadata[self::TOOL_CALL_METADATA_SNAPSHOT] ?? null;
		$tool = $this->findTool($call->getName(), $context);
		if (!$tool instanceof IAgentMutationGuardedTool) {
			if ($this->isCommitGuardRequired($definition)) {
				return $this->deny(
					$action,
					AgentMutationCommitDecision::CODE_GUARD_UNAVAILABLE,
					'Mutation commit guard is required but unavailable for this tool.',
					$context
				);
			}

			$decision = AgentMutationCommitDecision::allow(
				'Legacy mutation tool explicitly opted out of commit snapshot validation.',
				['guarded' => false]
			);
			$this->emitAudit(MissionBayAgentActionAuditEvent::TYPE_COMMIT_ALLOWED, $action, $decision, $context);
			return $decision;
		}

		if (!is_array($snapshotData)) {
			return $this->deny(
				$action,
				AgentMutationCommitDecision::CODE_INVALID_SNAPSHOT,
				'Mutation commit snapshot is missing.',
				$context
			);
		}

		try {
			$snapshot = AgentMutationCommitSnapshot::fromArray($snapshotData);
		} catch (\Throwable $e) {
			return $this->deny(
				$action,
				AgentMutationCommitDecision::CODE_INVALID_SNAPSHOT,
				'Mutation commit snapshot is invalid.',
				$context,
				['type' => get_class($e), 'message' => $e->getMessage()]
			);
		}

		if (
			$snapshot->getActionId() !== $action->getId()
			|| !hash_equals($snapshot->getActionFingerprint(), $currentFingerprint)
		) {
			return $this->deny(
				$action,
				AgentMutationCommitDecision::CODE_INVALID_SNAPSHOT,
				'Mutation commit snapshot no longer matches the approved action.',
				$context
			);
		}

		try {
			$decision = $tool->validateMutationCommit(
				$action,
				$snapshot,
				$context
			);
		} catch (\Throwable $e) {
			$decision = AgentMutationCommitDecision::deny(
				AgentMutationCommitDecision::CODE_REJECTED,
				'Mutation commit guard failed: ' . $e->getMessage(),
				['type' => get_class($e)]
			);
		}

		$this->emitAudit(
			$decision->isAllowed()
				? MissionBayAgentActionAuditEvent::TYPE_COMMIT_ALLOWED
				: MissionBayAgentActionAuditEvent::TYPE_COMMIT_BLOCKED,
			$action,
			$decision,
			$context
		);

		return $decision;
	}

	/** @param array<string,mixed> $metadata */
	public function recordCommitResult(
		AiToolCall $call,
		IAgentContext $context,
		bool $succeeded,
		string $reason = '',
		array $metadata = []
	): void {
		if (!$this->isMutation($call, $context)) {
			return;
		}
		$action = $this->createAction($call, $context);
		if (!$this->eventManager instanceof IEventManager) {
			return;
		}
		$trace = $this->buildAuditTrace($context);
		try {
			$this->eventManager->fire(new MissionBayAgentActionAuditEvent(
				$succeeded
					? MissionBayAgentActionAuditEvent::TYPE_COMMIT_SUCCEEDED
					: MissionBayAgentActionAuditEvent::TYPE_COMMIT_FAILED,
				$action,
				$reason,
				$trace,
				$metadata
			));
		} catch (\Throwable) {
		}
	}

	private function createAction(AiToolCall $call, IAgentContext $context): AgentAction {
		return new AgentAction(
			trim($call->getId()),
			AgentAction::TYPE_TOOL_CALL,
			trim($call->getName()),
			$call->getArguments(),
			[
				'iteration' => (int)($context->getVar(AgentToolLoopContextKeys::ITERATION) ?? 0),
				'tool_call' => $call->getMetadata()
			]
		);
	}

	private function deny(
		AgentAction $action,
		string $code,
		string $reason,
		IAgentContext $context,
		array $metadata = []
	): AgentMutationCommitDecision {
		$decision = AgentMutationCommitDecision::deny($code, $reason, $metadata);
		$this->emitAudit(MissionBayAgentActionAuditEvent::TYPE_COMMIT_BLOCKED, $action, $decision, $context);
		return $decision;
	}

	/** @return ?array<string,mixed> */
	private function findToolDefinition(string $toolName, IAgentContext $context): ?array {
		$definitions = $context->getVar(AgentToolLoopContextKeys::TOOL_DEFINITIONS);
		if (!is_array($definitions)) {
			return null;
		}
		foreach ($definitions as $definition) {
			if (!is_array($definition)) {
				continue;
			}
			$function = is_array($definition['function'] ?? null) ? $definition['function'] : $definition;
			if (trim((string)($function['name'] ?? '')) === $toolName) {
				return $definition;
			}
		}
		return null;
	}

	private function findTool(string $toolName, IAgentContext $context): ?IAgentTool {
		$tools = $context->getVar(AgentToolLoopContextKeys::TOOLS);
		if (!is_array($tools)) {
			return null;
		}
		foreach ($tools as $tool) {
			if (!$tool instanceof IAgentTool) {
				continue;
			}
			foreach ($tool->getToolDefinitions() as $definition) {
				if (!is_array($definition)) {
					continue;
				}
				$function = is_array($definition['function'] ?? null) ? $definition['function'] : $definition;
				if (trim((string)($function['name'] ?? '')) === $toolName) {
					return $tool;
				}
			}
		}
		return null;
	}

	/** @param ?array<string,mixed> $definition */
	private function isMutationDefinition(?array $definition): bool {
		if ($definition === null) {
			return false;
		}
		$annotations = $this->readAnnotations($definition);
		foreach ([
			'destructiveHint', 'destructive', 'requiresApproval', 'requires_confirmation',
			'requiresConfirmation', 'mutation', 'sideEffectHint', 'side_effect'
		] as $key) {
			if (($annotations[$key] ?? false) === true) {
				return true;
			}
		}
		foreach (['readOnlyHint', 'read_only', 'readonly'] as $key) {
			if (array_key_exists($key, $annotations) && $annotations[$key] === false) {
				return true;
			}
		}
		return false;
	}

	/** @param ?array<string,mixed> $definition */
	private function isCommitGuardRequired(?array $definition): bool {
		if ($definition === null) {
			return false;
		}
		$annotations = $this->readAnnotations($definition);
		if (array_key_exists('commitGuardRequired', $annotations)) {
			return $annotations['commitGuardRequired'] === true;
		}
		if (array_key_exists('commit_guard_required', $annotations)) {
			return $annotations['commit_guard_required'] === true;
		}
		return true;
	}

	/** @param array<string,mixed> $definition @return array<string,mixed> */
	private function readAnnotations(array $definition): array {
		$function = is_array($definition['function'] ?? null) ? $definition['function'] : [];
		$annotations = is_array($definition['annotations'] ?? null) ? $definition['annotations'] : [];
		if (is_array($function['annotations'] ?? null)) {
			$annotations = array_merge($annotations, $function['annotations']);
		}
		foreach ([
			'readOnlyHint', 'read_only', 'readonly', 'destructiveHint', 'destructive',
			'requiresApproval', 'requires_confirmation', 'requiresConfirmation', 'mutation',
			'sideEffectHint', 'side_effect', 'commitGuardRequired', 'commit_guard_required'
		] as $key) {
			if (array_key_exists($key, $definition)) {
				$annotations[$key] = $definition[$key];
			}
			if (array_key_exists($key, $function)) {
				$annotations[$key] = $function[$key];
			}
		}
		return $annotations;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function buildAuditTrace(IAgentContext $context): array {
		$trace = $context->getVar(AgentToolLoopContextKeys::TRACE);
		$trace = is_array($trace) ? $trace : [];
		$trace['source'] = 'agent';

		$nodeId = trim((string)($context->getVar(AgentToolLoopContextKeys::NODE_ID) ?? ''));
		if ($nodeId !== '') {
			$trace['node_id'] = $nodeId;
		}

		return $trace;
	}

	private function emitAudit(
		string $type,
		AgentAction $action,
		AgentMutationCommitDecision $decision,
		IAgentContext $context
	): void {
		if (!$this->eventManager instanceof IEventManager) {
			return;
		}
		$trace = $this->buildAuditTrace($context);
		try {
			$this->eventManager->fire(new MissionBayAgentActionAuditEvent(
				$type,
				$action,
				$decision->getReason(),
				$trace,
				['commit_decision' => $decision->toArray()]
			));
		} catch (\Throwable) {
		}
	}
}
