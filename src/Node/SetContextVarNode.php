<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Agent\AgentContext;

class SetContextVarNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'setcontextvarnode';
	}

	public function getInputDefinitions(): array {
		return ['key', 'value'];
	}

	public function getOutputDefinitions(): array {
		return ['success', 'error'];
	}

	public function execute(array $inputs, AgentContext $context): array {
		$key = $inputs['key'] ?? null;
		$value = $inputs['value'] ?? null;

		if (!is_string($key)) {
			return ['error' => 'SetContextVarNode: "key" must be a string'];
		}

		$context->setVar($key, $value);
		return ['success' => true];
	}
}

