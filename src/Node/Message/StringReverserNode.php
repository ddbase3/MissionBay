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

namespace MissionBay\Node\Message;

use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class StringReverserNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'stringreversernode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'text',
				description: 'The input string to be reversed.',
				type: 'string',
				default: '',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'reversed',
				description: 'The reversed result of the input string.',
				type: 'string',
				default: null,
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context): array {
		$text = $inputs['text'] ?? '';
		$reversed = strrev($text);

		return ['reversed' => $reversed];
	}

	public function getDescription(): string {
		return 'Reverses the given input string and returns the result. Useful for string manipulation, testing, or flow demonstrations.';
	}
}

