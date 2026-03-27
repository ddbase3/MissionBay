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

namespace MissionBay\Node\Core;

use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class TestInputNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'testinputnode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'value',
				description: 'The value to pass through unchanged.',
				type: 'mixed',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'value',
				description: 'The same value that was received as input.',
				type: 'mixed',
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context): array {
		$value = $inputs['value'] ?? null;
		return ['value' => $value];
	}

	public function getDescription(): string {
		return 'Passes through the input value unchanged. Useful for testing, debugging, or injecting controlled data into a flow.';
	}
}

