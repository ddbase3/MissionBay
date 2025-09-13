<?php declare(strict_types=1);

namespace MissionBay\Resource;

use Base3\Api\IAiChatModel;
use MissionBay\Api\IAgentConfigValueResolver;
use MissionBay\Agent\AgentNodeDock;

/**
 * OpenAiChatModelAgentResource
 *
 * Provides access to OpenAI's Chat Completion API via a dockable resource.
 * Supports dynamic config resolution for model, apikey, endpoint, and parameters.
 */
class OpenAiChatModelAgentResource extends AbstractAgentResource implements IAiChatModel {

	protected IAgentConfigValueResolver $resolver;

	protected array|string|null $modelConfig = null;
	protected array|string|null $apikeyConfig = null;
	protected array|string|null $endpointConfig = null;
	protected array|string|null $temperatureConfig = null;

	protected array $resolvedOptions = [];

	public function __construct(IAgentConfigValueResolver $resolver, ?string $id = null) {
		parent::__construct($id);
		$this->resolver = $resolver;
	}

	public static function getName(): string {
		return 'openaichatmodelagentresource';
	}

	public function getDescription(): string {
		return 'Connects to OpenAI Chat API (GPT models) and returns assistant responses. Supports function/tool-calling via OpenAI tools parameter.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->modelConfig       = $config['model'] ?? null;
		$this->apikeyConfig      = $config['apikey'] ?? null;
		$this->endpointConfig    = $config['endpoint'] ?? null;
		$this->temperatureConfig = $config['temperature'] ?? null;

		$this->resolvedOptions = [
			'model'       => $this->resolver->resolveValue($this->modelConfig) ?? 'gpt-4o-mini',
			'apikey'      => $this->resolver->resolveValue($this->apikeyConfig),
			'endpoint'    => $this->resolver->resolveValue($this->endpointConfig) ?? 'https://api.openai.com/v1/chat/completions',
			'temperature' => (float)($this->resolver->resolveValue($this->temperatureConfig) ?? 0.7),
		];
	}

	public function getOptions(): array {
		return $this->resolvedOptions;
	}

	public function setOptions(array $options): void {
		$this->resolvedOptions = array_merge($this->resolvedOptions, $options);
	}

	/**
	 * Sends chat messages and returns the assistant reply (string).
	 */
	public function chat(array $messages): string {
		$result = $this->raw($messages);

		if (!isset($result['choices'][0]['message']['content'])) {
			throw new \RuntimeException("Malformed OpenAI chat response: " . json_encode($result));
		}

		return $result['choices'][0]['message']['content'];
	}

	/**
	 * Sends chat messages and returns raw decoded JSON result.
	 * If $tools are passed, OpenAI Function Calling will be enabled.
	 *
	 * @param array $messages
	 * @param array $tools Optional tool definitions
	 * @return mixed
	 */
	public function raw(array $messages, array $tools = []): mixed {
		$model    = $this->resolvedOptions['model'] ?? 'gpt-4o-mini';
		$apikey   = $this->resolvedOptions['apikey'] ?? null;
		$endpoint = $this->resolvedOptions['endpoint'] ?? 'https://api.openai.com/v1/chat/completions';
		$temp     = $this->resolvedOptions['temperature'] ?? 0.7;

		if (!$apikey) {
			throw new \RuntimeException("Missing API key for OpenAI chat model.");
		}

		$payload = [
			'model'       => $model,
			'messages'    => $messages,
			'temperature' => $temp,
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
			throw new \RuntimeException('OpenAI API request failed: ' . curl_error($ch));
		}

		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($httpCode !== 200) {
			throw new \RuntimeException("API request failed with status $httpCode: $result");
		}

		$data = json_decode($result, true);
		if (!is_array($data)) {
			throw new \RuntimeException("Invalid JSON response from OpenAI: " . substr($result, 0, 200));
		}

		return $data;
	}
}

