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

namespace Base3\MissionBay\Policy;

use Base3\AssistantFoundation\Api\IAgentActionPolicy;
use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentConfigurationService;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\CoreFoundation\State\Api\IStateStore;
use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentActionDecision;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;

/** Requires explicit approval for tool definitions declaring side effects. */
final class MutationApprovalAgentActionPolicy implements IAgentActionPolicy {

	public function __construct(
		private readonly IAgentConfigurationService $agentConfigurations,
		private readonly AgentActionFingerprint $fingerprint,
		private readonly IStateStore $stateStore,
		private readonly string $id = 'mutation-approval-actions',
		private readonly string $policyName = 'mutation-approval-actions'
	) {}

	public static function getName(): string { return 'mutationapprovalagentactionpolicy'; }
	public function id(): string { return $this->id; }
	public function name(): string { return $this->policyName; }
	public function getDescription(): string {
		return 'Requires explicit user approval for tool calls whose definitions declare mutation or destructive side effects.';
	}
	public function getAiUsage(): string { return IAgentActionPolicy::AI_USAGE_NONE; }

	public function evaluate(AgentAction $action, IAgentContext $context): AgentActionDecision {
		if ($action->getType() !== AgentAction::TYPE_TOOL_CALL) {
			return AgentActionDecision::allow($action->getId(), 'Mutation approval policy applies only to tool calls.');
		}
		$definition = $this->findToolDefinition($action->getName(), $context);
		if ($definition === null) {
			return AgentActionDecision::allow($action->getId(), 'No matching tool definition annotation was available.');
		}
		$annotations = $this->readAnnotations($definition);
		if (!$this->requiresApproval($annotations)) {
			return AgentActionDecision::allow($action->getId(), 'Tool definition does not declare a mutation requiring approval.');
		}

		$definitionFingerprint = $this->fingerprint->createToolDefinitionFingerprint($definition);
		$agentId = trim((string)($context->getVar('agent_id') ?? ''));
		if ($definitionFingerprint !== '' && $agentId !== '' ) {
			$settings = $this->agentConfigurations->getAgent($agentId);
			$approvals = is_array($settings['tool_approval_grants'] ?? null) ? $settings['tool_approval_grants'] : [];
			$approval = $approvals[$action->getName()] ?? null;
			if (is_array($approval)
				&& ($approval['scope'] ?? '') === 'agent'
				&& is_string($approval['fingerprint'] ?? null)
				&& hash_equals($approval['fingerprint'], $definitionFingerprint)) {
				return AgentActionDecision::allow($action->getId(), 'Authorized agent-scoped tool approval matches the current tool definition.', [
					'approval_scope' => 'agent',
					'tool_definition_fingerprint' => $definitionFingerprint
				]);
			}
		}

		// A chat approval is limited to one verified conversation and one complete
		// tool definition. Every other policy and native permission still runs.
		$scope = $this->fingerprint->conversationApprovalScope($context);
		$key = $this->fingerprint->conversationApprovalKey($scope, $action->getName());
		if ($key !== '' && $definitionFingerprint !== '') {
			$grant = $this->stateStore->get($key);
			if (is_array($grant)
				&& ($grant['scope'] ?? '') === 'conversation'
				&& ($grant['tool'] ?? '') === $action->getName()
				&& is_string($grant['fingerprint'] ?? null)
				&& hash_equals($grant['fingerprint'], $definitionFingerprint)) {
				return AgentActionDecision::allow($action->getId(), 'A user-approved chat tool definition matches this conversation.', [
					'approval_scope' => 'conversation',
					'tool_definition_fingerprint' => $definitionFingerprint
				]);
			}
		}

		$risk = ($annotations['destructiveHint'] ?? $annotations['destructive'] ?? false) === true
			? 'high'
			: 'medium';

		return AgentActionDecision::requireApproval(
			$action->getId(),
			'This tool call may change data and requires explicit approval before execution.',
			[
				'interaction' => [
					'risk' => $risk
				],
				'tool_annotations' => $annotations,
				'tool_definition_fingerprint' => $definitionFingerprint
			]
		);
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

	/** @param array<string,mixed> $definition @return array<string,mixed> */
	private function readAnnotations(array $definition): array {
		$function = is_array($definition['function'] ?? null) ? $definition['function'] : [];
		$annotations = is_array($definition['annotations'] ?? null) ? $definition['annotations'] : [];
		if (is_array($function['annotations'] ?? null)) {
			$annotations = array_merge($annotations, $function['annotations']);
		}
		foreach ([
			'readOnlyHint', 'read_only', 'readonly', 'destructiveHint', 'destructive',
			'requiresApproval', 'requires_confirmation', 'requiresConfirmation',
			'mutation', 'sideEffectHint', 'side_effect'
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

	/** @param array<string,mixed> $annotations */
	private function requiresApproval(array $annotations): bool {
		foreach (['destructiveHint', 'destructive'] as $key) {
			if (($annotations[$key] ?? false) === true) {
				return true;
			}
		}

		// Trusted tool definitions may explicitly opt an internal state write out
		// of user approval. Destructive annotations always win above.
		if (array_key_exists('requiresApproval', $annotations)) {
			return $annotations['requiresApproval'] === true;
		}

		foreach ([
			'requires_confirmation', 'requiresConfirmation', 'mutation',
			'sideEffectHint', 'side_effect'
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
}
