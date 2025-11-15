<?php declare(strict_types=1);

namespace MissionBay\Node\Control;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentFlow;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class NoActionNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'noactionnode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'text',
				description: 'Any input.',
				type: 'mixed',
				default: '',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context, IAgentFlow $flow): array {
		return [];
	}

	public function getDescription(): string {
		return 'Terminates the flow along this path and avoids unneccesary outputs.';
	}
}

