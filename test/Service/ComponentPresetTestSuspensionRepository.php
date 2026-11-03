<?php declare(strict_types=1);

namespace MissionBay\Test\Service;

use AssistantFoundation\Api\IAgentActionPolicy;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAgentSuspensionRepository;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionReview;
use AssistantFoundation\Dto\AgentMutationCommitDecision;
use AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use AssistantFoundation\Dto\AgentSuspension;
use AssistantFoundation\Dto\AgentSuspensionClaim;
use AssistantFoundation\Dto\AgentSuspensionResolution;
use AssistantFoundation\Dto\AgentSuspensionState;
use Base3\Event\Api\IEventManager;
use MissionBay\Api\IAgentMutationGuardedTool;
use MissionBay\Api\IAgentTool;
use MissionBay\Context\AgentContext;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Policy\IAgentActionPolicyResolver;
use MissionBay\Orchestrator\Service\AgentActionResumeService;
use MissionBay\Orchestrator\Service\AgentActionReviewService;
use MissionBay\Orchestrator\Service\AgentCapabilitySelectionGuardService;
use MissionBay\Orchestrator\Service\AgentInteractionResponseResolver;
use MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use MissionBay\Orchestrator\Validation\JsonSchemaValidator;
use MissionBay\Policy\AllowAllAgentActionPolicy;
use MissionBay\Policy\MutationApprovalAgentActionPolicy;
use MissionBay\Service\AgentComponentPresetToolTestService;
use PHPUnit\Framework\TestCase;

final class ComponentPresetTestSuspensionRepository implements IAgentSuspensionRepository {

	/** @var array<string,AgentSuspension> */
	private array $suspensions = [];

	public function create(AgentSuspension $suspension, int $ttlSeconds): string {
		$handle = 'component-preset-test-' . count($this->suspensions);
		$this->suspensions[$handle] = $suspension;
		return $handle;
	}

	public function findPending(string $scopeId): ?AgentSuspensionState {
		foreach ($this->suspensions as $handle => $suspension) {
			if ($suspension->getScopeId() === $scopeId) {
				return new AgentSuspensionState(
					true,
					$suspension->getStatus(),
					$suspension->getRequests(),
					$handle
				);
			}
		}
		return null;
	}

	public function findAll(string $scopeId): array {
		$pending = $this->findPending($scopeId);
		return $pending !== null ? [$pending] : [];
	}

	public function claim(string $resumeHandle): AgentSuspensionClaim {
		if(!isset($this->suspensions[$resumeHandle])) {
			throw new \RuntimeException('Unknown resume handle.');
		}

		return new AgentSuspensionClaim(
			$resumeHandle,
			'component-preset-test-claim',
			$this->suspensions[$resumeHandle]
		);
	}

	public function release(AgentSuspensionClaim $claim): void {
	}

	public function consume(AgentSuspensionClaim $claim, ?AgentSuspensionResolution $resolution = null): void {
		unset($this->suspensions[$claim->getResumeHandle()]);
	}
}
