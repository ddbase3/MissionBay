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

namespace MissionBay\Policy;

use AssistantFoundation\Api\IAgentActionPolicy;
use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionDecision;

/**
 * AllowAllAgentActionPolicy
 *
 * Compatibility policy that explicitly allows every proposed action.
 *
 * It preserves the historical MissionBay behavior while making the policy
 * boundary visible and replaceable through configured components.
 */
final class AllowAllAgentActionPolicy implements IAgentActionPolicy {

	public function __construct(
		private readonly string $id = 'allow-all-actions',
		private readonly string $policyName = 'allow-all-actions'
	) {}

	public static function getName(): string {
		return 'allowallagentactionpolicy';
	}

	public function id(): string {
		return $this->id;
	}

	public function name(): string {
		return $this->policyName;
	}

	public function getDescription(): string {
		return 'Allows every proposed agent action and preserves the historical execution behavior.';
	}

	public function getAiUsage(): string {
		return IAgentActionPolicy::AI_USAGE_NONE;
	}

	public function evaluate(AgentAction $action, IAgentContext $context): AgentActionDecision {
		return AgentActionDecision::allow(
			$action->getId(),
			'Allowed by the compatibility allow-all policy.',
			[
				'policy_id' => $this->id(),
				'policy_name' => $this->name()
			]
		);
	}
}
