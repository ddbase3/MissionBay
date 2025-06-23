<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;

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

	public function execute(array $inputs, IAgentContext $context): array {
		$seconds = $inputs['seconds'] ?? 1;

		if (!is_numeric($seconds) || $seconds < 0 || $seconds > 60) {
			return ['error' => 'Invalid delay time'];
		}

		sleep((int)$seconds);

		return ['done' => true];
	}

	public function getDescription(): string {
		return 'Pauses flow execution for a specified number of seconds (between 0 and 60). Useful for throttling, timing control, or simulating wait conditions.';
	}
}

