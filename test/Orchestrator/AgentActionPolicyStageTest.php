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

use Base3\AssistantFoundation\Api\IAgentActionPolicy;
use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentActionDecision;
use Base3\AssistantFoundation\Dto\AgentToolResult;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use Base3\MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use Base3\MissionBay\Policy\AllowAllAgentActionPolicy;
use Base3\AssistantRuntime\Service\StateStoreAgentSuspensionRepository;
use Base3\Test\State\StateStoreStub;
use Base3\MissionBay\Orchestrator\Service\AgentActionReviewService;
use Base3\MissionBay\Orchestrator\Service\AgentCapabilitySelectionGuardService;
use Base3\MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use PHPUnit\Framework\TestCase;

final class AgentActionPolicyStageTest extends TestCase {

	public function testAllowPolicyKeepsToolCallPendingForExecution(): void {
		$call = new AiToolCall('call-1', 'lookup', ['query' => 'BASE3']);
		$context = $this->createContext($call);
		$stage = $this->createStage([new AllowAllAgentActionPolicy()]);

		$patch = $stage->process($context)->getPatch();

		$this->assertSame([$call], $patch[AgentToolLoopContextKeys::PENDING_TOOL_CALLS]);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_TOOLS, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertCount(1, $patch[AgentToolLoopContextKeys::ACTIONS]);
		$this->assertCount(1, $patch[AgentToolLoopContextKeys::ACTION_DECISIONS]);
		$this->assertSame([], $patch[AgentToolLoopContextKeys::TOOL_RESULTS]);
		$this->assertTrue($patch[AgentToolLoopContextKeys::ACTION_DECISIONS][0]->isAllowed());
	}

	public function testMissingProviderCallIdIsNormalizedForActionAndExecution(): void {
		$call = new AiToolCall('', 'lookup', ['query' => 'BASE3']);
		$context = $this->createContext($call);
		$stage = $this->createStage([new AllowAllAgentActionPolicy()]);

		$patch = $stage->process($context)->getPatch();
		$action = $patch[AgentToolLoopContextKeys::ACTIONS][0];
		$effectiveCall = $patch[AgentToolLoopContextKeys::PENDING_TOOL_CALLS][0];

		$this->assertNotSame('', $action->getId());
		$this->assertSame($action->getId(), $effectiveCall->getId());
		$this->assertTrue($effectiveCall->getMetadata()['generated_call_id']);
	}

	public function testApprovalUsesReviewServiceAndSuspendsToolExecution(): void {
		$call = new AiToolCall('call-1', 'delete_record', ['id' => 42]);
		$context = $this->createContext($call);
		$stage = $this->createStage([new ApprovalAgentActionPolicy()]);

		$patch = $stage->process($context)->getPatch();

		$this->assertTrue($patch[AgentToolLoopContextKeys::SUSPENDED]);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_AWAITING_APPROVAL, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertSame([], $patch[AgentToolLoopContextKeys::PENDING_TOOL_CALLS]);
		$this->assertCount(1, $patch[AgentToolLoopContextKeys::INTERACTION_REQUESTS]);
		$this->assertNotSame('', $patch[AgentToolLoopContextKeys::RESUME_HANDLE]);
	}

	public function testDenyPolicyBlocksToolCallAndCreatesObservationResult(): void {
		$call = new AiToolCall('call-1', 'delete_record', ['id' => 42]);
		$context = $this->createContext($call);
		$stage = $this->createStage([new DenyAgentActionPolicy()]);

		$patch = $stage->process($context)->getPatch();

		$this->assertSame([], $patch[AgentToolLoopContextKeys::PENDING_TOOL_CALLS]);
		$this->assertSame(AgentToolLoopContextKeys::PHASE_AFTER_TOOLS, $patch[AgentToolLoopContextKeys::PHASE]);
		$this->assertCount(1, $patch[AgentToolLoopContextKeys::ACTION_DECISIONS]);
		$this->assertSame(AgentActionDecision::DECISION_DENY, $patch[AgentToolLoopContextKeys::ACTION_DECISIONS][0]->getDecision());
		$this->assertCount(1, $patch[AgentToolLoopContextKeys::TOOL_RESULTS]);
		$this->assertInstanceOf(AgentToolResult::class, $patch[AgentToolLoopContextKeys::TOOL_RESULTS][0]);
		$this->assertFalse($patch[AgentToolLoopContextKeys::TOOL_RESULTS][0]->isSuccess());
		$this->assertSame('action_denied', $patch[AgentToolLoopContextKeys::TOOL_RESULTS][0]->getErrorCode());
	}

