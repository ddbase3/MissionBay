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

namespace MissionBay\Node\Control;

use AssistantFoundation\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class NoActionNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'noactionnode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'text',
				description: 'Any input.',
				type: 'mixed',
				default: '',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context): array {
		return [];
	}

	public function getDescription(): string {
		return 'Terminates the flow along this path and avoids unneccesary outputs.';
	}
}

