<?php declare(strict_types=1);

namespace MissionBay\Node\Data;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentFlow;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class ArraySetNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'arraysetnode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'array',
				description: 'The base array to modify.',
				type: 'array',
				required: true
			),
			new AgentNodePort(
				name: 'path',
				description: 'Dot-notation path to the key to insert or update (e.g., "user.profile.name").',
				type: 'string',
				required: true
			),
			new AgentNodePort(
				name: 'value',
				description: 'The value to set at the specified path.',
				type: 'mixed',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'array',
				description: 'The modified array after insertion or update.',
				type: 'array',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message if the input was invalid.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context, IAgentFlow $flow): array {
		$array = $inputs['array'] ?? [];
		$path = $inputs['path'] ?? '';
		$value = $inputs['value'] ?? null;

		if (!is_array($array)) {
			return ['error' => $this->error('Input "array" must be an array')];
		}

		if (!is_string($path) || $path === '') {
			return ['error' => $this->error('Input "path" must be a non-empty string')];
		}

		$keys = explode('.', $path);
		$ref =& $array;

		foreach ($keys as $key) {
			if (!is_array($ref)) {
				$ref = [];
			}
			if (!array_key_exists($key, $ref)) {
				$ref[$key] = [];
			}
			$ref =& $ref[$key];
		}

		$ref = $value;

		return ['array' => $array];
	}

	public function getDescription(): string {
		return 'Inserts or updates a value in a nested array using dot-notation path syntax (e.g., "user.profile.name"). Returns the modified array structure.';
	}
}

