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
use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentActionDecision;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use Base3\MissionBay\Policy\MutationApprovalAgentActionPolicy;
use PHPUnit\Framework\TestCase;

final class MutationApprovalAgentActionPolicyTest extends TestCase {

	public function testIdentifiesPolicyAndIgnoresNonToolActions(): void {
		$policy = new MutationApprovalAgentActionPolicy('custom-id', 'Custom name');
		$this->assertSame('mutationapprovalagentactionpolicy', $policy::getName());
		$this->assertSame('custom-id', $policy->id());
		$this->assertSame('Custom name', $policy->name());
		$this->assertSame(IAgentActionPolicy::AI_USAGE_NONE, $policy->getAiUsage());
		$this->assertNotEmpty($policy->getDescription());
		$decision = $policy->evaluate(new AgentAction('a', 'message', 'set'), $this->context([]));
		$this->assertSame(AgentActionDecision::DECISION_ALLOW, $decision->getDecision());
	}

	public function testUnannotatedAndMissingToolsDoNotRequireApproval(): void {
		$context = $this->context([['function' => ['name' => 'read']]]);
		$policy = new MutationApprovalAgentActionPolicy();
		foreach (['read', 'missing'] as $name) {
			$this->assertTrue($policy->evaluate(new AgentAction($name, AgentAction::TYPE_TOOL_CALL, $name), $context)->isAllowed());
		}
		$this->assertTrue($policy->evaluate(new AgentAction('none', AgentAction::TYPE_TOOL_CALL, 'read'), $this->context(null))->isAllowed());
	}

	public function testMutatingAnnotationsRequestMediumRiskApproval(): void {
		$policy = new MutationApprovalAgentActionPolicy();
		foreach (['requiresApproval', 'requires_confirmation', 'requiresConfirmation', 'mutation', 'sideEffectHint', 'side_effect'] as $key) {
			$decision = $policy->evaluate(new AgentAction('a', AgentAction::TYPE_TOOL_CALL, 'set'), $this->context([['function' => ['name' => 'set', $key => true]]]));
			$this->assertSame(AgentActionDecision::DECISION_REQUIRE_APPROVAL, $decision->getDecision(), $key);
			$this->assertSame('medium', $decision->getMetadata()['interaction']['risk']);
		}
	}

	public function testDestructiveAnnotationOverridesExplicitNoApproval(): void {
		$context = $this->context([['function' => [
			'name' => 'delete', 'annotations' => ['requiresApproval' => false],
			'destructiveHint' => true
		]]]);
		$decision = (new MutationApprovalAgentActionPolicy())->evaluate(new AgentAction('a', AgentAction::TYPE_TOOL_CALL, 'delete'), $context);
		$this->assertSame(AgentActionDecision::DECISION_REQUIRE_APPROVAL, $decision->getDecision());
		$this->assertSame('high', $decision->getMetadata()['interaction']['risk']);
		$this->assertSame(true, $decision->getMetadata()['tool_annotations']['destructiveHint']);
	}

	public function testExplicitNoApprovalOverridesNonDestructiveMutationHints(): void {
		$context = $this->context([['function' => ['name' => 'set', 'mutation' => true, 'requiresApproval' => false]]]);
		$this->assertTrue((new MutationApprovalAgentActionPolicy())->evaluate(new AgentAction('a', AgentAction::TYPE_TOOL_CALL, 'set'), $context)->isAllowed());
	}

	public function testReadOnlyFalseMeansApprovalRequiredButTrueDoesNot(): void {
		$policy = new MutationApprovalAgentActionPolicy();
		foreach (['readOnlyHint', 'read_only', 'readonly'] as $key) {
			$deny = $policy->evaluate(new AgentAction('a', AgentAction::TYPE_TOOL_CALL, 'set'), $this->context([['function' => ['name' => 'set'], $key => false]]));
			$this->assertSame(AgentActionDecision::DECISION_REQUIRE_APPROVAL, $deny->getDecision());
			$allow = $policy->evaluate(new AgentAction('a', AgentAction::TYPE_TOOL_CALL, 'set'), $this->context([['function' => ['name' => 'set'], $key => true]]));
			$this->assertTrue($allow->isAllowed());
		}
	}

	public function testTopLevelAnnotationsOverrideNestedAndInvalidEntriesAreIgnored(): void {
		$context = $this->context([false, ['function' => ['name' => 'other']], [
			'function' => ['name' => 'set', 'annotations' => ['requiresApproval' => false]],
			'requiresApproval' => true
		]]);
		$decision = (new MutationApprovalAgentActionPolicy())->evaluate(new AgentAction('a', AgentAction::TYPE_TOOL_CALL, 'set'), $context);
		$this->assertSame(AgentActionDecision::DECISION_REQUIRE_APPROVAL, $decision->getDecision());
	}

	private function context(mixed $definitions): IAgentContext {
		$context = $this->createStub(IAgentContext::class);
		$context->method('getVar')->willReturnCallback(static fn(string $key): mixed => $key === AgentToolLoopContextKeys::TOOL_DEFINITIONS ? $definitions : null);
		return $context;
	}
}
