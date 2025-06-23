<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentContext;
use MissionBay\Agent\AgentNodePort;

class HttpRequestNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'httprequestnode';
	}

	public function getDescription(): string {
		return 'Performs a flexible HTTP request with customizable method, headers, and body. Returns the response body and HTTP status code. Supports GET, POST, PUT, DELETE, and more.';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'url',
				description: 'The target URL for the HTTP request.',
				type: 'string',
				required: true
			),
			new AgentNodePort(
				name: 'method',
				description: 'The HTTP method to use (GET, POST, PUT, DELETE, etc).',
				type: 'string',
				default: 'GET',
				required: false
			),
			new AgentNodePort(
				name: 'headers',
				description: 'Optional associative array of HTTP headers.',
				type: 'array<string>',
				default: [],
				required: false
			),
			new AgentNodePort(
				name: 'body',
				description: 'Optional request body to send (for POST/PUT).',
				type: 'string',
				default: null,
				required: false
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'status',
				description: 'The HTTP status code from the response.',
				type: 'int',
				required: false
			),
			new AgentNodePort(
				name: 'body',
				description: 'The response body returned by the request.',
				type: 'string',
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message if the request failed.',
				type: 'string',
				required: false
			)
		];
	}

	public function execute(array $inputs, IAgentContext $context): array {
		$url = $inputs['url'] ?? null;
		$method = strtoupper($inputs['method'] ?? 'GET');
		$headers = $inputs['headers'] ?? [];
		$body = $inputs['body'] ?? null;

		if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
			return ['error' => 'Invalid or missing URL'];
		}

		$httpHeaders = [];
		foreach ($headers as $name => $value) {
			$httpHeaders[] = $name . ': ' . $value;
		}

		$options = [
			'http' => [
				'method' => $method,
				'header' => implode("\r\n", $httpHeaders),
				'ignore_errors' => true
			]
		];

		if ($method !== 'GET' && $body !== null) {
			$options['http']['content'] = $body;
		}

		$contextStream = stream_context_create($options);
		$response = @file_get_contents($url, false, $contextStream);

		$statusCode = null;
		if (isset($http_response_header[0]) && preg_match('/HTTP\/\S+ (\d+)/', $http_response_header[0], $matches)) {
			$statusCode = (int)$matches[1];
		}

		if ($response === false) {
			return ['error' => 'Request failed', 'status' => $statusCode ?? 0];
		}

		return [
			'status' => $statusCode ?? 200,
			'body' => $response
		];
	}
}

