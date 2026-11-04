<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator\Stage;

use AssistantFoundation\Api\IAgentStage;
use AssistantFoundation\Dto\AgentStageResult;
use MissionBay\Api\IAgentModelDecisionStrategy;
use MissionBay\Api\IAgentModelDecisionStrategyResolver;
use MissionBay\Context\AgentContext;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys as Keys;
use PHPUnit\Framework\TestCase;

final class AgentModelDecisionStageTest extends TestCase {

	public function testSupportsOnlyActiveUnfailedModelPhase(): void {
		$stage = new AgentModelDecisionStage();
		$context = new AgentContext();
		$this->assertFalse($stage->supports($context));
		$context->setVar(Keys::PHASE, Keys::PHASE_MODEL);
		$this->assertTrue($stage->supports($context));
		$context->setVar(Keys::COMPLETED, true);
		$this->assertFalse($stage->supports($context));
		$context->setVar(Keys::COMPLETED, false);
		$context->setVar(Keys::FAILURE_CODE, 'error');
		$this->assertFalse($stage->supports($context));
		$context->setVar(Keys::FAILURE_CODE, '');
		$this->assertTrue($stage->supports($context));
	}

	public function testConfiguredStrategyDelegatesThroughExistingResolver(): void {
		$config = AgentModelDecisionConfig::native();
		$context = new AgentContext(vars: [Keys::MODEL_DECISION_CONFIG => $config]);
		$result = AgentStageResult::patch(['status' => 'chosen']);
		$strategy = $this->createMock(IAgentModelDecisionStrategy::class);
		$strategy->expects($this->once())->method('decide')->with($context, $config)->willReturn($result);
		$resolver = $this->createMock(IAgentModelDecisionStrategyResolver::class);
		$resolver->expects($this->once())->method('resolve')->with(AgentModelDecisionConfig::STRATEGY_NATIVE)->willReturn($strategy);
		$this->assertSame($result, (new AgentModelDecisionStage('custom', 'decision', $resolver))->process($context));
	}

	public function testMissingStrategyConfigUsesLegacyCompatibilitySelection(): void {
		$context = new AgentContext();
		$strategy = $this->createStub(IAgentModelDecisionStrategy::class);
		$strategy->method('decide')->willReturn(AgentStageResult::none());
		$resolver = $this->createMock(IAgentModelDecisionStrategyResolver::class);
		$resolver->expects($this->once())->method('resolve')->with(AgentModelDecisionConfig::STRATEGY_SIMPLE)->willReturn($strategy);
		$this->assertSame([], (new AgentModelDecisionStage(strategyResolver: $resolver))->process($context)->getPatch());
	}

	public function testStrategyResolverFailureProducesTypedFailurePatch(): void {
		$config = AgentModelDecisionConfig::aiGuarded();
		$context = new AgentContext(vars: [Keys::MODEL_DECISION_CONFIG => $config]);
		$resolver = $this->createStub(IAgentModelDecisionStrategyResolver::class);
		$resolver->method('resolve')->willThrowException(new \RuntimeException('unavailable'));
		$patch = (new AgentModelDecisionStage(strategyResolver: $resolver))->process($context)->getPatch();
		$this->assertSame('model_decision_strategy_error', $patch[Keys::FAILURE_CODE]);
		$this->assertSame(Keys::PHASE_FAILED, $patch[Keys::PHASE]);
		$this->assertFalse($patch[Keys::COMPLETED]);
		$this->assertSame('unavailable', $patch[Keys::FAILURE_DETAIL]['message']);
		$this->assertSame(AgentModelDecisionConfig::STRATEGY_AI_GUARDED, $patch[Keys::FAILURE_DETAIL]['strategy']);
	}

	public function testStageIdentityAndAiRequirementRemainStable(): void {
		$stage = new AgentModelDecisionStage('id', 'title');
		$this->assertSame('agentmodeldecisionstage', AgentModelDecisionStage::getName());
		$this->assertSame('id', $stage->id());
		$this->assertSame('title', $stage->name());
		$this->assertSame(IAgentStage::AI_USAGE_REQUIRED, $stage->getAiUsage());
	}
}
