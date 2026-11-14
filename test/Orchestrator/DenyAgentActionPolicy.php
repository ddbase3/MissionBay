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
