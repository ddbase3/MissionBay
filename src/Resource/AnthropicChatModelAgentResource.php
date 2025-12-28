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
 *
 * Notes:
 * - Anthropic messages API expects roles user/assistant in "messages".
 * - System prompt must be passed via top-level "system" (NOT as role=system message).
 * - Tool messages or unknown roles must be filtered out.
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
			'endpoint'    => $this->resolver->resolveValue($this->endpointConfig) ?? 'https://api.anthropic.com/v1/messages',
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

		$norm = $this->normalizeMessages($messages);

		$payload = [
			'model'       => $opts['model'],
			'messages'    => $norm['messages'],
			'temperature' => $opts['temperature'],
			'max_tokens'  => $opts['maxtokens'],
		];

		if ($norm['system'] !== '') {
			$payload['system'] = $norm['system'];
		}

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
	 *
	 * Anthropic streams evented JSON messages (e.g. content_block_delta with delta.text).
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

		$norm = $this->normalizeMessages($messages);

		$payload = [
			'model'       => $opts['model'],
			'messages'    => $norm['messages'],
			'temperature' => $opts['temperature'],
			'max_tokens'  => $opts['maxtokens'],
			'stream'      => true
		];

		if ($norm['system'] !== '') {
			$payload['system'] = $norm['system'];
		}

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

		$buffer = '';
		$eventName = '';

		curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$buffer, &$eventName, $onData, $onMeta) {

			$buffer .= $chunk;

			// Process complete lines
			while (($pos = strpos($buffer, "\n")) !== false) {
				$line = substr($buffer, 0, $pos);
				$buffer = substr($buffer, $pos + 1);

				$line = rtrim($line, "\r");
				$trim = trim($line);

				if ($trim === '') {
					// event separator
					$eventName = '';
					continue;
				}

				if (str_starts_with($trim, 'event:')) {
					$eventName = trim(substr($trim, 6));
					continue;
				}

				if (!str_starts_with($trim, 'data:')) {
					continue;
				}

				$dataStr = trim(substr($trim, 5));
				if ($dataStr === '' || $dataStr === '[DONE]') {
					if ($dataStr === '[DONE]' && $onMeta !== null) {
						$onMeta(['event' => 'done']);
					}
					continue;
				}

				$json = json_decode($dataStr, true);
				if (!is_array($json)) {
					continue;
				}

				// Anthropic stream types are in "type" (not always via eventName)
				$type = (string)($json['type'] ?? $eventName);

				if ($type === 'content_block_delta') {
					$deltaText = $json['delta']['text'] ?? null;
					if (is_string($deltaText) && $deltaText !== '') {
						$onData($deltaText);
					}
					continue;
				}

				if ($type === 'message_delta') {
					$stop = $json['delta']['stop_reason'] ?? null;
					if ($onMeta !== null && $stop !== null) {
						$onMeta([
							'event'       => 'meta',
							'stop_reason' => $stop,
							'full'        => $json
						]);
					}
					continue;
				}

				if ($type === 'message_stop') {
					if ($onMeta !== null) {
						$onMeta(['event' => 'done']);
					}
					continue;
				}

				// Optional: forward unknown meta
				if ($onMeta !== null) {
					$onMeta([
						'event' => 'meta',
						'type'  => $type,
						'full'  => $json
					]);
				}
			}

			return strlen($chunk);
		});

		curl_exec($ch);
		curl_close($ch);
	}

	/**
	 * Convert internal message schema into Anthropic format.
	 *
	 * - Collect "system" messages into one system string (top-level field).
	 * - Only keep roles user/assistant in messages.
	 * - Drop tool messages and unknown roles.
	 * - Preserve feedback as extra user message.
	 *
	 * @return array{system:string,messages:array}
	 */
	private function normalizeMessages(array $messages): array {
		$out = [];
		$systemParts = [];

		foreach ($messages as $m) {
			if (!is_array($m) || !isset($m['role'])) {
				continue;
			}

			$role = (string)$m['role'];
			$content = $m['content'] ?? '';

			// System is top-level in Anthropic, not in messages[]
			if ($role === 'system') {
				$text = is_string($content) ? $content : json_encode($content);
				$text = trim((string)$text);
				if ($text !== '') {
					$systemParts[] = $text;
				}
				continue;
			}

			// Anthropic messages accept only user/assistant
			if ($role !== 'user' && $role !== 'assistant') {
				// Drop tool, function, developer, etc.
				continue;
			}

			$text = is_string($content) ? $content : json_encode($content);

			$out[] = [
				'role'    => $role,
				'content' => [
					[
						'type' => 'text',
						'text' => (string)$text
					]
				]
			];

			// Inject feedback as extra user message
			if (!empty($m['feedback']) && is_string($m['feedback'])) {
				$fb = trim($m['feedback']);
				if ($fb !== '') {
					$out[] = [
						'role'    => 'user',
						'content' => [
							[
								'type' => 'text',
								'text' => $fb
							]
						]
					];
				}
			}
		}

		return [
			'system'   => implode("\n\n", $systemParts),
			'messages' => $out
		];
	}
}
