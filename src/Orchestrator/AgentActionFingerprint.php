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

namespace Base3\MissionBay\Orchestrator;

use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentSuspensionScope;
use Base3\AssistantFoundation\Api\IAgentContext;

/** Binds approval to an exact canonical action payload. */
final class AgentActionFingerprint {

	public function create(AgentAction $action): string {
		$payload = [
			'type' => $action->getType(),
			'name' => $action->getName(),
			'input' => $this->canonicalize($action->getInput())
		];
		$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (!is_string($json)) {
			throw new \RuntimeException('Agent action could not be serialized for approval fingerprinting.');
		}
		return hash('sha256', $json);
	}

	/**
	 * Canonical identity of one complete declared tool, including its provider
	 * information, contract and safety annotations. A definition change
	 * invalidates all older reusable approvals for that tool.
	 *
	 * @param array<string,mixed> $definition
	 */
	public function createToolDefinitionFingerprint(array $definition): string {
		$json = json_encode($this->canonicalize($definition), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		if (!is_string($json)) {
			throw new \RuntimeException('Agent tool definition cannot be serialized for approval fingerprinting.');
		}
		return hash('sha256', $json);
	}

	/**
	 * Uses the existing authenticated suspension scope, also when inherited by a
	 * sub-agent. No new conversation identifier or permission model is created.
	 */
	public function conversationApprovalScope(IAgentContext $context): string {
		$inherited = $context->getVar('approval_conversation_scope');
		if (is_string($inherited) && preg_match('/^conversation:[a-f0-9]{64}$/D', $inherited) === 1) {
			return $inherited;
		}
		return AgentSuspensionScope::forConversation(
			trim((string)($context->getVar('conversation_channel_id') ?? '')),
			trim((string)($context->getVar('conversation_id') ?? '')),
			trim((string)($context->getVar('agent_suspension_subject') ?? ''))
		);
	}

	/** Stable prefix for approvals owned by the existing StateStore. */
	public function conversationApprovalPrefix(string $scope): string {
		if (preg_match('/^conversation:[a-f0-9]{64}$/D', $scope) !== 1) {
			return '';
		}
		return 'missionbay.hitl.conversation_approval.' . substr($scope, strlen('conversation:')) . '.';
	}

	public function conversationApprovalKey(string $scope, string $toolName): string {
		$prefix = $this->conversationApprovalPrefix($scope);
		$toolName = trim($toolName);
		return $prefix !== '' && $toolName !== ''
			? $prefix . hash('sha256', $toolName)
			: '';
	}

	private function canonicalize(mixed $value): mixed {
		if (!is_array($value)) {
			return $value;
		}
		if (array_is_list($value)) {
			return array_map(fn(mixed $entry): mixed => $this->canonicalize($entry), $value);
		}
		ksort($value, SORT_STRING);
		foreach ($value as $key => $entry) {
			$value[$key] = $this->canonicalize($entry);
		}
		return $value;
	}
}
