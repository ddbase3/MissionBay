<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Agent\AgentContext;

class ArrayGetNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'arraygetnode';
	}

	public function getInputDefinitions(): array {
		return ['array', 'path'];
	}

	public function getOutputDefinitions(): array {
		return ['value', 'error'];
	}

	public function execute(array $inputs, AgentContext $context): array {
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

