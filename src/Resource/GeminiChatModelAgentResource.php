<?php declare(strict_types=1);

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAiChatModel;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * GeminiChatModelAgentResource
 *
 * Adapter for Google Gemini Generative Language API (v1beta).
 * Supports:
 *  - rich message normalization
 *  - function calling (Gemini "function_call")
 *  - streaming via chunked JSON lines
 */
class GeminiChatModelAgentResource extends AbstractAgentResource implements IAiChatModel {

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
		return 'geminichatmodelagentresource';
	}

	public function getDescription(): string {
		return 'Connects to Google Gemini API. Supports streaming, tool-calling and rich message objects.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->modelConfig       = $config['model'] ?? null;
		$this->apikeyConfig      = $config['apikey'] ?? null;
		$this->endpointConfig    = $config['endpoint'] ?? null;
		$this->temperatureConfig = $config['temperature'] ?? null;
		$this->maxtokensConfig   = $config['maxtokens'] ?? null;

		$this->resolvedOptions = [
			'model'       => $this->resolver->resolveValue($this->modelConfig) ?? 'gemini-1.5-flash',
			'apikey'      => $this->resolver->resolveValue($this->apikeyConfig),
			'endpoint'    => $this->resolver->resolveValue($this->endpointConfig)
				?? 'https://generativelanguage.googleapis.com/v1beta/models',
			'temperature' => (float)($this->resolver->resolveValue($this->temperatureConfig) ?? 0.3),
			'maxtokens'   => (int)($this->resolver->resolveValue($this->maxtokensConfig) ?? 4096),
		];
	}

	public function getOptions(): array {
		return $this->resolvedOptions;
	}

	public function setOptions(array $options): void {
		$this->resolvedOptions = array_merge($this->resolvedOptions, $options);
	}

	/**
	 * Basic non-streaming chat.
	 */
	public function chat(array $messages): string {
		$result = $this->raw($messages);
		return $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
	}

	/**
	 * Non-streaming Gemini call.
	 */
	public function raw(array $messages, array $tools = []): mixed {
		$apikey   = $this->resolvedOptions['apikey'];
		$endpoint = $this->resolvedOptions['endpoint'];
		$model    = $this->resolvedOptions['model'];
		$temp     = $this->resolvedOptions['temperature'];
		$maxtokens = $this->resolvedOptions['maxtokens'];

		if (!$apikey) {
			throw new \RuntimeException("Missing Gemini API key.");
		}

		$normalized = $this->normalizeMessages($messages);

		$payload = [
			'contents' => $normalized,
			'generationConfig' => [
				'temperature' => $temp,
				'maxOutputTokens' => $maxtokens
			]
		];

		if (!empty($tools)) {
			$payload['tools'] = [
				[
					'functionDeclarations' => $tools
				]
			];
		}

		$jsonPayload = json_encode($payload);

		$url = $endpoint . '/' . $model . ':generateContent?key=' . urlencode($apikey);

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json'
		]);

		$result = curl_exec($ch);

		if (curl_errno($ch)) {
			throw new \RuntimeException('Gemini request failed: ' . curl_error($ch));
		}

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode !== 200) {
			throw new \RuntimeException("Gemini error $httpCode: $result");
		}

		$data = json_decode($result, true);
		if (!is_array($data)) {
			throw new \RuntimeException("Invalid Gemini JSON: " . substr($result, 0, 200));
		}

		return $data;
	}

	/**
	 * Streaming API
	 *
	 * Gemini streams JSON objects on each line (NOT SSE "data:").
	 */
	public function stream(
			array $messages,
			array $tools,
			callable $onData,
			callable $onMeta = null
	): void {

		$apikey   = $this->resolvedOptions['apikey'];
		$endpoint = $this->resolvedOptions['endpoint'];
		$model    = $this->resolvedOptions['model'];
		$temp     = $this->resolvedOptions['temperature'];
		$maxtokens = $this->resolvedOptions['maxtokens'];

		if (!$apikey) {
			throw new \RuntimeException("Missing Gemini API key.");
		}

		$normalized = $this->normalizeMessages($messages);

		$payload = [
			'contents' => $normalized,
			'generationConfig' => [
				'temperature' => $temp,
				'maxOutputTokens' => $maxtokens
			],
			'stream' => true
		];

		if (!empty($tools)) {
			$payload['tools'] = [
				[
					'functionDeclarations' => $tools
				]
			];
		}

		$jsonPayload = json_encode($payload);

		$url = $endpoint . '/' . $model . ':streamGenerateContent?key=' . urlencode($apikey);

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json'
		]);
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);

		curl_setopt(
			$ch,
			CURLOPT_WRITEFUNCTION,
			function ($ch, $chunk) use ($onData, $onMeta) {

				$lines = preg_split("/\r\n|\n|\r/", $chunk);

				foreach ($lines as $line) {
					$line = trim($line);
					if ($line === '') {
						continue;
					}

					$json = json_decode($line, true);
					if (!is_array($json)) {
						continue;
					}

					$candidates = $json['candidates'][0] ?? null;
					if (!$candidates) {
						continue;
					}

					$parts = $candidates['content']['parts'][0] ?? [];

					// Text chunk
					if (!empty($parts['text'])) {
						$onData($parts['text']);
					}

					// Function call
					if (isset($parts['functionCall']) && $onMeta !== null) {
						$onMeta([
							'event' => 'toolcall',
							'tool_calls' => [
								[
									'id' => uniqid('tool_', true),
									'type' => 'function',
									'function' => [
										'name' => $parts['functionCall']['name'] ?? '',
										'arguments' => json_encode($parts['functionCall']['args'] ?? [])
									]
								]
							]
						]);
					}

					// finish_reason
					if ($onMeta !== null && isset($candidates['finishReason'])) {
						$onMeta([
							'event' => 'meta',
							'finish_reason' => $candidates['finishReason']
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
	 * Convert rich memory messages → Gemini content/messages format.
	 */
	private function normalizeMessages(array $messages): array {
		$out = [];

		foreach ($messages as $m) {
			if (!isset($m['role'])) {
				continue;
			}

			$role = $m['role'];
			$content = $m['content'] ?? '';

			// Gemini structure:
			// { "role": "user", "parts": [ {"text": "..."} ] }
			// Gemini does NOT support role:"tool".

			if ($role === 'tool') {
				$text = is_string($content)
					? $content
					: json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

				$out[] = [
					'role' => 'user',
					'parts' => [
						['text' =>
							"Tool output:\n\n"
							. $text
							. "\n\nPlease use this information for the final answer."
						]
					]
				];
				continue;
			}

			$out[] = [
				'role' => $role,
				'parts' => [
					['text' => is_string($content) ? $content : json_encode($content)]
				]
			];
		}

		return $out;
	}
}

