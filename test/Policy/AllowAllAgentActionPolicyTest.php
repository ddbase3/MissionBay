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

namespace Base3\MissionBay\Test\Policy;

use Base3\AssistantFoundation\Api\IAgentActionPolicy;
use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentActionDecision;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Policy\AllowAllAgentActionPolicy;
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
