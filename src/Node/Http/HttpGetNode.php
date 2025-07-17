<?php declare(strict_types=1);

namespace MissionBay\Node\Http;

use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

class HttpGetNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'httpgetnode';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'url',
				description: 'The full URL to fetch using HTTP GET.',
				type: 'string',
				required: true
			),
			new AgentNodePort(
				name: 'active',
				description: 'Whether to run this node',
				type: 'bool',
				default: true,
				required: false
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'body',
				description: 'The raw response body from the HTTP request.',
				type: 'string',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message if the request failed or the URL was invalid.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context): array {
		$url = $inputs['url'] ?? null;

		if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
			return ['error' => $this->error('Invalid or missing URL')];
		}

		$response = @file_get_contents($url);

		if ($response === false) {
			return ['error' => $this->error("Failed to fetch URL: $url")];
		}

		return ['body' => $response];
	}

	public function getDescription(): string {
		return 'Performs a simple HTTP GET request to the specified URL and returns the response body. Useful for retrieving external JSON, HTML, or text content in a flow.';
	}
}

