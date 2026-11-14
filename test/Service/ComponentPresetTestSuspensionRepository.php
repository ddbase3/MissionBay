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

namespace Base3\MissionBay\Test\Service;

use Base3\AssistantFoundation\Api\IAgentActionPolicy;
use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Api\IAgentSuspensionRepository;
use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentActionReview;
use Base3\AssistantFoundation\Dto\AgentMutationCommitDecision;
use Base3\AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use Base3\AssistantFoundation\Dto\AgentSuspension;
use Base3\AssistantFoundation\Dto\AgentSuspensionClaim;
use Base3\AssistantFoundation\Dto\AgentSuspensionResolution;
use Base3\AssistantFoundation\Dto\AgentSuspensionState;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\MissionBay\Api\IAgentMutationGuardedTool;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\Policy\IAgentActionPolicyResolver;
use Base3\MissionBay\Orchestrator\Service\AgentActionResumeService;
use Base3\MissionBay\Orchestrator\Service\AgentActionReviewService;
use Base3\MissionBay\Orchestrator\Service\AgentCapabilitySelectionGuardService;
use Base3\MissionBay\Orchestrator\Service\AgentInteractionResponseResolver;
use Base3\MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use Base3\MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use Base3\MissionBay\Orchestrator\Service\AgentToolDefinitionSemantics;
use Base3\MissionBay\Orchestrator\Validation\JsonSchemaValidator;
use Base3\MissionBay\Policy\AllowAllAgentActionPolicy;
use Base3\MissionBay\Policy\MutationApprovalAgentActionPolicy;
use Base3\MissionBay\Service\AgentComponentPresetToolTestService;
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
