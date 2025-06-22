<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Agent\AgentContext;

class ArraySetNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'arraysetnode';
	}

	public function getInputDefinitions(): array {
		return ['array', 'path', 'value'];
	}

	public function getOutputDefinitions(): array {
		return ['array', 'error'];
	}

	public function execute(array $inputs, AgentContext $context): array {
		$array = $inputs['array'] ?? [];
		$path = $inputs['path'] ?? '';
		$value = $inputs['value'] ?? null;

		if (!is_array($array)) {
			return ['error' => 'Input "array" must be an array'];
		}

		if (!is_string($path) || $path === '') {
			return ['error' => 'Input "path" must be a non-empty string'];
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
}

