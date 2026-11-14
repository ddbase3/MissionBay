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

final class ApprovalPreflightRejectingMutationTool implements IAgentTool, IAgentMutationGuardedTool {
	private int $callCount = 0;

	public static function getName(): string { return 'approvalpreflightrejectingmutationtool'; }

	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Update missing course',
			'annotations' => ['readOnlyHint' => false, 'commitGuardRequired' => true],
			'function' => [
				'name' => 'update_missing_course',
				'description' => 'Updates one course.',
				'parameters' => [
					'type' => 'object',
					'properties' => ['ref_id' => ['type' => 'integer']]
				]
			]
		]];
	}

	public function captureMutationCommitSnapshot(
		AgentAction $action,
		string $actionFingerprint,
		IAgentContext $context
	): AgentMutationCommitSnapshot {
		throw new \InvalidArgumentException('No active course exists for ref_id 404.');
	}

	public function getActionReview(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context
	): AgentActionReview {
		throw new \LogicException('Action review must not be reached after a rejected preflight.');
	}

	public function validateMutationCommit(
		AgentAction $action,
		AgentMutationCommitSnapshot $snapshot,
		IAgentContext $context
	): AgentMutationCommitDecision {
		return AgentMutationCommitDecision::allow('Test mutation remains valid.');
	}

	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		$this->callCount++;
		return ['updated' => true];
	}

	public function getCallCount(): int { return $this->callCount; }
}
