<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Dto\Mcp;

/**
 * Validated connection and protocol limits for one remote MCP client.
 */
final class McpClientConfig {

	public const PROTOCOL_AUTO = 'auto';
	public const PROTOCOL_2025_11_25 = '2025-11-25';
	public const PROTOCOL_2025_06_18 = '2025-06-18';
	public const PROTOCOL_2025_03_26 = '2025-03-26';

	private const SUPPORTED_PROTOCOL_VERSIONS = [
		self::PROTOCOL_2025_11_25,
		self::PROTOCOL_2025_06_18,
		self::PROTOCOL_2025_03_26
	];

	private const PROTOCOL_CONTROLLED_HEADER_NAMES = [
		'accept',
		'accept-encoding',
		'authorization',
		'content-length',
		'content-type',
		'host',
		'mcp-protocol-version',
		'mcp-session-id',
		'x-base3-nonce',
		'x-base3-signature',
		'x-base3-timestamp'
	];

	/**
	 * @param array<string,string> $headers
	 */
	private function __construct(
		private readonly string $endpoint,
		private readonly string $authType,
		private readonly string $token,
		private readonly string $hmacSecret,
		private readonly string $username,
		private readonly string $authHeaderName,
		private readonly array $headers,
		private readonly string $protocolVersion,
		private readonly int $connectTimeout,
		private readonly int $requestTimeout,
		private readonly int $maxResponseBytes,
		private readonly int $maxPages,
		private readonly int $maxItems,
		private readonly bool $verifyTls,
		private readonly string $clientName,
		private readonly string $clientVersion
	) {}

	/**
	 * @param array<string,mixed> $config Resolved values only.
	 */
	public static function fromArray(array $config): self {
		$endpoint = trim((string)($config['endpoint'] ?? ''));

		if($endpoint === '') {
			throw new \InvalidArgumentException('MCP endpoint must not be empty.');
		}

		$parts = parse_url($endpoint);
		$scheme = is_array($parts) ? strtolower((string)($parts['scheme'] ?? '')) : '';

		if(!is_array($parts) || !in_array($scheme, ['http', 'https'], true) || trim((string)($parts['host'] ?? '')) === '') {
			throw new \InvalidArgumentException('MCP endpoint must be an absolute HTTP or HTTPS URL.');
		}

		if(isset($parts['user']) || isset($parts['pass'])) {
			throw new \InvalidArgumentException('MCP endpoint must not contain embedded credentials.');
		}

		if(isset($parts['fragment'])) {
			throw new \InvalidArgumentException('MCP endpoint must not contain a URL fragment.');
		}

		$authType = strtolower(trim((string)($config['auth_type'] ?? 'bearer')));
		$allowedAuthTypes = ['none', 'bearer', 'hmac', 'api_key', 'basic'];

		if(!in_array($authType, $allowedAuthTypes, true)) {
			throw new \InvalidArgumentException('Unsupported MCP authentication type: ' . $authType);
		}

		$token = (string)($config['token'] ?? '');
		$hmacSecret = (string)($config['hmac_secret'] ?? '');
		$username = trim((string)($config['username'] ?? ''));
		$authHeaderName = trim((string)($config['auth_header_name'] ?? 'X-API-Key'));

		if($authType !== 'none' && $token === '') {
			throw new \InvalidArgumentException('MCP token must not be empty for authentication type ' . $authType . '.');
		}

		if(in_array($authType, ['bearer', 'hmac', 'api_key'], true)) {
			self::assertHeaderValue($token, 'MCP authentication token');
		}

		if($authType === 'hmac') {
			if($hmacSecret === '' && preg_match('/^b3k_[a-f0-9]{20}_([A-Za-z0-9_-]{43})$/D', $token, $matches)) {
				$hmacSecret = $matches[1];
			}

			if($hmacSecret === '') {
				throw new \InvalidArgumentException(
					'MCP HMAC secret must be configured or derivable from a KeyHarbor token.'
				);
			}

			self::assertHeaderValue($hmacSecret, 'MCP HMAC secret');
		}

		if($authType === 'basic' && $username === '') {
			throw new \InvalidArgumentException('MCP username must not be empty for basic authentication.');
		}

		if($authType === 'api_key') {
			self::assertHeaderName($authHeaderName, 'MCP API key header name');

			if(in_array(strtolower($authHeaderName), self::PROTOCOL_CONTROLLED_HEADER_NAMES, true)) {
				throw new \InvalidArgumentException('MCP API key header conflicts with a protocol-controlled header: ' . $authHeaderName);
			}
		}

		$headers = self::normalizeHeaders($config['headers'] ?? []);

		if($authType === 'api_key') {
			foreach(array_keys($headers) as $headerName) {
				if(strcasecmp($headerName, $authHeaderName) === 0) {
					throw new \InvalidArgumentException(
						'MCP API key header must not also be configured as a custom header: ' . $authHeaderName
					);
				}
			}
		}
		$protocolVersion = trim((string)($config['protocol_version'] ?? self::PROTOCOL_AUTO));

		if(!in_array($protocolVersion, array_merge([self::PROTOCOL_AUTO], self::SUPPORTED_PROTOCOL_VERSIONS), true)) {
			throw new \InvalidArgumentException('Unsupported configured MCP protocol version: ' . $protocolVersion);
		}

		$clientName = trim((string)($config['client_name'] ?? 'MissionBay MCP Client'));
		$clientVersion = trim((string)($config['client_version'] ?? '1.0.0'));

		return new self(
			$endpoint,
			$authType,
			$token,
			$hmacSecret,
			$username,
			$authHeaderName,
			$headers,
			$protocolVersion,
			self::boundedInt($config['connect_timeout'] ?? 10, 1, 120, 'MCP connect timeout'),
			self::boundedInt($config['request_timeout'] ?? 60, 1, 600, 'MCP request timeout'),
			self::boundedInt($config['max_response_bytes'] ?? 2097152, 1024, 67108864, 'MCP maximum response size'),
			self::boundedInt($config['max_pages'] ?? 20, 1, 1000, 'MCP maximum page count'),
			self::boundedInt($config['max_items'] ?? 500, 1, 100000, 'MCP maximum item count'),
			self::toBool($config['verify_tls'] ?? true, true),
			$clientName !== '' ? $clientName : 'MissionBay MCP Client',
			$clientVersion !== '' ? $clientVersion : '1.0.0'
		);
	}

