<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Agent\AgentContext;

class IfNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'ifnode';
	}

	public function getInputDefinitions(): array {
		return ['condition'];
	}

	public function getOutputDefinitions(): array {
		return ['true', 'false', 'error'];
	}

	public function execute(array $inputs, AgentContext $context): array {
		if (!array_key_exists('condition', $inputs)) {
			return ['error' => 'Missing "condition" input'];
		}

		$cond = $inputs['condition'];

		if (!is_bool($cond)) {
			return ['error' => 'IfNode: "condition" must be boolean'];
		}

		return $cond ? ['true' => 1] : ['false' => 1];
	}

	public function getDescription(): string {
		return 'Evaluates a boolean condition and routes the flow to either the "true" or "false" output. Useful for simple conditional branching within flows.';
	}
}

