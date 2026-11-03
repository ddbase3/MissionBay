<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionReview;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentMutationCommitDecision;
use AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use AssistantFoundation\Dto\AgentInteractionResponse;
use AssistantFoundation\Dto\AgentResume;
use AssistantFoundation\Dto\AgentSuspensionClaim;
use AssistantFoundation\Exception\AgentSuspensionRepositoryException;
use Base3\Event\Api\IEventManager;
use Base3\State\Api\IStateStore;
use MissionBay\Api\IAgentMutationGuardedTool;
use MissionBay\Api\IAgentTool;
use MissionBay\ChatModel\NormalizedChatModelTrait;
use MissionBay\Context\AgentContext;
use MissionBay\Event\MissionBayAgentActionAuditEvent;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\AgentToolOrchestrator;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use MissionBay\Orchestrator\Service\AgentActionResumeService;
use MissionBay\Orchestrator\Service\AgentActionReviewService;
use MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use AssistantRuntime\Service\StateStoreAgentSuspensionRepository;
use MissionBay\Policy\AllowAllAgentActionPolicy;
use MissionBay\Policy\MutationApprovalAgentActionPolicy;
use PHPUnit\Framework\TestCase;

final class ApprovalMutationTool implements IAgentTool, IAgentMutationGuardedTool {
	private int $callCount = 0;
	private int $version = 1;
	private bool $authorized = true;
	/** @var array<string,mixed> */
	private array $lastArguments = [];
	public static function getName(): string { return 'approvalmutationtool'; }
	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Update record',
			'annotations' => ['readOnlyHint' => false, 'commitGuardRequired' => true],
			'function' => [
				'name' => 'update_record',
				'description' => 'Updates one record.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'id' => ['type' => 'integer'],
						'title' => ['type' => 'string']
					]
				]
			]
		]];
	}
	public function captureMutationCommitSnapshot(
		AgentAction $action,
		string $actionFingerprint,
		IAgentContext $context
	): AgentMutationCommitSnapshot {
		$id = (string)($action->getInput()['id'] ?? '');
		return new AgentMutationCommitSnapshot(
			$action->getId(),
			$actionFingerprint,
			['subject' => 'test-user'],
			['record:' . $id => (string)$this->version],
			gmdate('c')
		);
	}
	public function getActionReview(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context
	): AgentActionReview {
		return new AgentActionReview(
			'Update record',
			'Record ' . (string)($action->getInput()['id'] ?? '') . ' will be updated.',
			[
				'Record' => (string)($action->getInput()['id'] ?? ''),
				'New title' => (string)($action->getInput()['title'] ?? '')
			]
		);
	}

	public function validateMutationCommit(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context
	): AgentMutationCommitDecision {
		if (!$this->authorized) {
			return AgentMutationCommitDecision::deny(
				AgentMutationCommitDecision::CODE_UNAUTHORIZED,
				'Current user is no longer authorized to update this record.'
			);
		}
		$id = (string)($action->getInput()['id'] ?? '');
		$expected = (string)($snapshot->getResourceVersions()['record:' . $id] ?? '');
		if ($expected === '' || $expected !== (string)$this->version) {
			return AgentMutationCommitDecision::deny(
				AgentMutationCommitDecision::CODE_STALE,
				'Record version changed after approval.',
				['expected' => $expected, 'current' => (string)$this->version]
			);
		}
		return AgentMutationCommitDecision::allow('Authorization and record version are still valid.');
	}
	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		$this->callCount++;
		$this->lastArguments = $arguments;
		$this->version++;
		return ['updated' => true, 'record' => $arguments, 'version' => $this->version];
	}
	public function getCallCount(): int { return $this->callCount; }
	/** @return array<string,mixed> */
	public function getLastArguments(): array { return $this->lastArguments; }
	public function setVersion(int $version): void { $this->version = $version; }
	public function setAuthorized(bool $authorized): void { $this->authorized = $authorized; }
}
