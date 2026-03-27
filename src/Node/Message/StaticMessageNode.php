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

class StaticMessageNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'staticmessagenode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'text',
				description: 'The static text message to output.',
				type: 'string',
				default: '',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'message',
				description: 'The resulting static message.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context): array {
		$text = $inputs['text'] ?? '';

		return ['message' => (string)$text];
	}

	public function getDescription(): string {
		return 'Outputs a static text message as provided in the input. Useful for sending fixed content into a flow, such as default values, templates, or predefined prompts.';
	}
}

