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

final class AgentComponentPresetToolTestServiceTest extends TestCase {

	public function testMutationIsSuspendedAndExecutesExactlyOnceAfterApproval(): void {
		$tool = new ComponentPresetApprovalMutationTool();
		$service = $this->createService();
		$first = $service->invoke(
			$tool,
			'set_value',
			['value' => 'A'],
			new AgentContext()
		);

		$this->assertSame('confirmation_required', $first['status'] ?? null);
		$this->assertTrue($first['requires_confirmation'] ?? false);
		$this->assertSame(0, $tool->getCallCount());

		$request = $first['interaction_requests'][0] ?? [];
		$this->assertSame('Change test value', $request['title'] ?? null);
		$this->assertSame(['New value' => 'A'], $request['summary'] ?? null);
		$this->assertSame('set_value', $request['action']['name'] ?? null);
		$approved = $service->resume(
			$tool,
			(string)($first['resume_handle'] ?? ''),
			(string)($request['id'] ?? ''),
			'approve',
			'',
			new AgentContext()
		);

		$this->assertTrue($approved['ok'] ?? false);
		$this->assertSame('executed', $approved['status'] ?? null);
		$this->assertSame(1, $tool->getCallCount());
		$this->assertSame(['value' => 'A'], $tool->getLastArguments());
	}

	public function testDeniedMutationIsNotExecuted(): void {
		$tool = new ComponentPresetApprovalMutationTool();
		$service = $this->createService();
		$first = $service->invoke(
			$tool,
			'set_value',
			['value' => 'B'],
			new AgentContext()
		);
		$request = $first['interaction_requests'][0] ?? [];
		$denied = $service->resume(
			$tool,
			(string)($first['resume_handle'] ?? ''),
			(string)($request['id'] ?? ''),
			'deny',
			'Do not execute this action.',
			new AgentContext()
		);

		$this->assertSame(0, $tool->getCallCount());
		$this->assertContains(
			$denied['status'] ?? null,
			['blocked_or_failed', 'declined_or_no_execution']
		);
	}

	private function createService(): AgentComponentPresetToolTestService {
		$events = new ComponentPresetTestEventManager();
		$fingerprint = new AgentActionFingerprint();
		$mutationCommitGuard = new AgentMutationCommitGuardService(
			$fingerprint,
			$events,
			new AgentToolDefinitionSemantics()
		);
		$contractValidation = new AgentToolContractValidationService(new JsonSchemaValidator());
		$suspensions = new ComponentPresetTestSuspensionRepository();
		$review = new AgentActionReviewService(
			$fingerprint,
			$suspensions,
			900,
			$mutationCommitGuard,
			$events
		);

		return new AgentComponentPresetToolTestService(
			new ComponentPresetTestActionPolicyResolver(),
			$fingerprint,
			$review,
			new AgentActionResumeService(
				$fingerprint,
				$suspensions,
				$events,
				new AgentInteractionResponseResolver()
			),
			$contractValidation,
			new AgentCapabilitySelectionGuardService(),
			$mutationCommitGuard,
			$events,
			new AgentToolDefinitionSemantics()
		);
	}
}

require_once __DIR__ . '/ComponentPresetTestActionPolicyResolver.php';
require_once __DIR__ . '/ComponentPresetTestSuspensionRepository.php';
require_once __DIR__ . '/ComponentPresetTestEventManager.php';
require_once __DIR__ . '/ComponentPresetApprovalMutationTool.php';
