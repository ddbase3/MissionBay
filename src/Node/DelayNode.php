<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Agent\AgentContext;

class DelayNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'delaynode';
	}

	public function getInputDefinitions(): array {
		return ['seconds'];
	}

	public function getOutputDefinitions(): array {
		return ['done', 'error'];
	}

	public function execute(array $inputs, AgentContext $context): array {
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

