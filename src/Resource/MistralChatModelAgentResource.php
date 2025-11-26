<?php declare(strict_types=1);

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAiChatModel;
use MissionBay\Api\IAgentConfigValueResolver;

class MistralChatModelAgentResource extends AbstractAgentResource implements IAiChatModel {

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
		return 'mistralchatmodelagentresource';
	}

	public function getDescription(): string {
		return 'Connects to the native Mistral.ai Chat Completion API (no tools).';
	}

	/**
	 * Loads config and resolves dynamic values.
	 */
	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->modelConfig       = $config['model'] ?? null;
		$this->apikeyConfig      = $config['apikey'] ?? null;
		$this->endpointConfig    = $config['endpoint'] ?? null;
		$this->temperatureConfig = $config['temperature'] ?? null;
		$this->maxtokensConfig   = $config['maxtokens'] ?? null;

		$model     = $this->resolver->resolveValue($this->modelConfig) ?? 'mistral-small-latest';
		$apikey    = $this->resolver->resolveValue($this->apikeyConfig);
		$endpoint  = $this->resolver->resolveValue($this->endpointConfig);
		$temp      = $this->resolver->resolveValue($this->temperatureConfig);
		$maxtokens = $this->resolver->resolveValue($this->maxtokensConfig);

		// Correct default endpoint
		if (empty($endpoint)) {
			$endpoint = 'https://api.mistral.ai/v1/chat/completions';
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
	 * Simple chat
	 */
	public function chat(array $messages): string {
		$result = $this->raw($messages);

		return $result['choices'][0]['message']['content'] ?? '';
	}

	/**
	 * Non-streaming request
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

		$json = json_encode($payload);
		$headers = [
			'Content-Type: application/json',
			'Authorization: ' . 'Bearer ' . $apikey
		];

		$ch = curl_init($endpoint);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

		$result = curl_exec($ch);

		if (curl_errno($ch)) {
			throw new \RuntimeException('Mistral request failed: ' . curl_error($ch));
		}

		$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($http !== 200) {
			throw new \RuntimeException("Mistral API error $http: $result");
		}

		return json_decode($result, true);
	}

	/**
	 * Streaming responses
	 */
	public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void {

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

		curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use ($onData, $onMeta) {

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
				$delta = $choice['delta']['content'] ?? null;

				if ($delta !== null) {
					$onData($delta);
				}
			}

			return strlen($chunk);
		});

		curl_exec($ch);
		curl_close($ch);
	}

	private function normalizeMessages(array $messages): array {
		$out = [];

		foreach ($messages as $m) {
			$out[] = [
				'role'    => $m['role'],
				'content' => $m['content']
			];
		}

		return $out;
	}
}