	public function testToolOutsideExactModelSelectionIsBlockedBeforePolicy(): void {
		$call = new AiToolCall('call-1', 'lookup', ['query' => 'BASE3']);
		$context = $this->createContext($call);
		$context->setVar(AgentToolLoopContextKeys::CAPABILITY_SELECTION_APPLIED, true);
		$context->setVar(AgentToolLoopContextKeys::SELECTED_TOOL_NAMES, ['other_tool']);
		$stage = $this->createStage([new AllowAllAgentActionPolicy()]);

		$patch = $stage->process($context)->getPatch();

		$this->assertSame([], $patch[AgentToolLoopContextKeys::PENDING_TOOL_CALLS]);
		$this->assertSame([], $patch[AgentToolLoopContextKeys::ACTION_DECISIONS]);
		$this->assertCount(1, $patch[AgentToolLoopContextKeys::TOOL_RESULTS]);
		$this->assertSame('capability_not_selected', $patch[AgentToolLoopContextKeys::TOOL_RESULTS][0]->getErrorCode());
	}

	public function testInvalidToolInputIsBlockedBeforePolicyAndExecution(): void {
		$call = new AiToolCall('call-1', 'lookup', ['limit' => 'five']);
		$context = $this->createContext($call);
		$context->setVar(AgentToolLoopContextKeys::TOOLS, [new PolicyContractTool()]);
		$context->setVar(AgentToolLoopContextKeys::TOOL_CONTRACT_VALIDATIONS, []);
		$stage = $this->createStage([new AllowAllAgentActionPolicy()]);

		$patch = $stage->process($context)->getPatch();

		$this->assertSame([], $patch[AgentToolLoopContextKeys::PENDING_TOOL_CALLS]);
		$this->assertSame([], $patch[AgentToolLoopContextKeys::ACTION_DECISIONS]);
		$this->assertCount(1, $patch[AgentToolLoopContextKeys::ACTIONS]);
		$this->assertCount(1, $patch[AgentToolLoopContextKeys::TOOL_RESULTS]);
		$this->assertSame('tool_input_contract_violation', $patch[AgentToolLoopContextKeys::TOOL_RESULTS][0]->getErrorCode());
		$this->assertSame(AgentToolLoopContextKeys::PHASE_AFTER_TOOLS, $patch[AgentToolLoopContextKeys::PHASE]);
	}

	/**
	 * @param array<int,IAgentActionPolicy> $policies
	 */
	private function createStage(array $policies): AgentActionPolicyStage {
		$policyIds = array_map(
			static fn(IAgentActionPolicy $policy): string => $policy->id(),
			$policies
		);

		$fingerprint = new AgentActionFingerprint();
		return new AgentActionPolicyStage(
			new StaticAgentActionPolicyResolver($policies),
			$fingerprint,
			new AgentActionReviewService($fingerprint, new StateStoreAgentSuspensionRepository(new StateStoreStub())),
			new AgentToolContractValidationService(),
			new AgentCapabilitySelectionGuardService(),
			'action-policy',
			'action-policy',
			$policyIds
		);
	}

	private function createContext(AiToolCall $call): AgentContext {
		return new AgentContext(vars: [
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_TOOLS,
			AgentToolLoopContextKeys::PENDING_TOOL_CALLS => [$call],
			AgentToolLoopContextKeys::ACTIONS => [],
			AgentToolLoopContextKeys::ACTION_DECISIONS => [],
			AgentToolLoopContextKeys::TOOL_RESULTS => [],
			AgentToolLoopContextKeys::ITERATION => 1,
			AgentToolLoopContextKeys::COMPLETED => false,
			AgentToolLoopContextKeys::FAILURE_CODE => ''
		]);
	}
}

require_once __DIR__ . '/ApprovalAgentActionPolicy.php';
require_once __DIR__ . '/DenyAgentActionPolicy.php';
require_once __DIR__ . '/PolicyContractTool.php';
