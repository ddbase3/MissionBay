<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;

class IfNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'ifnode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'condition',
				description: 'The boolean value to evaluate.',
				type: 'bool',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'true',
				description: 'Activated if the condition is true.',
				type: 'int',
				required: false
			),
			new AgentNodePort(
				name: 'false',
				description: 'Activated if the condition is false.',
				type: 'int',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message if the input is missing or not boolean.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, IAgentContext $context): array {
		if (!array_key_exists('condition', $inputs)) {
			return ['error' => $this->error('Missing "condition" input')];
		}

		$cond = $inputs['condition'];

		if (!is_bool($cond)) {
			return ['error' => $this->error('IfNode: "condition" must be boolean')];
		}

		return $cond ? ['true' => 1] : ['false' => 1];
	}

	public function getDescription(): string {
		return 'Evaluates a boolean condition and routes the flow to either the "true" or "false" output. Useful for simple conditional branching within flows.';
	}
}

