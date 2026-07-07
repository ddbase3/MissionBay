<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Mcp;

use Base3\Api\IRequest;
use Base3\Logger\Api\ILogger;

/**
 * McpHttpGuard
 *
 * Performs the HTTP-level checks around the JSON-RPC handler. This class is
 * intentionally transport-focused and does not know anything about MissionBay
 * tools, presets, or MCP tool profile contents.
 */
class McpHttpGuard {

	private const LOG_SCOPE = 'missionbay_mcp';
	private const MAX_BODY_BYTES = 1048576;

	/**
	 * @var array<int,string>
	 */
	private const SUPPORTED_PROTOCOL_VERSIONS = [
		'2025-11-25',
		'2025-06-18',
		'2025-03-26'
	];

	public function __construct(
		private readonly IRequest $request,
		private readonly ILogger $logger
	) {}

	public static function getName(): string {
		return 'mcphttpguard';
	}

	/**
	 * Checks request properties that do not require reading the JSON body.
	 *
	 * @return array<string,mixed>|null
	 */
	public function checkBeforePayload(): ?array {
		$methodError = $this->checkMethod();

		if($methodError !== null) {
			return $methodError;
		}

		$acceptError = $this->checkAcceptHeader();

		if($acceptError !== null) {
			return $acceptError;
		}

		$originError = $this->checkOriginHeader();

		if($originError !== null) {
			return $originError;
		}

		$bodySizeError = $this->checkBodySize();

		if($bodySizeError !== null) {
			return $bodySizeError;
		}

		return null;
	}

	/**
	 * Checks request properties that need the decoded JSON-RPC payload.
	 *
	 * @return array<string,mixed>|null
	 */
	public function checkAfterPayload(mixed $payload): ?array {
		if($this->isInitializeOnlyPayload($payload)) {
			return null;
		}

		$version = $this->getProtocolVersionHeader();

		if($version === '') {
			// MCP requires this header after initialize, but also defines 2025-03-26
			// as the compatibility fallback when the header is absent.
			return null;
		}

		if(!in_array($version, self::SUPPORTED_PROTOCOL_VERSIONS, true)) {
			$this->logger->logLevel(ILogger::WARNING, 'Rejected MCP request with unsupported protocol version.', [
				'scope' => self::LOG_SCOPE,
				'protocol_version' => $version
			]);

			return $this->errorResponse(
				400,
				-32006,
				'Unsupported MCP-Protocol-Version: ' . $version
			);
		}

		return null;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function checkMethod(): ?array {
		$method = $this->getMethod();

		if($method === 'POST') {
			return null;
		}

		$this->logger->logLevel(ILogger::WARNING, 'Rejected MCP request with unsupported HTTP method.', [
			'scope' => self::LOG_SCOPE,
			'method' => $method
		]);

		return [
			'status' => 405,
			'headers' => [
				'Allow' => 'POST'
			],
			'body' => [
				'jsonrpc' => '2.0',
				'id' => null,
				'error' => [
					'code' => -32005,
					'message' => 'Method not allowed. Use POST with MCP JSON-RPC payload.'
				]
			]
		];
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function checkAcceptHeader(): ?array {
		$accept = strtolower(trim((string)$this->server('HTTP_ACCEPT', '')));

		if($accept === '') {
			return null;
		}

		if(str_contains($accept, 'application/json') || str_contains($accept, '*/*')) {
			return null;
		}

		$this->logger->logLevel(ILogger::WARNING, 'Rejected MCP request with unsupported Accept header.', [
			'scope' => self::LOG_SCOPE,
			'accept' => $accept
		]);

		return $this->errorResponse(
			406,
			-32007,
			'Not acceptable. This endpoint returns application/json and does not provide SSE responses.'
		);
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function checkOriginHeader(): ?array {
		$origin = trim((string)$this->server('HTTP_ORIGIN', ''));

		if($origin === '') {
			return null;
		}

		$originHost = $this->normalizeHost((string)(parse_url($origin, PHP_URL_HOST) ?: ''));
		$requestHost = $this->normalizeHost((string)$this->server('HTTP_HOST', ''));

		if($originHost !== '' && $requestHost !== '' && hash_equals($requestHost, $originHost)) {
			return null;
		}

		$this->logger->logLevel(ILogger::WARNING, 'Rejected MCP request with foreign Origin header.', [
			'scope' => self::LOG_SCOPE,
			'origin' => $origin,
			'host' => $requestHost
		]);

		return $this->errorResponse(
			403,
			-32008,
			'Forbidden Origin for MCP endpoint.'
		);
	}

	/**
	 * @return array<string,mixed>|null
	 */
	private function checkBodySize(): ?array {
		$contentLength = trim((string)$this->server('CONTENT_LENGTH', ''));

		if($contentLength === '' || !ctype_digit($contentLength)) {
			return null;
		}

		if((int)$contentLength <= self::MAX_BODY_BYTES) {
			return null;
		}

		$this->logger->logLevel(ILogger::WARNING, 'Rejected MCP request body because it is too large.', [
			'scope' => self::LOG_SCOPE,
			'content_length' => (int)$contentLength,
			'max_body_bytes' => self::MAX_BODY_BYTES
		]);

		return $this->errorResponse(
			413,
			-32009,
			'MCP request body is too large.'
		);
	}

	private function getMethod(): string {
		$method = strtoupper((string)$this->server('REQUEST_METHOD', ''));

		if($method !== '') {
			return $method;
		}

		return $this->request->getContext() === IRequest::CONTEXT_WEB_POST ? 'POST' : 'GET';
	}

	private function getProtocolVersionHeader(): string {
		return trim((string)($this->server('HTTP_MCP_PROTOCOL_VERSION', '') ?: $this->server('MCP_PROTOCOL_VERSION', '')));
	}

	private function server(string $key, mixed $default = ''): mixed {
		$value = $this->request->server($key, null);

		if($value !== null) {
			return $value;
		}

		return $_SERVER[$key] ?? $default;
	}

	private function normalizeHost(string $host): string {
		$host = strtolower(trim($host));

		if($host === '') {
			return '';
		}

		if(str_contains($host, ':')) {
			$host = explode(':', $host, 2)[0];
		}

		return $host;
	}

	private function isInitializeOnlyPayload(mixed $payload): bool {
		if(!is_array($payload)) {
			return false;
		}

		if($this->isList($payload)) {
			foreach($payload as $entry) {
				if(!is_array($entry) || !$this->isInitializeOrNotification($entry)) {
					return false;
				}
			}

			return true;
		}

		return $this->isInitializeOrNotification($payload);
	}

	/**
	 * @param array<string,mixed> $request
	 */
	private function isInitializeOrNotification(array $request): bool {
		$method = trim((string)($request['method'] ?? ''));

		if($method === 'initialize') {
			return true;
		}

		return str_starts_with($method, 'notifications/');
	}

	private function isList(array $value): bool {
		if(function_exists('array_is_list')) {
			return array_is_list($value);
		}

		return array_keys($value) === range(0, count($value) - 1);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function errorResponse(int $status, int $code, string $message): array {
		return [
			'status' => $status,
			'headers' => [],
			'body' => [
				'jsonrpc' => '2.0',
				'id' => null,
				'error' => [
					'code' => $code,
					'message' => $message
				]
			]
		];
	}
}
