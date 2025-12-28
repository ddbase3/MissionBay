<?php declare(strict_types=1);

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAiChatModel;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * FireworksChatModelAgentResource
 *
 * Adapter for Fireworks.ai Chat Completion API.
 * OpenAI-compatible chat/completions endpoint.
 *
 * Supports:
 * - non-streaming chat() and raw()
 * - streaming via SSE-like chunks (data: {...})
 * - tool calling (tools + tool_choice=auto)
 * - robust normalizeMessages(): supports assistant tool_calls and tool responses
 *	 and filters orphaned tool messages to avoid API 400 errors.
 */
class FireworksChatModelAgentResource extends AbstractAgentResource implements IAiChatModel {

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
		return 'fireworkschatmodelagentresource';
	}

	public function getDescription(): string {
		return 'Connects to Fireworks.ai Chat Completion API (OpenAI-compatible).';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->modelConfig       = $config['model'] ?? null;
		$this->apikeyConfig      = $config['apikey'] ?? null;
		$this->endpointConfig    = $config['endpoint'] ?? null;
		$this->temperatureConfig = $config['temperature'] ?? null;
		$this->maxtokensConfig   = $config['maxtokens'] ?? null;

		$this->resolvedOptions = [
			'model'       => $this->resolver->resolveValue($this->modelConfig)
				?? 'accounts/fireworks/models/firefunction-v1',
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

	public function chat(array $messages): string {
		$r = $this->raw($messages);
		return $r['choices'][0]['message']['content'] ?? '';
	}

	public function raw(array $messages, array $tools = []): mixed {
		$opts = $this->resolvedOptions;

		if (empty($opts['apikey'])) {
			throw new \RuntimeException("Missing API key for Fireworks model.");
		}

		$payload = [
			'model'       => $opts['model'],
			'messages'    => $this->normalizeMessages($messages),
			'temperature' => $opts['temperature'],
			'max_tokens'  => $opts['maxtokens']
		];

		if (!empty($tools)) {
			$payload['tools'] = $tools;
			$payload['tool_choice'] = 'auto';
		}

		$jsonPayload = json_encode($payload);

		$headers = [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $opts['apikey']
		];

		$ch = curl_init($opts['endpoint']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);

		$result = curl_exec($ch);

		if (curl_errno($ch)) {
			throw new \RuntimeException('Fireworks request failed: ' . curl_error($ch));
		}

		$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http !== 200) {
			throw new \RuntimeException("Fireworks error HTTP $http: $result");
		}

		$data = json_decode($result, true);
		if (!is_array($data)) {
			throw new \RuntimeException("Invalid JSON response: " . substr($result ?? '', 0, 200));
		}

		return $data;
	}

	public function stream(
		array $messages,
		array $tools,
		callable $onData,
		callable $onMeta = null
	): void {

		$opts = $this->resolvedOptions;

		if (empty($opts['apikey'])) {
			throw new \RuntimeException("Missing API key for Fireworks model.");
		}

		$payload = [
			'model'       => $opts['model'],
			'messages'    => $this->normalizeMessages($messages),
			'temperature' => $opts['temperature'],
			'max_tokens'  => $opts['maxtokens'],
			'stream'      => true
		];

		if (!empty($tools)) {
			$payload['tools'] = $tools;
			$payload['tool_choice'] = 'auto';
		}

		$jsonPayload = json_encode($payload);

		$headers = [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $opts['apikey']
		];

		$ch = curl_init($opts['endpoint']);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);

		curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use ($onData, $onMeta) {

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

				if ($onMeta !== null && isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
					$onMeta([
						'event'         => 'meta',
						'finish_reason' => $choice['finish_reason'],
						'full'          => $json
					]);
				}

				$delta = $choice['delta']['content'] ?? null;
				if ($delta !== null) {
					$onData($delta);
				}

				if (!empty($choice['delta']['tool_calls'])) {
					if ($onMeta !== null) {
						$onMeta([
							'event'      => 'toolcall',
							'tool_calls' => $choice['delta']['tool_calls']
						]);
					}
				}
			}

			return strlen($chunk);
		});

		curl_exec($ch);
		curl_close($ch);
	}

	/**
	 * Normalizes rich message objects into standard OpenAI-compatible format.
	 *
	 * Critical invariant:
	 * - A tool message is only valid if it responds to a preceding assistant message
	 *	 that declared a matching tool_call_id in THIS outgoing payload.
	 */
	private function normalizeMessages(array $messages): array {
		$out = [];
		$validToolCallIds = [];

		foreach ($messages as $m) {
			if (!is_array($m) || !isset($m['role'])) {
				continue;
			}

			$role = (string)$m['role'];
			$content = $m['content'] ?? '';

			// Assistant message with tool calls
			if ($role === 'assistant' && !empty($m['tool_calls']) && is_array($m['tool_calls'])) {
				$toolCalls = [];

				foreach ($m['tool_calls'] as $call) {
					if (!isset($call['id'], $call['function']['name'])) {
						continue;
					}

					$callId = (string)$call['id'];
					$args = $call['function']['arguments'] ?? '{}';

					if (!is_string($args)) {
						$args = json_encode($args);
					}

					$toolCalls[] = [
						'id'       => $callId,
						'type'     => 'function',
						'function' => [
							'name'      => (string)$call['function']['name'],
							'arguments' => $args
						]
					];

					$validToolCallIds[$callId] = true;
				}

				$out[] = [
					'role'       => 'assistant',
					'content'    => is_string($content) ? $content : json_encode($content),
					'tool_calls' => $toolCalls
				];

				continue;
			}

			// Tool message (must match prior declared tool_call_id)
			if ($role === 'tool') {
				$toolCallId = (string)($m['tool_call_id'] ?? '');

				if ($toolCallId === '' || empty($validToolCallIds[$toolCallId])) {
					// Skip orphaned tool messages to avoid API 400 errors.
					continue;
				}

				$out[] = [
					'role'         => 'tool',
					'tool_call_id' => $toolCallId,
					'content'      => is_string($content) ? $content : json_encode($content)
				];

				unset($validToolCallIds[$toolCallId]);
				continue;
			}

			// Standard message
			$out[] = [
				'role'    => $role,
				'content' => is_string($content) ? $content : json_encode($content)
			];

			// Inject feedback as extra user message
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
