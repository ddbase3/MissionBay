<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Agent\AgentContext;

class GetContextVarNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'getcontextvarnode';
	}

	public function getInputDefinitions(): array {
		return ['key'];
	}

	public function getOutputDefinitions(): array {
		return ['value', 'error'];
	}

	public function execute(array $inputs, AgentContext $context): array {
		$key = $inputs['key'] ?? null;

		if (!is_string($key)) {
			return ['error' => 'GetContextVarNode: "key" must be a string'];
		}

		$value = $context->getVar($key);

		if ($value === null) {
			return ['error' => 'Context variable not found: ' . $key];
		}

		return ['value' => $value];
	}
}

