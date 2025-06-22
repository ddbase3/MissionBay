<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Agent\AgentContext;

class JsonToArrayNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'jsontoarraynode';
	}

	public function getInputDefinitions(): array {
		return ['json'];
	}

	public function getOutputDefinitions(): array {
		return ['array', 'error'];
	}

	public function execute(array $inputs, AgentContext $context): array {
		$json = $inputs['json'] ?? '';

		$data = json_decode($json, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			return ['error' => 'Invalid JSON: ' . json_last_error_msg()];
		}

		return ['array' => $data];
	}

	public function getDescription(): string {
		return 'Parses a JSON string and converts it into an associative PHP array. Useful for processing API responses or any structured JSON data.';
	}
}

