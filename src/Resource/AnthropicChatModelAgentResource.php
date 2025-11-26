<?php declare(strict_types=1);

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAiChatModel;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * AnthropicChatModelAgentResource
 *
 * Adapter for Anthropic Claude 3.x API.
 * Supports:
 * - chat()
 * - raw()
 * - stream()
 * No tool calling included.
 *
 * Endpoint:
 *   https://api.anthropic.com/v1/messages
 */
class AnthropicChatModelAgentResource extends AbstractAgentResource implements IAiChatModel {

	protected IAgentConfigValueResolver $resolver;
	protected array $resolvedOptions = [];

	protected array|string|null $modelConfig = null;
	protected array|string|null $apikeyConfig = null;
	protected array|string|null $endpointConfig = null;
	protected array|string|null $temperatureConfig = null;
	protected array|string|null $maxtokensConfig = null;

	public function __construct(IAgentConfigValueResolver $resolver, ?string $id = null) {
		parent::__construct($id);
		$this->resolver = $resolver;
	}

	public static function getName(): string {
		return 'anthropicchatmodelagentresource';
	}

	public function getDescription(): string {
		return 'Connects to Anthropic Claude 3.x message API.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->modelConfig       = $config['model'] ?? null;
		$this->apikeyConfig      = $config['apikey'] ?? null;
		$this->endpointConfig    = $config['endpoint'] ?? null;
		$this->temperatureConfig = $config['temperature'] ?? null;
		$this->maxtokensConfig   = $config['maxtokens'] ?? null;

		$this->resolvedOptions = [
			'model'       => $this->resolver->resolveValue($this->modelConfig) ?? 'claude-3-haiku-20240307',
			'apikey'      => $this->resolver->resolveValue($this->apikeyConfig),
			'endpoint'    => $this->resolver->resolveValue($this->endpointConfig)
				?? 'https://api.anthropic.com/v1/messages',
			'temperature' => (float)($this->resolver->resolveValue($this->temperatureConfig) ?? 0.3),
			'maxtokens'   => (int)($this->resolver->resolveValue($this->maxtokensConfig) ?? 1024),
		];
	}

	public function getOptions(): array {
		return $this->resolvedOptions;
	}

	public function setOptions(array $options): void {
		$this->resolvedOptions = array_merge($this->resolvedOptions, $options);
	}

	/**
	 * Non-streaming chat wrapper
	 */
	public function chat(array $messages): string {
		$raw = $this->raw($messages);
		return $raw['content'][0]['text'] ?? '';
	}

	/**
	 * Non-streaming Claude request
	 */
	public function raw(array $messages, array $tools = []): mixed {
		$opts = $this->resolvedOptions;

		if (empty($opts['apikey'])) {
			throw new \RuntimeException("Missing API key for Anthropic model.");
		}

		$payload = [
			'model'       => $opts['model'],
			'messages'    => $this->normalizeMessages($messages),
			'temperature' => $opts['temperature'],
			'max_tokens'  => $opts['maxtokens']
		];

		$json = json_encode($payload);

		$headers = [
			'Content-Type: application/json',
			'x-api-key: ' . $opts['apikey'],
			'anthropic-version: 2023-06-01'
		];

		$ch = curl_init($opts['endpoint']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

		$result = curl_exec($ch);

		if (curl_errno($ch)) {
			throw new \RuntimeException('Anthropic request failed: ' . curl_error($ch));
		}

		$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http !== 200) {
			throw new \RuntimeException("Anthropic error HTTP $http: $result");
		}

		$data = json_decode($result, true);
		if (!is_array($data)) {
			throw new \RuntimeException("Invalid JSON from Anthropic: " . substr($result ?? '', 0, 200));
		}

		return $data;
	}

	/**
	 * Streaming Claude (SSE)
	 */
	public function stream(
		array $messages,
		array $tools,
		callable $onData,
		callable $onMeta = null
	): void {

		$opts = $this->resolvedOptions;

		if (empty($opts['apikey'])) {
			throw new \RuntimeException("Missing API key for Anthropic model.");
		}

		$payload = [
			'model'       => $opts['model'],
			'messages'    => $this->normalizeMessages($messages),
			'temperature' => $opts['temperature'],
			'max_tokens'  => $opts['maxtokens'],
			'stream'      => true
		];

		$json = json_encode($payload);

		$headers = [
			'Content-Type: application/json',
			'x-api-key: ' . $opts['apikey'],
			'anthropic-version: 2023-06-01'
		];

		$ch = curl_init($opts['endpoint']);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);

		curl_setopt(
			$ch,
			CURLOPT_WRITEFUNCTION,
			function ($ch, $chunk) use ($onData, $onMeta) {

				$lines = preg_split("/\r\n|\n|\r/", $chunk);

				foreach ($lines as $line) {
					$line = trim($line);

					if ($line === '' || !str_starts_with($line, 'data:')) {
						continue;
					}

					$data = trim(substr($line, 5));

					if ($data === '[DONE]') {
						if ($onMeta !== null) {
							$onMeta(['event' => 'done']);
						}
						continue;
					}

					$json = json_decode($data, true);
					if (!is_array($json)) {
						continue;
					}

					if (!empty($json['delta']['text'])) {
						$onData($json['delta']['text']);
					}

					if ($onMeta !== null && isset($json['stop_reason'])) {
						$onMeta([
							'event'        => 'meta',
							'stop_reason'  => $json['stop_reason'],
							'full'         => $json
						]);
					}
				}

				return strlen($chunk);
			}
		);

		curl_exec($ch);
		curl_close($ch);
	}

	/**
	 * Convert your internal message schema into Anthropic format.
	 */
	private function normalizeMessages(array $messages): array {
		$out = [];

		foreach ($messages as $m) {
			if (!is_array($m) || !isset($m['role'])) {
				continue;
			}

			$content = $m['content'] ?? '';

			$out[] = [
				'role'    => $m['role'],
				'content' => [
					[
						'type' => 'text',
						'text' => is_string($content) ? $content : json_encode($content)
					]
				]
			];

			if (!empty($m['feedback']) && is_string($m['feedback'])) {
				$out[] = [
					'role'    => 'user',
					'content' => [
						[
							'type' => 'text',
							'text' => trim($m['feedback'])
						]
					]
				];
			}
		}

		return $out;
	}
}
