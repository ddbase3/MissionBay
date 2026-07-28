<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp\Client;

use MissionBay\Api\IMcpClient;
use MissionBay\Api\IMcpTransport;
use MissionBay\Dto\Mcp\McpClientConfig;
use MissionBay\Dto\Mcp\McpHttpRequest;
use MissionBay\Dto\Mcp\McpHttpResponse;

/**
 * Session-aware MCP client using the Streamable HTTP transport.
 */
final class McpClient implements IMcpClient {

	private bool $initialized = false;
	private int $nextRequestId = 1;
	private string $protocolVersion = '';
	private string $sessionId = '';

	/** @var array<string,mixed> */
	private array $initializeResult = [];

	public function __construct(
		private readonly McpClientConfig $config,
		private readonly IMcpTransport $transport,
		private readonly McpHmacRequestSigner $hmacRequestSigner = new McpHmacRequestSigner()
	) {}

	public function initialize(): array {
		if($this->initialized) {
			return $this->initializeResult;
		}

		$lastError = null;

		foreach($this->config->getSupportedProtocolVersions() as $protocolVersion) {
			$protocolVersion = trim((string)$protocolVersion);
			if($protocolVersion === '') {
				continue;
			}

			try {
				$id = $this->nextRequestId++;
				$message = [
					'jsonrpc' => '2.0',
					'id' => $id,
					'method' => 'initialize',
					'params' => [
						'protocolVersion' => $protocolVersion,
						'capabilities' => (object)[],
						'clientInfo' => [
							'name' => $this->config->getClientName(),
							'version' => $this->config->getClientVersion()
						]
					]
				];
				$response = $this->sendMessage($message, true, false);
				$result = $this->decodeResult($response, $id);
				$negotiatedVersion = trim((string)($result['protocolVersion'] ?? $protocolVersion));

				if($negotiatedVersion === '') {
					throw new McpClientException('MCP initialize response contains no protocol version.');
				}

				if(!in_array($negotiatedVersion, $this->config->getSupportedProtocolVersions(), true)) {
					throw new McpClientException('MCP server selected an unsupported protocol version: ' . $negotiatedVersion);
				}

				$this->protocolVersion = $negotiatedVersion;
				$this->sessionId = $this->normalizeSessionId($response->getHeader('mcp-session-id'));
				$this->initializeResult = $result;
				$this->initialized = true;
				$this->sendNotification('notifications/initialized');

				return $this->initializeResult;
			}
			catch(McpClientException $e) {
				$lastError = $e;
				$this->resetSession();

				if(!$this->isProtocolVersionError($e)) {
					throw $e;
				}
			}
		}

		if($lastError instanceof McpClientException) {
			throw $lastError;
		}

		throw new McpClientException('MCP initialization failed because no protocol version was configured.');
	}

	public function getInitializeResult(): array {
		return $this->initialize();
	}

	public function listTools(): array {
		if(!$this->hasServerCapability('tools')) {
			return [];
		}

		return $this->collectList('tools/list', 'tools');
	}

	public function callTool(string $name, array $arguments = []): array {
		$this->requireServerCapability('tools');
		$name = trim($name);
		if($name === '') {
			throw new \InvalidArgumentException('MCP tool name must not be empty.');
		}

		return $this->request('tools/call', [
			'name' => $name,
			'arguments' => $arguments === [] ? (object)[] : $arguments
		]);
	}

	public function listResources(): array {
		if(!$this->hasServerCapability('resources')) {
			return [];
		}

		return $this->collectList('resources/list', 'resources');
	}

	public function listResourceTemplates(): array {
		if(!$this->hasServerCapability('resources')) {
			return [];
		}

		try {
			return $this->collectList('resources/templates/list', 'resourceTemplates');
		}
		catch(McpClientException $e) {
			if($e->getJsonRpcCode() === -32601) {
				return [];
			}

			throw $e;
		}
	}

