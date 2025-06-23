<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;

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

	public function execute(array $inputs, IAgentContext $context): array {
		$array = $inputs['array'] ?? [];
		$path = $inputs['path'] ?? '';

		if (!is_array($array)) {
			return ['error' => 'Input is not an array'];
		}

		if (!is_string($path) || $path === '') {
			return ['error' => 'Invalid or missing path'];
		}

		$keys = explode('.', $path);
		$current = $array;

		foreach ($keys as $key) {
			if (!is_array($current) || !array_key_exists($key, $current)) {
				return ['error' => "Path not found: $path"];
			}
			$current = $current[$key];
		}

		return ['value' => $current];
	}

	public function getDescription(): string {
		return 'Retrieves a value from a nested array using dot-notation path syntax (e.g., "user.profile.name"). Returns the value or an error if the path is invalid.';
	}
}

