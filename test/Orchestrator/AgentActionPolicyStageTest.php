<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentActionPolicy;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionDecision;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Context\AgentContext;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use MissionBay\Policy\AllowAllAgentActionPolicy;
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

	/**
	 * @param array<int,IAgentActionPolicy> $policies
	 */
	private function createStage(array $policies): AgentActionPolicyStage {
		$policyIds = array_map(
			static fn(IAgentActionPolicy $policy): string => $policy->id(),
			$policies
		);

		return new AgentActionPolicyStage(
			new StaticAgentActionPolicyResolver($policies),
			new AgentActionFingerprint(),
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

final class DenyAgentActionPolicy implements IAgentActionPolicy {

	public static function getName(): string {
		return 'denyagentactionpolicy';
	}

	public function id(): string {
		return 'deny-actions';
	}

	public function name(): string {
		return 'deny-actions';
	}

	public function getDescription(): string {
		return 'Denies every action in tests.';
	}

	public function getAiUsage(): string {
		return IAgentActionPolicy::AI_USAGE_NONE;
	}

	public function evaluate(AgentAction $action, IAgentContext $context): AgentActionDecision {
		return AgentActionDecision::deny($action->getId(), 'Denied by test policy.');
	}
}
