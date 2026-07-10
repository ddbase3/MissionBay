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

class DelayNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'delaynode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'seconds',
				description: 'Number of seconds to wait (between 0 and 60).',
				type: 'int',
				default: 1,
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'done',
				description: 'Indicates successful completion of the delay.',
				type: 'bool',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message if delay input was invalid.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context): array {
		$seconds = $inputs['seconds'] ?? 1;

		if (!is_numeric($seconds) || $seconds < 0 || $seconds > 60) {
			return ['error' => $this->error('Invalid delay time')];
		}

		sleep((int)$seconds);

		return ['done' => true];
	}

	public function getDescription(): string {
		return 'Pauses flow execution for a specified number of seconds (between 0 and 60). Useful for throttling, timing control, or simulating wait conditions.';
	}
}

