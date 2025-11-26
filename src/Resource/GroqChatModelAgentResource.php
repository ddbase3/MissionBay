<?php declare(strict_types=1);

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAiChatModel;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * GroqChatModelAgentResource
 *
 * Adapter for Groq's OpenAI-compatible Chat Completion API.
 * Supports:
 * - chat()
 * - raw()
 * - stream()
 * No tool-calling.
 */
class GroqChatModelAgentResource extends AbstractAgentResource implements IAiChatModel {

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
		return 'groqchatmodelagentresource';
	}

	public function getDescription(): string {
		return 'Connects to Groq OpenAI-compatible Chat Completion API.';
	}

	/**
	 * Load config from AgentFlow.
	 */
	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->modelConfig       = $config['model'] ?? null;
		$this->apikeyConfig      = $config['apikey'] ?? null;
		$this->endpointConfig    = $config['endpoint'] ?? null;
		$this->temperatureConfig = $config['temperature'] ?? null;
		$this->maxtokensConfig   = $config['maxtokens'] ?? null;

		$this->resolvedOptions = [
			'model'       => $this->resolver->resolveValue($this->modelConfig)
				?? 'llama3-8b-8192',
			'apikey'      => $this->resolver->resolveValue($this->apikeyConfig),
			'endpoint'    => $this->resolver->resolveValue($this->endpointConfig)
				?? 'https://api.groq.com/openai/v1/chat/completions',
			'temperature' => (float)($this->resolver->resolveValue($this->temperatureConfig) ?? 0.3),
			'maxtokens'   => (int)($this->resolver->resolveValue($this->maxtokensConfig) ?? 512),
		];
	}

	public function getOptions(): array {
		return $this->resolvedOptions;
	}

	public function setOptions(array $options): void {
		$this->resolvedOptions = array_merge($this->resolvedOptions, $options);
	}

	/**
	 * Basic chat → return assistant text only.
	 */
	public function chat(array $messages): string {
		$raw = $this->raw($messages);
		return $raw['choices'][0]['message']['content'] ?? '';
	}

	/**
	 * Non-streaming raw request.
	 */
	public function raw(array $messages, array $tools = []): mixed {
		$opts = $this->resolvedOptions;

		if (empty($opts['apikey'])) {
			throw new \RuntimeException("Missing API key for Groq model.");
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
			'Authorization: Bearer ' . $opts['apikey']
		];

		$ch = curl_init($opts['endpoint']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

		$result = curl_exec($ch);

		if (curl_errno($ch)) {
			throw new \RuntimeException('Groq request failed: ' . curl_error($ch));
		}

		$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http !== 200) {
			throw new \RuntimeException("Groq error HTTP $http: $result");
		}

		$data = json_decode($result, true);
		if (!is_array($data)) {
			throw new \RuntimeException("Invalid JSON: " . substr($result ?? '', 0, 200));
		}

		return $data;
	}

	/**
	 * Streaming SSE-like output.
	 */
	public function stream(
		array $messages,
		array $tools,
		callable $onData,
		callable $onMeta = null
	): void {

		$opts = $this->resolvedOptions;

		if (empty($opts['apikey'])) {
			throw new \RuntimeException("Missing API key for Groq model.");
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
			'Authorization: Bearer ' . $opts['apikey']
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

					$choice = $json['choices'][0] ?? [];
					$delta  = $choice['delta']['content'] ?? null;

					if ($delta !== null) {
						$onData($delta);
					}

					if ($onMeta !== null && isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
						$onMeta([
							'event'         => 'meta',
							'finish_reason' => $choice['finish_reason'],
							'full'          => $json
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
	 * Normalize rich messages.
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
				'content' => is_string($content) ? $content : json_encode($content)
			];

			if (!empty($m['feedback']) && is_string($m['feedback'])) {
				$out[] = [
					'role'    => 'user',
					'content' => trim($m['feedback'])
				];
			}
		}

		return $out;
	}
}
