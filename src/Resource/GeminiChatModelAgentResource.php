<?php declare(strict_types=1);

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAiChatModel;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * GeminiChatModelAgentResource
 *
 * Drop-in replacement compatible with OpenAiChatModelAgentResource.
 * Produces OpenAI-compatible outputs (choices/message/etc),
 * but behind the scenes calls Google's Gemini v1beta API.
 *
 * FIXES (important for your tool-loop stability):
 * - Properly converts OpenAI-style assistant tool_calls into Gemini "functionCall" parts.
 * - Properly converts OpenAI-style tool messages into Gemini "functionResponse" parts.
 * - Avoids sending orphaned tool messages without a prior tool call in the same history.
 * - Uses systemInstruction field instead of stuffing system into user messages.
 *
 * References (Gemini API):
 * - GenerateContentRequest supports systemInstruction, tools, toolConfig, generationConfig. :contentReference[oaicite:0]{index=0}
 * - Function calling uses functionCall and functionResponse parts. :contentReference[oaicite:1]{index=1}
 */
class GeminiChatModelAgentResource extends AbstractAgentResource implements IAiChatModel {

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
		return 'geminichatmodelagentresource';
	}

	public function getDescription(): string {
		return 'Connects to Google Gemini API with full OpenAI-style output compatibility.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->modelConfig       = $config['model'] ?? null;
		$this->apikeyConfig      = $config['apikey'] ?? null;
		$this->endpointConfig    = $config['endpoint'] ?? null;
		$this->temperatureConfig = $config['temperature'] ?? null;
		$this->maxtokensConfig   = $config['maxtokens'] ?? null;

		// NOTE:
		// Endpoint should be "https://generativelanguage.googleapis.com/v1beta/models"
		// because we append "/{model}:generateContent".
		$this->resolvedOptions = [
			'model'       => $this->resolver->resolveValue($this->modelConfig) ?? 'gemini-1.5-flash',
			'apikey'      => $this->resolver->resolveValue($this->apikeyConfig),
			'endpoint'    => $this->resolver->resolveValue($this->endpointConfig) ?? 'https://generativelanguage.googleapis.com/v1beta/models',
			'temperature' => (float)($this->resolver->resolveValue($this->temperatureConfig) ?? 0.7),
			'maxtokens'   => (int)($this->resolver->resolveValue($this->maxtokensConfig) ?? 4096),
		];
	}

	public function getOptions(): array {
		return $this->resolvedOptions;
	}

	public function setOptions(array $options): void {
		$this->resolvedOptions = array_merge($this->resolvedOptions, $options);
	}

	public function chat(array $messages): string {
		$result = $this->raw($messages);

		if (!isset($result['choices'][0]['message']['content'])) {
			throw new \RuntimeException("Malformed Gemini(OpenAI-mode) response: " . json_encode($result));
		}

		return (string)$result['choices'][0]['message']['content'];
	}

	/**
	 * Convert OpenAI tools -> Gemini functionDeclarations.
	 */
	private function normalizeTools(array $tools): array {
		$decls = [];

		foreach ($tools as $t) {
			if (!is_array($t) || !isset($t['function']) || !is_array($t['function'])) continue;

			$fn = $t['function'];

			$decls[] = [
				'name' => (string)($fn['name'] ?? ''),
				'description' => (string)($fn['description'] ?? ''),
				'parameters' => $fn['parameters'] ?? [
					'type' => 'object',
					'properties' => []
				]
			];
		}

		return $decls;
	}

	/**
	 * RAW Gemini-call -> OpenAI-compatible response.
	 */
	public function raw(array $messages, array $tools = []): mixed {

		$apikey    = $this->resolvedOptions['apikey'] ?? null;
		$endpoint  = (string)($this->resolvedOptions['endpoint'] ?? '');
		$model     = (string)($this->resolvedOptions['model'] ?? 'gemini-1.5-flash');
		$temp      = (float)($this->resolvedOptions['temperature'] ?? 0.7);
		$maxtokens = (int)($this->resolvedOptions['maxtokens'] ?? 4096);

		if (!$apikey) {
			throw new \RuntimeException("Missing Gemini API key.");
		}

		$normalized = $this->normalizeMessages($messages);

		$payload = [
			'contents' => $normalized['contents'],
			'generationConfig' => [
				'temperature' => $temp,
				'maxOutputTokens' => $maxtokens
			]
		];

		// systemInstruction is supported as a Content object. :contentReference[oaicite:2]{index=2}
		if (!empty($normalized['system'])) {
			$payload['systemInstruction'] = [
				'parts' => [
					['text' => $normalized['system']]
				]
			];
		}

		// Tools: provide functionDeclarations. :contentReference[oaicite:3]{index=3}
		if (!empty($tools)) {
			$payload['tools'] = [[
				'functionDeclarations' => $this->normalizeTools($tools)
			]];

			// Optional: allow the model to call functions automatically.
			// If your account/API version rejects toolConfig, you can remove this block.
			$payload['toolConfig'] = [
				'functionCallingConfig' => [
					'mode' => 'AUTO'
				]
			];
		}

		$jsonPayload = json_encode($payload);

		$url = rtrim($endpoint, '/') . '/' . $model . ':generateContent?key=' . urlencode((string)$apikey);

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

		$result = curl_exec($ch);

		if (curl_errno($ch)) {
			throw new \RuntimeException('Gemini request failed: ' . curl_error($ch));
		}

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode !== 200) {
			throw new \RuntimeException("Gemini error $httpCode: $result");
		}

		$data = json_decode((string)$result, true);
		if (!is_array($data)) {
			throw new \RuntimeException("Invalid Gemini JSON: " . substr((string)$result, 0, 200));
		}

		// ---- OPENAI-COMPATIBLE WRAPPING ----
		$candidate = $data['candidates'][0] ?? [];

		$message = [
			'role' => 'assistant',
			'content' => ''
		];

		// Gemini returns parts array; we may get:
		// - text parts
		// - functionCall part (tool call) :contentReference[oaicite:4]{index=4}
		$parts = $candidate['content']['parts'] ?? [];
		if (!is_array($parts)) {
			$parts = [];
		}

		$textChunks = [];
		$toolCalls = [];

		foreach ($parts as $p) {
			if (!is_array($p)) continue;

			if (isset($p['text']) && is_string($p['text'])) {
				$textChunks[] = $p['text'];
				continue;
			}

			if (isset($p['functionCall']) && is_array($p['functionCall'])) {
				$fn = $p['functionCall'];
				$toolCalls[] = [
					'id' => uniqid('tool_', true),
					'type' => 'function',
					'function' => [
						'name' => (string)($fn['name'] ?? ''),
						'arguments' => json_encode($fn['args'] ?? [], JSON_UNESCAPED_UNICODE)
					]
				];
			}
		}

		$message['content'] = trim(implode('', $textChunks));

		if ($toolCalls) {
			$message['tool_calls'] = $toolCalls;
		}

		return [
			'choices' => [[
				'message' => $message,
				'finish_reason' => $candidate['finishReason'] ?? 'stop'
			]]
		];
	}

	/**
	 * STREAMING Gemini -> token callbacks.
	 *
	 * Note: Gemini streamGenerateContent often returns newline-delimited JSON chunks.
	 * Some gateways prefix with "data:"; we handle both.
	 */
	public function stream(
		array $messages,
		array $tools,
		callable $onData,
		callable $onMeta = null
	): void {

		$apikey    = $this->resolvedOptions['apikey'] ?? null;
		$endpoint  = (string)($this->resolvedOptions['endpoint'] ?? '');
		$model     = (string)($this->resolvedOptions['model'] ?? 'gemini-1.5-flash');
		$temp      = (float)($this->resolvedOptions['temperature'] ?? 0.7);
		$maxtokens = (int)($this->resolvedOptions['maxtokens'] ?? 4096);

		if (!$apikey) {
			throw new \RuntimeException("Missing Gemini API key.");
		}

		$normalized = $this->normalizeMessages($messages);

		$payload = [
			'contents' => $normalized['contents'],
			'generationConfig' => [
				'temperature' => $temp,
				'maxOutputTokens' => $maxtokens
			]
		];

		if (!empty($normalized['system'])) {
			$payload['systemInstruction'] = [
				'parts' => [
					['text' => $normalized['system']]
				]
			];
		}

		if (!empty($tools)) {
			$payload['tools'] = [[
				'functionDeclarations' => $this->normalizeTools($tools)
			]];
			$payload['toolConfig'] = [
				'functionCallingConfig' => [
					'mode' => 'AUTO'
				]
			];
		}

		$jsonPayload = json_encode($payload);

		$url = rtrim($endpoint, '/') . '/' . $model . ':streamGenerateContent?key=' . urlencode((string)$apikey);

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);

		curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use ($onData, $onMeta) {

			$lines = preg_split("/\r\n|\n|\r/", (string)$chunk);

			foreach ($lines as $line) {
				$line = trim((string)$line);
				if ($line === '') continue;

				// Some proxies prefix with "data:"
				if (str_starts_with($line, 'data:')) {
					$line = trim(substr($line, 5));
					if ($line === '' || $line === '[DONE]') {
						if ($onMeta !== null) {
							$onMeta(['event' => 'done']);
						}
						continue;
					}
				}

				$json = json_decode($line, true);
				if (!is_array($json)) continue;

				$candidate = $json['candidates'][0] ?? null;
				if (!is_array($candidate)) continue;

				$parts = $candidate['content']['parts'] ?? [];
				if (!is_array($parts)) $parts = [];

				foreach ($parts as $p) {
					if (!is_array($p)) continue;

					if (isset($p['text']) && is_string($p['text'])) {
						$onData($p['text']);
						continue;
					}

					if (isset($p['functionCall']) && is_array($p['functionCall']) && $onMeta !== null) {
						$onMeta([
							'event' => 'toolcall',
							'tool_calls' => [[
								'id' => uniqid('tool_', true),
								'type' => 'function',
								'function' => [
									'name' => (string)($p['functionCall']['name'] ?? ''),
									'arguments' => json_encode($p['functionCall']['args'] ?? [], JSON_UNESCAPED_UNICODE)
								]
							]]
						]);
					}
				}

				if ($onMeta !== null && isset($candidate['finishReason']) && $candidate['finishReason'] !== null) {
					$onMeta([
						'event' => 'meta',
						'finish_reason' => $candidate['finishReason']
					]);
				}
			}

			return strlen((string)$chunk);
		});

		curl_exec($ch);
		curl_close($ch);
	}

	/**
	 * Normalize OpenAI-style messages into Gemini "contents" + a single "system" string.
	 *
	 * Key part:
	 * - assistant tool_calls -> role:model + parts:[{functionCall:{name,args}}]
	 * - tool message -> role:user + parts:[{functionResponse:{name,response:{result:<tool JSON>}}}]
	 *
	 * This mirrors Google's function calling flow. :contentReference[oaicite:5]{index=5}
	 */
	private function normalizeMessages(array $messages): array {
		$contents = [];
		$systemParts = [];

		// Track which tool_call_ids are known and map them to tool names.
		$toolCallIdToName = [];

		foreach ($messages as $m) {
			if (!is_array($m) || !isset($m['role'])) continue;

			$role = (string)$m['role'];
			$content = $m['content'] ?? '';

			// Collect system messages into systemInstruction. :contentReference[oaicite:6]{index=6}
			if ($role === 'system') {
				$text = is_string($content) ? $content : json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
				$text = trim((string)$text);
				if ($text !== '') {
					$systemParts[] = $text;
				}
				continue;
			}

			// Assistant messages with tool_calls: convert to functionCall parts.
			if ($role === 'assistant' && !empty($m['tool_calls']) && is_array($m['tool_calls'])) {
				$parts = [];
				foreach ($m['tool_calls'] as $call) {
					if (!is_array($call)) continue;
					$id = (string)($call['id'] ?? '');
					$fname = (string)($call['function']['name'] ?? '');
					$args = $call['function']['arguments'] ?? '{}';

					if ($id === '' || $fname === '') continue;

					$toolCallIdToName[$id] = $fname;

					if (is_string($args)) {
						$decodedArgs = json_decode($args, true);
						$argsArr = is_array($decodedArgs) ? $decodedArgs : [];
					} else if (is_array($args)) {
						$argsArr = $args;
					} else {
						$argsArr = [];
					}

					$parts[] = [
						'functionCall' => [
							'name' => $fname,
							'args' => $argsArr
						]
					];
				}

				// Optional: also forward assistant text (if any) as a text part.
				$text = is_string($content) ? trim($content) : '';
				if ($text !== '') {
					array_unshift($parts, ['text' => $text]);
				}

				if ($parts) {
					$contents[] = [
						'role' => 'model',
						'parts' => $parts
					];
				}

				continue;
			}

			// Tool response: must correspond to a prior tool_call_id; otherwise skip to avoid "orphan" tool messages.
			if ($role === 'tool') {
				$toolCallId = (string)($m['tool_call_id'] ?? '');
				if ($toolCallId === '') {
					continue;
				}

				$fname = $toolCallIdToName[$toolCallId] ?? '';
				if ($fname === '') {
					// Orphan tool response: skip it (prevents broken tool sequencing).
					continue;
				}

				$toolText = is_string($content) ? $content : json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
				$toolText = (string)$toolText;

				// Try to decode the tool JSON (we usually store JSON string).
				$toolJson = json_decode($toolText, true);
				$toolPayload = is_array($toolJson) ? $toolJson : ['raw' => $toolText];

				$contents[] = [
					'role' => 'user',
					'parts' => [[
						'functionResponse' => [
							'name' => $fname,
							'response' => [
								'result' => $toolPayload
							]
						]
					]]
				];

				continue;
			}

			// Regular user/assistant messages -> Gemini user/model.
			$gemRole = ($role === 'assistant') ? 'model' : 'user';

			$text = is_string($content)
				? $content
				: json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

			$contents[] = [
				'role' => $gemRole,
				'parts' => [
					['text' => (string)$text]
				]
			];

			// Optional feedback injection (same approach as OpenAI resource)
			if (!empty($m['feedback']) && is_string($m['feedback'])) {
				$fb = trim($m['feedback']);
				if ($fb !== '') {
					$contents[] = [
						'role' => 'user',
						'parts' => [
							['text' => $fb]
						]
					];
				}
			}
		}

		return [
			'system' => trim(implode("\n\n", $systemParts)),
			'contents' => $contents
		];
	}
}
