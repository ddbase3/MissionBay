<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Agent\AgentContext;

class HttpRequestNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'httprequestnode';
	}

	public function getInputDefinitions(): array {
		return ['url', 'method', 'headers', 'body'];
	}

	public function getOutputDefinitions(): array {
		return ['status', 'body', 'error'];
	}

	public function execute(array $inputs, AgentContext $context): array {
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

		// Statuscode aus $http_response_header extrahieren
		$statusCode = null;
		if (isset($http_response_header[0]) && preg_match('/HTTP\/\S+ (\d+)/', $http_response_header[0], $matches)) {
			$statusCode = (int)$matches[1];
		}

		if ($response === false) {
			return ['error' => "Request failed", 'status' => $statusCode ?? 0];
		}

		return [
			'status' => $statusCode ?? 200,
			'body' => $response
		];
	}
}

