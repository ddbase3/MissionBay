<?php declare(strict_types=1);

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAiChatModel;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * MistralChatModelAgentResource
 *
 * Adapter für Mistral-kompatible Chat Completion APIs (z. B. Fireworks, Mistral.ai).
 * Unterstützt einfache Tool-Calls, die von Mistral typischerweise als JSON im content-Feld
 * zurückgegeben werden. Wandelt Tool-Ergebnisse automatisch in sprachliche Kontexte um.
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
		return 'Connects to Mistral-compatible Chat Completion APIs (e.g. Mistral, Fireworks). '
		     . 'Converts structured tool outputs into natural-language context so Mistral can use them effectively.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->modelConfig       = $config['model'] ?? null;
		$this->apikeyConfig      = $config['apikey'] ?? null;
		$this->endpointConfig    = $config['endpoint'] ?? null;
		$this->temperatureConfig = $config['temperature'] ?? null;
		$this->maxtokensConfig   = $config['maxtokens'] ?? null;

		$this->resolvedOptions = [
			'model'       => $this->resolver->resolveValue($this->modelConfig) ?? 'mistralai/Mistral-7B-Instruct-v0.3',
			'apikey'      => $this->resolver->resolveValue($this->apikeyConfig),
			'endpoint'    => $this->resolver->resolveValue($this->endpointConfig) ?? 'https://api.fireworks.ai/inference/v1/chat/completions',
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
		$result = $this->raw($messages);
		return $result['choices'][0]['message']['content'] ?? '';
	}

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
			'max_tokens'  => $maxtokens,
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
			throw new \RuntimeException("Invalid JSON response from Mistral API: " . substr($result, 0, 200));
		}

		$this->normalizeMistralResponse($data);
		return $data;
	}

	/**
	 * Erkennung & Normalisierung von Mistral-typischen Tool-Call-Blöcken.
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
			if ($trimmed === '' || !preg_match('/^\[?\s*\{/', $trimmed)) continue;

			$parsed = json_decode($trimmed, true);
			if (!is_array($parsed)) continue;

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
	 * Wandelt Tool-Ergebnisse in natürlichsprachliche Nachrichten um,
	 * damit Mistral sie semantisch verwerten kann (RAG-ähnliches Verhalten).
	 */
	private function normalizeMessages(array $messages): array {
		$out = [];
		$systemContents = [];

		foreach ($messages as $m) {
			if (!is_array($m) || !isset($m['role'])) continue;

			$role = $m['role'];
			$content = $m['content'] ?? '';

			if ($role === 'system') {
				if (is_string($content) && trim($content) !== '') {
					$systemContents[] = trim($content);
				}
				continue;
			}

			// 🧠 Mistral versteht role:"tool" nicht – wir formulieren den Inhalt als Fließtext.
			if ($role === 'tool') {
				$resultText = is_string($content)
					? $content
					: json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

				// 🗣️ natürliche Sprachformulierung statt technischer JSON-Ausgabe
				$out[] = [
					'role' => 'user',
					'content' => "Das Tool hat folgende Information geliefert:\n\n"
						. $resultText
						. "\n\nBitte verwende diese Information, um die ursprüngliche Frage korrekt und natürlichsprachlich zu beantworten."
				];
				continue;
			}

			$out[] = [
				'role' => $role,
				'content' => is_string($content) ? $content : json_encode($content),
			];
		}

		if (!empty($systemContents)) {
			array_unshift($out, [
				'role' => 'system',
				'content' => implode("\n\n", $systemContents),
			]);
		}

		return $out;
	}
}

