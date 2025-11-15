<?php declare(strict_types=1);

namespace MissionBay\Node\Data;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentFlow;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class ArrayGetNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'arraygetnode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'array',
				description: 'The nested array to search in.',
				type: 'array',
				required: true
			),
			new AgentNodePort(
				name: 'path',
				description: 'Dot-notation path to the desired value (e.g., "user.profile.name").',
				type: 'string',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'value',
				description: 'The resolved value at the specified path.',
				type: 'mixed',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message if the path is invalid or array is malformed.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context, IAgentFlow $flow): array {
		$array = $inputs['array'] ?? [];
		$path = $inputs['path'] ?? '';

		if (!is_array($array)) {
			return ['error' => $this->error('Input is not an array')];
		}

		if (!is_string($path) || $path === '') {
			return ['error' => $this->error('Invalid or missing path')];
		}

		$keys = explode('.', $path);
		$current = $array;

		foreach ($keys as $key) {
			if (!is_array($current) || !array_key_exists($key, $current)) {
				return ['error' => $this->error("Path not found: $path")];
			}
			$current = $current[$key];
		}

		return ['value' => $current];
	}

	public function getDescription(): string {
		return 'Retrieves a value from a nested array using dot-notation path syntax (e.g., "user.profile.name"). Returns the value or an error if the path is invalid.';
	}
}

