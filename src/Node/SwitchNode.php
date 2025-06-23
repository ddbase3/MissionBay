<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;

class SwitchNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'switchnode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'value',
				description: 'The string value to evaluate for branching.',
				type: 'string',
				required: true
			),
			new AgentNodePort(
				name: 'cases',
				description: 'An array of allowed case values. If "value" matches one of them, the corresponding output is triggered.',
				type: 'array<string>',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		// Dynamische Outputs: ein Output pro Wert in "cases", plus "default"
		// Zur Anzeige verwenden wir "*" als Platzhalter
		return [
			new AgentNodePort(
				name: '*',
				description: 'Dynamic outputs: one output per case value, plus a "default" output.',
				type: 'int',
				required: false
			)
		];
	}

	public function execute(array $inputs, IAgentContext $context): array {
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

	public function getDescription(): string {
		return 'Routes the flow based on a string value. If the value matches one of the defined cases, the corresponding output is activated; otherwise, the "default" output is used. Useful for multi-branch control logic.';
	}
}

