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

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAiChatModel;
use Base3\AssistantFoundation\Dto\AiChatResult;
use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentActionReview;
use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\AssistantFoundation\Dto\AgentMutationCommitDecision;
use Base3\AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use Base3\AssistantFoundation\Dto\AgentInteractionResponse;
use Base3\AssistantFoundation\Dto\AgentResume;
use Base3\AssistantFoundation\Dto\AgentSuspensionClaim;
use Base3\AssistantFoundation\Exception\AgentSuspensionRepositoryException;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\CoreFoundation\State\Api\IStateStore;
use Base3\MissionBay\Api\IAgentMutationGuardedTool;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\ChatModel\NormalizedChatModelTrait;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Event\MissionBayAgentActionAuditEvent;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\AgentToolOrchestrator;
use Base3\MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use Base3\MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use Base3\MissionBay\Orchestrator\Service\AgentActionResumeService;
use Base3\MissionBay\Orchestrator\Service\AgentActionReviewService;
use Base3\MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use Base3\MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use Base3\MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use Base3\AssistantRuntime\Service\StateStoreAgentSuspensionRepository;
use Base3\MissionBay\Policy\AllowAllAgentActionPolicy;
use Base3\MissionBay\Policy\MutationApprovalAgentActionPolicy;
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