	public function getEndpoint(): string {
		return $this->endpoint;
	}

	/** @return array<int,string> */
	public function getSupportedProtocolVersions(): array {
		if($this->protocolVersion !== self::PROTOCOL_AUTO) {
			return [$this->protocolVersion];
		}

		return self::SUPPORTED_PROTOCOL_VERSIONS;
	}

	public function getConnectTimeout(): int {
		return $this->connectTimeout;
	}

	public function getRequestTimeout(): int {
		return $this->requestTimeout;
	}

	public function getMaxResponseBytes(): int {
		return $this->maxResponseBytes;
	}

	public function getMaxPages(): int {
		return $this->maxPages;
	}

	public function getMaxItems(): int {
		return $this->maxItems;
	}

	public function shouldVerifyTls(): bool {
		return $this->verifyTls;
	}

	public function getClientName(): string {
		return $this->clientName;
	}

	public function getClientVersion(): string {
		return $this->clientVersion;
	}

	/** @return array<string,string> */
	public function getConnectionHeaders(): array {
		$headers = $this->headers;

		if(in_array($this->authType, ['bearer', 'hmac'], true)) {
			$headers['Authorization'] = 'Bearer ' . $this->token;
		}
		elseif($this->authType === 'api_key') {
			$headers[$this->authHeaderName] = $this->token;
		}
		elseif($this->authType === 'basic') {
			$headers['Authorization'] = 'Basic ' . base64_encode($this->username . ':' . $this->token);
		}

		return $headers;
	}


	public function usesHmacAuthentication(): bool {
		return $this->authType === 'hmac';
	}

	public function getHmacSecret(): string {
		return $this->hmacSecret;
	}

