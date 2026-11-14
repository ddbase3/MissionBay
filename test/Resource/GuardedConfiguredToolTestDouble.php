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

namespace Base3\MissionBay\Test\Resource;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentActionReview;
use Base3\AssistantFoundation\Dto\AgentMutationCommitDecision;
use Base3\AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\MissionBay\Api\IAgentMutationGuardedTool;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Audit\AgentToolAuditContext;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Event\MissionBayToolFailedEvent;
use Base3\MissionBay\Event\MissionBayToolFinishedEvent;
use Base3\MissionBay\Event\MissionBayToolStartedEvent;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use Base3\MissionBay\Resource\ConfiguredAgentToolResource;
use PHPUnit\Framework\TestCase;

final class GuardedConfiguredToolTestDouble implements IAgentTool, IAgentMutationGuardedTool {

	private string $capturedActionName = '';
	private string $validatedActionName = '';
	private string $reviewedActionName = '';
	private string $calledToolName = '';

	public static function getName(): string {
		return 'guardedconfiguredtooltestdouble';
	}

	public function getDescription(): string {
		return 'Guarded configured tool test double.';
	}

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'mutation' => true,
			'requiresApproval' => true,
			'commitGuardRequired' => true,
			'function' => [
				'name' => 'set_user_pref',
				'description' => 'Sets a user preference.',
				'parameters' => [
					'type' => 'object',
					'properties' => [],
					'required' => []
				]
			]
		]];
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		$this->calledToolName = $name;
		return ['ok' => true];
	}

	public function captureMutationCommitSnapshot(
		AgentAction $action,
		string $actionFingerprint,
		IAgentContext $context
	): AgentMutationCommitSnapshot {
		$this->capturedActionName = $action->getName();

		return new AgentMutationCommitSnapshot(
			$action->getId(),
			$actionFingerprint,
			[],
			[],
			metadata: ['review' => ['operation' => 'Set user preference']]
		);
	}

	public function getActionReview(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context
	): AgentActionReview {
		$this->reviewedActionName = $action->getName();
		return new AgentActionReview(
			'Set user preference',
			'The user preference will be changed.',
			['Preference' => (string)($action->getInput()['key'] ?? '')]
		);
	}

	public function validateMutationCommit(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context
	): AgentMutationCommitDecision {
		$this->validatedActionName = $action->getName();
		return AgentMutationCommitDecision::allow('Guard forwarded.');
	}

	public function getCapturedActionName(): string {
		return $this->capturedActionName;
	}

	public function getReviewedActionName(): string {
		return $this->reviewedActionName;
	}

	public function getValidatedActionName(): string {
		return $this->validatedActionName;
	}

	public function getCalledToolName(): string {
		return $this->calledToolName;
	}
}
