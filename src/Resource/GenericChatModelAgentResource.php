<?php declare(strict_types=1);

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAiChatModel;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * GenericChatModelAgentResource
 *
 * Generic OpenAI-compatible Chat Completion adapter.
 * Supports:
 *  - rich message normalization
 *  - non-streaming raw completion
 *  - SSE-style streaming via "data: {...}" chunks
 *  - function/tool calling
 *
 * Suitable for OpenAI, Fireworks, Mistral, Ollama, LM Studio, etc.
 */
class GenericChatModelAgentResource extends AbstractAgentResource implements IAiChatModel {

	protected IAgentConfigValueResolver $resolver;

	protected array|string|null $modelConfig = null;
	protected array|string|null $apikeyConfig = null;
	protected array|string|null $endpointConfig = null;
	protected array|string|null $temperatureConfig = null;
	protected array|string|null $maxtokensConfig = null;

	protected array $resolvedOptions = [];

	public function __construct(IAgentConfigValueResolver $resolver, ?string $id = null) {
		parent::__construct($id);
		$this->resolver = $resolver;
	}

	public static function getName(): string {
		return 'genericchatmodelagentresource';
	}

	public function getDescription(): string {
		return 'Generic OpenAI-compatible Chat Completion adapter. Supports streaming + tool-calling.';
	}

	/**
	 * Load global configuration from Flow JSON.
	 */
	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->modelConfig       = $config['model'] ?? null;
		$this->apikeyConfig      = $config['apikey'] ?? null;
		$this->endpointConfig    = $config['endpoint'] ?? null;
		$this->temperatureConfig = $config['temperature'] ?? null;
		$this->maxtokensConfig   = $config['maxtokens'] ?? null;

		$this->resolvedOptions = [
			'model'       => $this->resolver->resolveValue($this->modelConfig) ?? 'gpt-4o-mini',
			'apikey'      => $this->resolver->resolveValue($this->apikeyConfig),
			'endpoint'    => $this->resolver->resolveValue($this->endpointConfig)
				?? 'https://api.openai.com/v1/chat/completions',
			'temperature' => (float)($this->resolver->resolveValue($this->temperatureConfig) ?? 0.7),
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
	 * Basic convenience wrapper → return only assistant text.
	 */
	public function chat(array $messages): string {
		$result = $this->raw($messages);

		if (!isset($result['choices'][0]['message']['content'])) {
			throw new \RuntimeException("Malformed chat response: " . json_encode($result));
		}

		return $result['choices'][0]['message']['content'];
	}

	/**
	 * Standard non-streaming OpenAI-compatible call.
	 */
	public function raw(array $messages, array $tools = []): mixed {
		$model     = $this->resolvedOptions['model'];
		$apikey    = $this->resolvedOptions['apikey'];
		$endpoint  = $this->resolvedOptions['endpoint'];
		$temp      = $this->resolvedOptions['temperature'];
		$maxtokens = $this->resolvedOptions['maxtokens'];

		if (!$apikey) {
			throw new \RuntimeException("Missing API key for chat model.");
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
			throw new \RuntimeException("Chat API request failed with status $httpCode: $result");
		}

		$data = json_decode($result, true);
		if (!is_array($data)) {
			throw new \RuntimeException("Invalid JSON response from chat model: " . substr($result, 0, 200));
		}

		return $data;
	}

	/**
	 * Streaming API (OpenAI-compatible SSE / "data: {...}").
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
			throw new \RuntimeException("Missing API key for chat model.");
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

					// delta text
					if (isset($choice['delta']['content'])) {
						$onData($choice['delta']['content']);
					}

					// finish_reason
					if ($onMeta !== null && isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
						$onMeta([
							'event'          => 'meta',
							'finish_reason'  => $choice['finish_reason'],
							'full'           => $json
						]);
					}

					// tool call chunks (if any)
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
	 * Normalizes structured rich messages → OpenAI schema.
	 * Merges system messages and handles tool messages + tool_calls.
	 */
	private function normalizeMessages(array $messages): array {
		$out = [];
		$systemContents = [];

		foreach ($messages as $m) {
			if (!is_array($m) || !isset($m['role'])) {
				continue;
			}

			$role    = $m['role'];
			$content = $m['content'] ?? '';

			// Collect system messages
			if ($role === 'system') {
				if (is_string($content) && trim($content) !== '') {
					$systemContents[] = trim($content);
				}
				continue;
			}

			// Tool result message
			if ($role === 'tool') {
				if (empty($m['tool_call_id'])) {
					continue;
				}
				$out[] = [
					'role'         => 'tool',
					'tool_call_id' => (string)$m['tool_call_id'],
					'content'      => is_string($content) ? $content : json_encode($content)
				];
				continue;
			}

			// Assistant tool-calling message
			if ($role === 'assistant' && !empty($m['tool_calls']) && is_array($m['tool_calls'])) {
				$toolCalls = [];

				foreach ($m['tool_calls'] as $call) {
					if (!isset($call['id'], $call['function']['name'])) {
						continue;
					}
					$args = $call['function']['arguments'] ?? '{}';
					if (!is_string($args)) {
						$args = json_encode($args);
					}

					$toolCalls[] = [
						'id'       => (string)$call['id'],
						'type'     => 'function',
						'function' => [
							'name'      => (string)$call['function']['name'],
							'arguments' => $args
						]
					];
				}

				$out[] = [
					'role'       => 'assistant',
					'content'    => is_string($content) ? $content : json_encode($content),
					'tool_calls' => $toolCalls
				];
				continue;
			}

			// Normal assistant/user messages
			$out[] = [
				'role'    => $role,
				'content' => is_string($content) ? $content : json_encode($content)
			];

			// Feedback injection
			if (!empty($m['feedback']) && is_string($m['feedback'])) {
				$fb = trim($m['feedback']);
				if ($fb !== '') {
					$out[] = [
						'role'    => 'user',
						'content' => $fb
					];
				}
			}
		}

		// Merge system messages → prepend
		if (!empty($systemContents)) {
			array_unshift($out, [
				'role'    => 'system',
				'content' => implode("\n\n", $systemContents)
			]);
		}

		return $out;
	}
}