	public function readResource(string $uri): array {
		$this->requireServerCapability('resources');
		$uri = trim($uri);
		if($uri === '') {
			throw new \InvalidArgumentException('MCP resource URI must not be empty.');
		}

		return $this->request('resources/read', ['uri' => $uri]);
	}

	public function listPrompts(): array {
		if(!$this->hasServerCapability('prompts')) {
			return [];
		}

		return $this->collectList('prompts/list', 'prompts');
	}

	public function getPrompt(string $name, array $arguments = []): array {
		$this->requireServerCapability('prompts');
		$name = trim($name);
		if($name === '') {
			throw new \InvalidArgumentException('MCP prompt name must not be empty.');
		}

		return $this->request('prompts/get', [
			'name' => $name,
			'arguments' => $arguments === [] ? (object)[] : $arguments
		]);
	}

	public function getProtocolVersion(): string {
		$this->initialize();
		return $this->protocolVersion;
	}

	public function getSessionId(): string {
		$this->initialize();
		return $this->sessionId;
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>
	 */
	private function request(string $method, array $params = [], bool $allowSessionRecovery = true): array {
		$this->initialize();
		$id = $this->nextRequestId++;
		$message = [
			'jsonrpc' => '2.0',
			'id' => $id,
			'method' => $method
		];

		if($params !== []) {
			$message['params'] = $params;
		}

		$response = $this->sendMessage($message, true, true);

		if($response->getStatusCode() === 404 && $this->sessionId !== '' && $allowSessionRecovery) {
			$this->resetSession();
			$this->initialize();
			return $this->request($method, $params, false);
		}

		return $this->decodeResult($response, $id);
	}

	private function sendNotification(string $method): void {
		$message = [
			'jsonrpc' => '2.0',
			'method' => $method
		];
		$response = $this->sendMessage($message, false, true);
		$status = $response->getStatusCode();

		if($status < 200 || $status >= 300) {
			throw $this->httpError($response);
		}
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	private function collectList(string $method, string $resultKey): array {
		$items = [];
		$cursor = null;
		$seenCursors = [];

		for($page = 1; $page <= $this->config->getMaxPages(); $page++) {
			$params = $cursor !== null ? ['cursor' => $cursor] : [];
			$result = $this->request($method, $params);
			$pageItems = $result[$resultKey] ?? [];

			if(!is_array($pageItems)) {
				throw new McpClientException('MCP ' . $method . ' result field "' . $resultKey . '" must be an array.');
			}

			foreach($pageItems as $item) {
				if(!is_array($item)) {
					continue;
				}

				$items[] = $item;
				if(count($items) > $this->config->getMaxItems()) {
					throw new McpClientException('MCP ' . $method . ' exceeded the configured item limit.');
				}
			}

			$nextCursor = $result['nextCursor'] ?? null;
			if($nextCursor === null) {
				return $items;
			}

			if(!is_scalar($nextCursor)) {
				throw new McpClientException('MCP ' . $method . ' returned an invalid nextCursor.');
			}

			$cursor = trim((string)$nextCursor);
			if($cursor === '') {
				return $items;
			}
			if(isset($seenCursors[$cursor])) {
				throw new McpClientException('MCP ' . $method . ' repeated a pagination cursor.');
			}
			$seenCursors[$cursor] = true;
		}

		throw new McpClientException('MCP ' . $method . ' exceeded the configured page limit.');
	}

	/**
	 * @param array<string,mixed> $message
	 */
	private function sendMessage(array $message, bool $expectResponse, bool $includeSession): McpHttpResponse {
		try {
			$body = json_encode(
				$message,
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
			);
		}
		catch(\JsonException $e) {
			throw new McpClientException('Unable to encode MCP JSON-RPC request: ' . $e->getMessage(), 0, null, $e);
		}

		$headers = $this->buildHeaders($includeSession);

		foreach($this->hmacRequestSigner->createHeaders($this->config, 'POST', $body) as $name => $value) {
			$headers[$name] = $value;
		}

		$request = new McpHttpRequest(
			$this->config->getEndpoint(),
			$body,
			$headers,
			$this->config->getConnectTimeout(),
			$this->config->getRequestTimeout(),
			$this->config->getMaxResponseBytes(),
			$this->config->shouldVerifyTls()
		);
		try {
			$response = $this->transport->send($request);
		}
		catch(\Throwable $e) {
			$message = $this->config->redactSensitiveText($e->getMessage());
			if($e instanceof McpClientException) {
				throw new McpClientException(
					$message,
					$e->getHttpStatus(),
					$e->getJsonRpcCode()
				);
			}
			throw new McpClientException($message !== '' ? $message : 'MCP transport failed.');
		}

		if(!$expectResponse) {
			return $response;
		}

		$status = $response->getStatusCode();
		if($status < 200 || $status >= 300) {
			return $response;
		}

		return $response;
	}

	/**
	 * @return array<string,string>
	 */
	private function buildHeaders(bool $includeSession): array {
		$headers = [];
		$names = [];

		foreach($this->config->getConnectionHeaders() as $name => $value) {
			$name = trim((string)$name);
			if($name === '') {
				continue;
			}
			$lower = strtolower($name);
			if(isset($names[$lower])) {
				unset($headers[$names[$lower]]);
			}
			$headers[$name] = (string)$value;
			$names[$lower] = $name;
		}

		$this->setHeader($headers, $names, 'Content-Type', 'application/json');
		$this->setHeader($headers, $names, 'Accept', 'application/json, text/event-stream');
		$this->setHeader($headers, $names, 'Accept-Encoding', 'identity');

		if($includeSession && $this->protocolVersion !== '') {
			$this->setHeader($headers, $names, 'MCP-Protocol-Version', $this->protocolVersion);
		}

		if($includeSession && $this->sessionId !== '') {
			$this->setHeader($headers, $names, 'Mcp-Session-Id', $this->sessionId);
		}

		return $headers;
	}

	/**
	 * @param array<string,string> $headers
	 * @param array<string,string> $names
	 */
	private function setHeader(array &$headers, array &$names, string $name, string $value): void {
		$lower = strtolower($name);
		if(isset($names[$lower])) {
			unset($headers[$names[$lower]]);
		}
		$headers[$name] = $value;
		$names[$lower] = $name;
	}

	/** @return array<string,mixed> */
	private function decodeResult(McpHttpResponse $response, int $requestId): array {
		$status = $response->getStatusCode();
		if($status < 200 || $status >= 300) {
			throw $this->httpError($response);
		}

		$payload = $this->decodePayload($response, $requestId);
		$error = $payload['error'] ?? null;

		if(is_array($error)) {
			$code = isset($error['code']) && is_numeric($error['code']) ? (int)$error['code'] : null;
			$message = trim((string)($error['message'] ?? 'MCP JSON-RPC request failed.'));
			$message = $this->config->redactSensitiveText($message);
			throw new McpClientException($message !== '' ? $message : 'MCP JSON-RPC request failed.', $status, $code);
		}

		$result = $payload['result'] ?? null;
		if(!is_array($result)) {
			if($result instanceof \stdClass) {
				return (array)$result;
			}
			throw new McpClientException('MCP JSON-RPC response contains no object result.', $status);
		}

		return $result;
	}

	/** @return array<string,mixed> */
	private function decodePayload(McpHttpResponse $response, int $requestId): array {
		$body = trim($response->getBody());
		if($body === '') {
			throw new McpClientException('MCP JSON-RPC response body is empty.', $response->getStatusCode());
		}

		$contentType = $response->getContentType();
		$messages = $contentType === 'text/event-stream'
			? $this->decodeSseMessages($body)
			: $this->decodeJsonMessages($body);

		foreach($messages as $message) {
			if(!is_array($message)) {
				continue;
			}

			if(array_key_exists('id', $message) && trim((string)($message['method'] ?? '')) !== '') {
				throw new McpClientException('Server-initiated MCP requests are not supported by this client.');
			}

			if(!array_key_exists('id', $message)) {
				continue;
			}

			if((string)$message['id'] === (string)$requestId) {
				return $message;
			}
		}

		throw new McpClientException('MCP response does not contain the expected JSON-RPC id.', $response->getStatusCode());
	}

	/** @return array<int,array<string,mixed>> */
	private function decodeJsonMessages(string $body): array {
		$decoded = json_decode($body, true);
		if(json_last_error() !== JSON_ERROR_NONE) {
			throw new McpClientException('Unable to decode MCP JSON response: ' . json_last_error_msg());
		}

		if(!is_array($decoded)) {
			throw new McpClientException('MCP JSON response must be an object or batch.');
		}

		if($this->isList($decoded)) {
			return array_values(array_filter($decoded, 'is_array'));
		}

		return [$decoded];
	}

	/** @return array<int,array<string,mixed>> */
	private function decodeSseMessages(string $body): array {
		$messages = [];
		$events = preg_split('/\r?\n\r?\n/', trim($body));

		foreach($events ?: [] as $event) {
			$dataLines = [];
			foreach(preg_split('/\r?\n/', (string)$event) ?: [] as $line) {
				if(str_starts_with($line, 'data:')) {
					$dataLines[] = ltrim(substr($line, 5));
				}
			}

			if($dataLines === []) {
				continue;
			}

			$decoded = json_decode(implode("\n", $dataLines), true);
			if(json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
				throw new McpClientException('Unable to decode MCP SSE data event: ' . json_last_error_msg());
			}
			$messages[] = $decoded;
		}

		if($messages === []) {
			throw new McpClientException('MCP SSE response contains no JSON-RPC data event.');
		}

		return $messages;
	}

	private function httpError(McpHttpResponse $response): McpClientException {
		$status = $response->getStatusCode();
		$message = 'MCP HTTP request failed with status ' . $status . '.';
		$jsonRpcCode = null;
		$body = trim($response->getBody());

		if($body !== '') {
			$decoded = json_decode($body, true);
			if(is_array($decoded)) {
				$error = is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
				$jsonRpcCode = isset($error['code']) && is_numeric($error['code'])
					? (int)$error['code']
					: null;
				$serverMessage = trim((string)($error['message'] ?? $decoded['message'] ?? ''));
				if($serverMessage !== '') {
					$message .= ' ' . $this->config->redactSensitiveText($serverMessage);
				}
			}
		}

		return new McpClientException($message, $status, $jsonRpcCode);
	}

	private function hasServerCapability(string $capability): bool {
		$initialize = $this->initialize();
		$capabilities = $initialize['capabilities'] ?? [];

		return is_array($capabilities) && array_key_exists($capability, $capabilities);
	}

	private function requireServerCapability(string $capability): void {
		if(!$this->hasServerCapability($capability)) {
			throw new McpClientException('Remote MCP server does not advertise the ' . $capability . ' capability.');
		}
	}

	private function normalizeSessionId(string $sessionId): string {
		$sessionId = trim($sessionId);
		if($sessionId === '') {
			return '';
		}

		if(preg_match('/^[\x21-\x7E]+$/', $sessionId) !== 1) {
			throw new McpClientException('MCP server returned an invalid session id.');
		}

		return $sessionId;
	}

	private function isProtocolVersionError(McpClientException $exception): bool {
		if($exception->getJsonRpcCode() === -32602) {
			return true;
		}

		$message = strtolower($exception->getMessage());
		return str_contains($message, 'protocol version') || str_contains($message, 'unsupported version');
	}

	private function resetSession(): void {
		$this->initialized = false;
		$this->protocolVersion = '';
		$this->sessionId = '';
		$this->initializeResult = [];
	}

	/** @param array<mixed> $value */
	private function isList(array $value): bool {
		if(function_exists('array_is_list')) {
			return array_is_list($value);
		}

		return array_keys($value) === range(0, count($value) - 1);
	}
}
