<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Agent\AgentContext;

class SwitchNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'switchnode';
	}

	public function getInputDefinitions(): array {
		return ['value', 'cases'];
	}

	public function getOutputDefinitions(): array {
		return ['*']; // alle möglichen Fälle + default
	}

	public function execute(array $inputs, AgentContext $context): array {
		$value = $inputs['value'] ?? null;
		$cases = $inputs['cases'] ?? [];

		if (!is_string($value)) {
			return ['error' => 'SwitchNode: "value" must be string'];
		}

		if (!is_array($cases)) {
			return ['error' => 'SwitchNode: "cases" must be array'];
		}

		if (in_array($value, $cases, true)) {
			return [$value => 1];
		}

		return ['default' => 1];
	}
}

