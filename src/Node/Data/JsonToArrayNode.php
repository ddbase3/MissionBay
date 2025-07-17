<?php declare(strict_types=1);

namespace MissionBay\Node\Data;

use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class JsonToArrayNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'jsontoarraynode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'json',
				description: 'A valid JSON string to be parsed.',
				type: 'string',
				required: true
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'array',
				description: 'The resulting associative array parsed from the JSON input.',
				type: 'array',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message if the JSON is invalid or cannot be parsed.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context): array {
		$json = $inputs['json'] ?? '';

		$data = json_decode($json, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			return ['error' => $this->error('Invalid JSON: ' . json_last_error_msg())];
		}

		return ['array' => $data];
	}

	public function getDescription(): string {
		return 'Parses a JSON string and converts it into an associative PHP array. Useful for processing API responses or any structured JSON data.';
	}
}

