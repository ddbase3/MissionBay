<?php declare(strict_types=1);

namespace MissionBay\Node\Data;

use MissionBay\Api\IAgentNode;
use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class TryArrayGetNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'tryarraygetnode';
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
				description: 'The resolved value at the specified path, if it exists.',
				type: 'mixed',
				required: false
			)
		];
	}

	public function execute(array $inputs, IAgentContext $context): array {
		$array = $inputs['array'] ?? [];
		$path = $inputs['path'] ?? '';

		if (!is_array($array) || !is_string($path) || $path === '') {
			return [];
		}

		$keys = explode('.', $path);
		$current = $array;

		foreach ($keys as $key) {
			if (!is_array($current) || !array_key_exists($key, $current)) {
				return [];
			}
			if (!array_key_exists($key, $current)) {
				return [];
			}
			$current = $current[$key];
		}

		return ['value' => $current];
	}

	public function getDescription(): string {
		return 'Tries to retrieve a value from a nested array using dot-notation path syntax. If the path does not exist, no output is returned.';
	}
}

