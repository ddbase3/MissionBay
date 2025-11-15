<?php declare(strict_types=1);

namespace MissionBay\Node\Core;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentFlow;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class SetContextVarNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'setcontextvarnode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'key',
				description: 'The name under which the value should be stored in the AgentContext.',
				type: 'string',
				required: true
			),
			new AgentNodePort(
				name: 'value',
				description: 'The value to store in context (any type).',
				type: 'mixed',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'success',
				description: 'True if the value was successfully stored.',
				type: 'bool',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message if key input is invalid.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context, IAgentFlow $flow): array {
		$key = $inputs['key'] ?? null;
		$value = $inputs['value'] ?? null;

		if (!is_string($key)) {
			return ['error' => $this->error('SetContextVarNode: "key" must be a string')];
		}

		$context->setVar($key, $value);
		return ['success' => true];
	}

	public function getDescription(): string {
		return 'Stores a value in the AgentContext under a given key. Useful for sharing data between nodes, caching intermediate results, or persisting values across flow steps.';
	}
}

