<?php declare(strict_types=1);

namespace MissionBay\Node\Core;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentFlow;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class GetContextVarNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'getcontextvarnode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'key',
				description: 'The name of the context variable to retrieve.',
				type: 'string',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'value',
				description: 'The value retrieved from context, if found.',
				type: 'mixed',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message if key is invalid or value not found.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context, IAgentFlow $flow): array {
		$key = $inputs['key'] ?? null;

		if (!is_string($key)) {
			return ['error' => $this->error('GetContextVarNode: "key" must be a string')];
		}

		$value = $context->getVar($key);

		if ($value === null) {
			return ['error' => $this->error('Context variable not found: ' . $key)];
		}

		return ['value' => $value];
	}

	public function getDescription(): string {
		return 'Retrieves a variable from the AgentContext by key. Useful for accessing shared values across multiple nodes during flow execution.';
	}
}