	public function redactSensitiveText(string $text): string {
		$values = [];

		if($this->token !== '') {
			$values[] = $this->token;
		}

		if($this->hmacSecret !== '') {
			$values[] = $this->hmacSecret;
		}

		if($this->endpoint !== '') {
			$values[] = $this->endpoint;
		}

		if($this->authType === 'basic' && $this->username !== '' && $this->token !== '') {
			$values[] = base64_encode($this->username . ':' . $this->token);
		}

		foreach($this->headers as $value) {
			if($value !== '') {
				$values[] = $value;
			}
		}

		foreach(array_unique($values) as $value) {
			$text = str_replace($value, '[redacted]', $text);
		}

		return $text;
	}

	/** @return array<string,mixed> */
	public function toDiagnosticArray(): array {
		$parts = parse_url($this->endpoint);

		return [
			'endpoint_host' => is_array($parts) ? (string)($parts['host'] ?? '') : '',
			'endpoint_scheme' => is_array($parts) ? (string)($parts['scheme'] ?? '') : '',
			'auth_type' => $this->authType,
			'protocol_version' => $this->protocolVersion,
			'connect_timeout' => $this->connectTimeout,
			'request_timeout' => $this->requestTimeout,
			'max_response_bytes' => $this->maxResponseBytes,
			'max_pages' => $this->maxPages,
			'max_items' => $this->maxItems,
			'verify_tls' => $this->verifyTls
		];
	}

	/** @return array<string,string> */
	private static function normalizeHeaders(mixed $headers): array {
		if($headers === null || $headers === '') {
			return [];
		}

		if(!is_array($headers)) {
			throw new \InvalidArgumentException('MCP headers must be configured as an object.');
		}

		$reserved = array_merge(self::PROTOCOL_CONTROLLED_HEADER_NAMES, [
			'authorization',
			'x-base3-timestamp',
			'x-base3-nonce',
			'x-base3-signature'
		]);
		$result = [];
		$names = [];

		foreach($headers as $name => $value) {
			$name = trim((string)$name);
			self::assertHeaderName($name, 'MCP custom header name');

			$lowerName = strtolower($name);

			if(in_array($lowerName, $reserved, true)) {
				throw new \InvalidArgumentException('MCP custom header is reserved: ' . $name);
			}

			if(isset($names[$lowerName])) {
				throw new \InvalidArgumentException(
					'MCP custom header is configured more than once with different casing: '
					. $names[$lowerName] . ' / ' . $name
				);
			}

			if(!is_scalar($value) && $value !== null) {
				throw new \InvalidArgumentException('MCP custom header value must be scalar: ' . $name);
			}

			$value = (string)$value;

			self::assertHeaderValue($value, 'MCP custom header value for ' . $name);

			$result[$name] = $value;
			$names[$lowerName] = $name;
		}

		return $result;
	}

	private static function assertHeaderName(string $name, string $label): void {
		if($name === '' || preg_match('/^[!#$%&\'*+.^_`|~0-9A-Za-z-]+$/', $name) !== 1) {
			throw new \InvalidArgumentException($label . ' is invalid.');
		}
	}

	private static function assertHeaderValue(string $value, string $label): void {
		if(preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $value) === 1) {
			throw new \InvalidArgumentException($label . ' must not contain control characters.');
		}
	}

	private static function boundedInt(mixed $value, int $min, int $max, string $label): int {
		if(!is_numeric($value)) {
			throw new \InvalidArgumentException($label . ' must be numeric.');
		}

		$value = (int)$value;

		if($value < $min || $value > $max) {
			throw new \InvalidArgumentException($label . ' must be between ' . $min . ' and ' . $max . '.');
		}

		return $value;
	}

	private static function toBool(mixed $value, bool $default): bool {
		if(is_bool($value)) {
			return $value;
		}

		if(is_int($value)) {
			return $value !== 0;
		}

		if(!is_scalar($value) || trim((string)$value) === '') {
			return $default;
		}

		$value = strtolower(trim((string)$value));

		if(in_array($value, ['1', 'true', 'yes', 'on'], true)) {
			return true;
		}

		if(in_array($value, ['0', 'false', 'no', 'off'], true)) {
			return false;
		}

		return $default;
	}
}
