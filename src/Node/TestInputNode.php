<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Agent\AgentContext;

class TestInputNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'testinputnode';
	}

	public function getInputDefinitions(): array {
		return ['value'];
	}

	public function getOutputDefinitions(): array {
		return ['value'];
	}

	public function execute(array $inputs, AgentContext $context): array {
		$value = $inputs['value'] ?? null;
		return ['value' => $value];
	}
}

