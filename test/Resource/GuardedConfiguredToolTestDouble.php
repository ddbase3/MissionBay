<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionReview;
use AssistantFoundation\Dto\AgentMutationCommitDecision;
use AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use AssistantFoundation\Dto\AiToolCall;
use Base3\Event\Api\IEventManager;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Api\IAgentMutationGuardedTool;
use MissionBay\Api\IAgentTool;
use MissionBay\Audit\AgentToolAuditContext;
use MissionBay\Context\AgentContext;
use MissionBay\Event\MissionBayToolFailedEvent;
use MissionBay\Event\MissionBayToolFinishedEvent;
use MissionBay\Event\MissionBayToolStartedEvent;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use MissionBay\Resource\ConfiguredAgentToolResource;
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
