<?php declare(strict_types=1);

namespace MissionBay\Test\Policy;

use AssistantFoundation\Api\IAgentActionPolicy;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionDecision;
use MissionBay\Context\AgentContext;
use MissionBay\Policy\AllowAllAgentActionPolicy;
use PHPUnit\Framework\TestCase;

final class AllowAllAgentActionPolicyTest extends TestCase {

	public function testDefaultPolicyAllowsAnyActionWithTraceableIdentity(): void {
		$policy = new AllowAllAgentActionPolicy();
		$action = new AgentAction('action-42', AgentAction::TYPE_TOOL_CALL, 'delete-record', ['id' => 7]);
		$decision = $policy->evaluate($action, new AgentContext());
		$this->assertTrue($decision->isAllowed());
		$this->assertSame(AgentActionDecision::DECISION_ALLOW, $decision->getDecision());
		$this->assertSame('action-42', $decision->getActionId());
		$this->assertSame('Allowed by the compatibility allow-all policy.', $decision->getReason());
		$this->assertSame(['policy_id' => 'allow-all-actions', 'policy_name' => 'allow-all-actions'], $decision->getMetadata());
	}

	public function testCustomPolicyIdentityIsReportedButNeverChangesDecision(): void {
		$policy = new AllowAllAgentActionPolicy('compat-id', 'Compatibility policy');
		$decision = $policy->evaluate(new AgentAction('x', AgentAction::TYPE_TOOL_CALL, 'unexpected'), new AgentContext());
		$this->assertTrue($decision->isAllowed());
		$this->assertSame(['policy_id' => 'compat-id', 'policy_name' => 'Compatibility policy'], $decision->getMetadata());
		$this->assertSame('allowallagentactionpolicy', AllowAllAgentActionPolicy::getName());
		$this->assertSame(IAgentActionPolicy::AI_USAGE_NONE, $policy->getAiUsage());
	}
}
