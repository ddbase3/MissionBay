<?php declare(strict_types=1);

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAiChatModel;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * OpenRouterChatModelAgentResource
 *
 * Full OpenAI-compatible implementation for OpenRouter.ai.
 * Supports:
 * - rich messages
 * - function/tool calling
 * - non-stream + streaming mode
 * - all OpenRouter models (Mistral, Qwen, Llama, DeepSeek, Mixtral, etc.)
 */
class OpenRouterChatModelAgentResource extends AbstractAgentResource implements IAiChatModel {

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
		return 'openrouterchatmodelagentresource';
	}

	public function getDescription(): string {
		return 'Connects to OpenRouter.ai (OpenAI-compatible API). Supports tools + streaming.';
	}

	/**
	 * Load configuration and resolve dynamic values from config/environment/context.
	 */
	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->modelConfig       = $config['model'] ?? null;
		$this->apikeyConfig      = $config['apikey'] ?? null;
		$this->endpointConfig    = $config['endpoint'] ?? null;
		$this->temperatureConfig = $config['temperature'] ?? null;
		$this->maxtokensConfig   = $config['maxtokens'] ?? null;

		$model     = $this->resolver->resolveValue($this->modelConfig) ?? 'mistralai/mistral-medium';
		$apikey    = $this->resolver->resolveValue($this->apikeyConfig);
		$endpoint  = $this->resolver->resolveValue($this->endpointConfig);
		$temp      = $this->resolver->resolveValue($this->temperatureConfig);
		$maxtokens = $this->resolver->resolveValue($this->maxtokensConfig);

		if (empty($endpoint)) {
			$endpoint = 'https://openrouter.ai/api/v1/chat/completions';
		}

		$this->resolvedOptions = [
			'model'       => $model,
			'apikey'      => $apikey,
			'endpoint'    => $endpoint,
			'temperature' => (float)($temp ?? 0.3),
			'maxtokens'   => (int)($maxtokens ?? 512)
		];
	}

	public function getOptions(): array {
		return $this->resolvedOptions;
	}

	public function setOptions(array $options): void {
		$this->resolvedOptions = array_merge($this->resolvedOptions, $options);
	}

	/**
	 * -------------------------------------------------------
	 * BASIC CHAT (non-stream)
	 * -------------------------------------------------------
	 */
	public function chat(array $messages): string {
		$result = $this->raw($messages);

		return $result['choices'][0]['message']['content'] ?? '';
	}

	/**
	 * -------------------------------------------------------
	 * RAW NON-STREAM REQUEST
	 * -------------------------------------------------------
	 */
	public function raw(array $messages, array $tools = []): mixed {

		$model     = $this->resolvedOptions['model'];
		$apikey    = $this->resolvedOptions['apikey'];
		$endpoint  = $this->resolvedOptions['endpoint'];
		$temp      = $this->resolvedOptions['temperature'];
		$maxtokens = $this->resolvedOptions['maxtokens'];

		if (!$apikey) {
			throw new \RuntimeException("Missing API key for OpenRouter.");
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
			'Authorization: ' . 'Bearer ' . $apikey
		];

		$ch = curl_init($endpoint);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

		$result = curl_exec($ch);
		$error  = curl_error($ch);
		$http   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($error) {
			throw new \RuntimeException("OpenRouter request failed: " . $error);
		}

		if ($http !== 200) {
			throw new \RuntimeException("OpenRouter error HTTP $http: " . $result);
		}

		$data = json_decode($result, true);
		if (!is_array($data)) {
			throw new \RuntimeException("Invalid JSON response from OpenRouter.");
		}

		return $data;
	}

	/**
	 * -------------------------------------------------------
	 * STREAMING (SSE)
	 * -------------------------------------------------------
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
			throw new \RuntimeException("Missing API key for OpenRouter.");
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

		$json = json_encode($payload);

		$headers = [
			'Content-Type: application/json',
			'Authorization: ' . 'Bearer ' . $apikey
		];

		$ch = curl_init($endpoint);
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

					$payload = trim(substr($line, 5));

					if ($payload === '[DONE]') {
						if ($onMeta) {
							$onMeta(['event' => 'done']);
						}
						continue;
					}

					$json = json_decode($payload, true);
					if (!is_array($json)) {
						continue;
					}

					$choice = $json['choices'][0] ?? [];
					$delta  = $choice['delta']['content'] ?? null;

					if ($delta !== null) {
						$onData($delta);
					}

					if ($onMeta && isset($choice['finish_reason'])) {
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
	 * -------------------------------------------------------
	 * MESSAGE NORMALIZATION
	 * -------------------------------------------------------
	 */
	private function normalizeMessages(array $messages): array {
		$out = [];

		foreach ($messages as $m) {
			if (!is_array($m) || !isset($m['role'])) {
				continue;
			}

			$role = $m['role'];
			$content = $m['content'] ?? '';

			// tool execution feedback
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

			// assistant message INCLUDING tool calls
			if ($role === 'assistant' && !empty($m['tool_calls'])) {
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

			// Standard chat message
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

		return $out;
	}
}
