<?php declare(strict_types=1);

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAiChatModel;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * MistralChatModelAgentResource
 *
 * Adapter for Mistral-compatible Chat APIs (e.g., Mistral.ai, Fireworks).
 * Supports:
 * - rich message objects
 * - tool-call extraction from JSON blocks inside assistant content
 * - streaming responses using SSE-like chunking
 * - natural-language transformation for tool results (Mistral cannot process role:'tool')
 */
class MistralChatModelAgentResource extends AbstractAgentResource implements IAiChatModel {

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
		return 'mistralchatmodelagentresource';
	}

	public function getDescription(): string {
		return 'Connects to Mistral-compatible Chat Completion APIs (Mistral.ai / Fireworks). '
			. 'Supports tool-call extraction and natural-language result injection.';
	}

	/**
	 * Load config and resolve config values from Section/Key sources.
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
				?? 'mistralai/Mistral-7B-Instruct-v0.3',
			'apikey'      => $this->resolver->resolveValue($this->apikeyConfig),
			'endpoint'    => $this->resolver->resolveValue($this->endpointConfig)
				?? 'https://api.fireworks.ai/inference/v1/chat/completions',
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
		$result = $this->raw($messages);
		return $result['choices'][0]['message']['content'] ?? '';
	}

	/**
	 * Non-streaming raw response.
	 */
	public function raw(array $messages, array $tools = []): mixed {
		$model     = $this->resolvedOptions['model'];
		$apikey    = $this->resolvedOptions['apikey'];
		$endpoint  = $this->resolvedOptions['endpoint'];
		$temp      = $this->resolvedOptions['temperature'];
		$maxtokens = $this->resolvedOptions['maxtokens'];

		if (!$apikey) {
			throw new \RuntimeException("Missing API key for Mistral model.");
		}

		$normalized = $this->normalizeMessages($messages);

		$payload = [
			'model'       => $model,
			'messages'    => $normalized,
			'temperature' => $temp,
			'max_tokens'  => $maxtokens
		];

		if (!empty($tools)) {
			$payload['tools'] = $tools;
			$payload['tool_choice'] = 'auto';
		}

		$jsonPayload = json_encode($payload);
		$headers = [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $apikey
		];

		$ch = curl_init($endpoint);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

		$result = curl_exec($ch);

		if (curl_errno($ch)) {
			throw new \RuntimeException('Chat API request failed: ' . curl_error($ch));
		}

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode !== 200) {
			throw new \RuntimeException("Mistral API request failed with status $httpCode: $result");
		}

		$data = json_decode($result, true);
		if (!is_array($data)) {
			throw new \RuntimeException("Invalid JSON response: " . substr($result, 0, 200));
		}

		$this->normalizeMistralResponse($data);
		return $data;
	}

	/**
	 * ------------------------------------------
	 * STREAMING (SSE-like chunks)
	 * ------------------------------------------
	 * Mistral/Fireworks stream format closely follows
	 * OpenAI-style "data: {...}" lines.
	 */
	public function stream(
			array $messages,
			array $tools,
			callable $onData,
			callable $onMeta = null
	): void {

		$model     = $this->resolvedOptions['model'];
		$apikey    = $this->resolvedOptions['apikey'];
		$endpoint  = $this->resolvedOptions['endpoint'];
		$temp      = $this->resolvedOptions['temperature'];
		$maxtokens = $this->resolvedOptions['maxtokens'];

		if (!$apikey) {
			throw new \RuntimeException("Missing API key for Mistral model.");
		}

		$normalized = $this->normalizeMessages($messages);

		$payload = [
			'model'       => $model,
			'messages'    => $normalized,
			'temperature' => $temp,
			'max_tokens'  => $maxtokens,
			'stream'      => true
		];

		if (!empty($tools)) {
			$payload['tools'] = $tools;
			$payload['tool_choice'] = 'auto';
		}

		$jsonPayload = json_encode($payload);

		$headers = [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $apikey
		];

		$ch = curl_init($endpoint);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
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

					// End-of-stream signal
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

					// Text chunk
					$delta = $choice['delta']['content'] ?? null;
					if ($delta !== null) {
						$onData($delta);
					}

					// finish_reason
					if ($onMeta !== null && isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
						$onMeta([
							'event'          => 'meta',
							'finish_reason'  => $choice['finish_reason'],
							'full'           => $json
						]);
					}

					// tool call chunks (rare)
					if (!empty($choice['delta']['tool_calls']) && $onMeta !== null) {
						$onMeta([
							'event'      => 'toolcall',
							'tool_calls' => $choice['delta']['tool_calls']
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
	 * Extracts tool-calls from Mistral JSON-in-text blocks.
	 */
	private function normalizeMistralResponse(array &$data): void {
		if (empty($data['choices'][0]['message']['content'])) {
			return;
		}

		$content = trim($data['choices'][0]['message']['content']);
		$parts = preg_split('/\n\s*\n/', $content);

		$toolCalls = [];

		foreach ($parts as $part) {
			$trimmed = trim($part);
			if ($trimmed === '' || !preg_match('/^\[?\s*\{/', $trimmed)) {
				continue;
			}

			$parsed = json_decode($trimmed, true);
			if (!is_array($parsed)) {
				continue;
			}

			$items = isset($parsed[0]) ? $parsed : [$parsed];

			foreach ($items as $tool) {
				if (isset($tool['name'])) {
					$toolCalls[] = [
						'id' => uniqid('tool_', true),
						'type' => 'function',
						'function' => [
							'name' => $tool['name'],
							'arguments' => json_encode($tool['arguments'] ?? []),
						],
					];
				}
			}
		}

		if (!empty($toolCalls)) {
			$data['choices'][0]['message']['tool_calls'] = $toolCalls;
		}
	}

	/**
	 * Converts rich memory objects into Mistral-friendly messages.
	 *
	 * Important: Mistral does NOT support role:"tool".
	 * → We convert tool results into natural-language user messages.
	 */
	private function normalizeMessages(array $messages): array {
		$out = [];
		$systemBlocks = [];

		foreach ($messages as $m) {
			if (!is_array($m) || !isset($m['role'])) {
				continue;
			}

			$role = $m['role'];
			$content = $m['content'] ?? '';

			// System messages aggregated
			if ($role === 'system') {
				if (is_string($content) && trim($content) !== '') {
					$systemBlocks[] = trim($content);
				}
				continue;
			}

			// Mistral cannot handle role:"tool"
			if ($role === 'tool') {
				$text = is_string($content)
					? $content
					: json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

				$out[] = [
					'role' => 'user',
					'content' =>
						"Das Tool hat folgende Information geliefert:\n\n"
						. $text
						. "\n\nBitte verwende diese Information für die weitere Beantwortung."
				];
				continue;
			}

			$out[] = [
				'role'    => $role,
				'content' => is_string($content) ? $content : json_encode($content)
			];
		}

		if (!empty($systemBlocks)) {
			array_unshift($out, [
				'role' => 'system',
				'content' => implode("\n\n", $systemBlocks),
			]);
		}

		return $out;
	}
}
