<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentActionPolicy;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionDecision;
use AssistantFoundation\Dto\AgentToolResult;
use AssistantFoundation\Dto\AiToolCall;
use MissionBay\Context\AgentContext;
use MissionBay\Api\IAgentTool;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use MissionBay\Policy\AllowAllAgentActionPolicy;
use PHPUnit\Framework\TestCase;

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
