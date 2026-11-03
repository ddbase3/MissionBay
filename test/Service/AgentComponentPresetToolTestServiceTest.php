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
