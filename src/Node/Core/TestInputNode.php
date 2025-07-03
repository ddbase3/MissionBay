<?php declare(strict_types=1);

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

	public function execute(array $inputs, IAgentContext $context): array {
		$value = $inputs['value'] ?? null;
		return ['value' => $value];
	}

	public function getDescription(): string {
		return 'Passes through the input value unchanged. Useful for testing, debugging, or injecting controlled data into a flow.';
	}
}

