<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace MissionBay\ChatModel;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Api\IAiProvider;
use Base3\Api\IBase;
use Base3\Api\IClassMap;

abstract class AbstractChatCompletionModel implements IAiChatModel, IBase {

	/**
	 * @var array<string,mixed>
	 */
	protected array $options = [];

	protected ?IAiProvider $provider = null;

	public function __construct(
		protected readonly IClassMap $classMap
	) {}

	abstract public static function getName(): string;

	abstract protected function getProviderName(): string;

	protected function getDefaultEndpoint(): string {
		return '';
	}

	protected function getDefaultChatCompletionPath(): string {
		return '/v1/chat/completions';
	}

	protected function getChatCompletionPath(): string {
		$path = trim((string)($this->options['chat_completion_path'] ?? ($this->options['path'] ?? '')));

		if ($path !== '') {
			return $path;
		}

		return $this->getDefaultChatCompletionPath();
	}

	protected function getDefaultModel(): string {
		return '';
	}

	protected function supportsTools(): bool {
		return true;
	}

	public function setOptions(array $options): void {
		$this->options = array_merge($this->options, $options);

		if ($this->provider instanceof IAiProvider) {
			$this->configureProvider($this->provider);
		}
	}

	public function getOptions(): array {
		return $this->options;
	}

	public function chat(array $messages): string {
		$result = $this->raw($messages);

		if (!isset($result['choices'][0]['message']['content'])) {
			throw new \RuntimeException('Malformed chat response: ' . json_encode($result));
		}

		return (string)$result['choices'][0]['message']['content'];
	}

	public function raw(array $messages, array $tools = []): mixed {
		$payload = $this->buildPayload($messages, $tools, false);

		return $this->getProvider()->request(
			$this->getChatCompletionPath(),
			$payload,
			$this->buildRequestOptions(false)
		);
	}

	public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void {
		$payload = $this->buildPayload($messages, $tools, true);
		$sseBuffer = '';

		$this->getProvider()->stream(
			$this->getChatCompletionPath(),
			$payload,
			function(string $chunk) use (&$sseBuffer, $onData, $onMeta) {
				$sseBuffer .= $chunk;
				$this->processSseBuffer($sseBuffer, $onData, $onMeta);
			},
			$this->buildRequestOptions(true)
		);

		$this->flushSseBuffer($sseBuffer, $onData, $onMeta);
	}

	protected function getProvider(): IAiProvider {
		if ($this->provider instanceof IAiProvider) {
			return $this->provider;
		}

		$provider = $this->classMap->getInstanceByInterfaceName(IAiProvider::class, $this->getProviderName());

		if (!$provider instanceof IAiProvider) {
			throw new \RuntimeException(
				'Unable to resolve provider "' . $this->getProviderName() . '" for interface ' . IAiProvider::class . '.'
			);
		}

		$this->configureProvider($provider);
		$this->provider = $provider;

		return $this->provider;
	}

	protected function configureProvider(IAiProvider $provider): void {
		$provider->setOptions([
			'endpoint' => $this->getEndpoint(),
			'apikey' => $this->getApiKey(),
			'timeout' => $this->getIntOption('timeout_seconds', 60),
			'connect_timeout' => $this->getIntOption('connect_timeout_seconds', 15),
		]);
	}

	/**
	 * @param array<int,array<string,mixed>> $messages
	 * @param array<int,array<string,mixed>> $tools
	 * @return array<string,mixed>
	 */
	protected function buildPayload(array $messages, array $tools, bool $stream): array {
		$model = $this->getModel();

		if ($model === '') {
			throw new \RuntimeException('Missing model name for chat completion model.');
		}

		$payload = [
			'model' => $model,
			'messages' => $this->normalizeMessages($messages),
		];

		$temperature = $this->getNullableFloatOption('temperature');
		if ($temperature !== null) {
			$payload['temperature'] = $temperature;
		}

		$maxTokens = $this->getNullableIntOption('max_tokens');
		if ($maxTokens !== null) {
			$payload['max_tokens'] = $maxTokens;
		}

		$topP = $this->getNullableFloatOption('top_p');
		if ($topP !== null) {
			$payload['top_p'] = $topP;
		}

		if ($stream) {
			$payload['stream'] = true;
		}

		if (!$stream && $this->supportsTools() && !empty($tools)) {
			$cleanTools = $this->sanitizeTools($tools);

			if (count($cleanTools) > 0) {
				$payload['tools'] = $cleanTools;
				$payload['tool_choice'] = $this->options['tool_choice'] ?? 'auto';
			}
		}

		return $payload;
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function buildRequestOptions(bool $stream): array {
		$options = [
			'timeout' => $this->getIntOption('timeout_seconds', 60),
			'connect_timeout' => $this->getIntOption('connect_timeout_seconds', 15),
		];

		if (isset($this->options['headers']) && is_array($this->options['headers'])) {
			$options['headers'] = $this->options['headers'];
		}

		return $options;
	}

	protected function getModel(): string {
		$model = trim((string)($this->options['model'] ?? ''));

		if ($model !== '') {
			return $model;
		}

		return $this->getDefaultModel();
	}

	protected function getEndpoint(): string {
		$endpoint = trim((string)($this->options['endpoint'] ?? ''));

		if ($endpoint !== '') {
			return $endpoint;
		}

		return $this->getDefaultEndpoint();
	}

	protected function getApiKey(): string {
		$apiKey = trim((string)($this->options['apikey'] ?? ''));

		if ($apiKey === '') {
			throw new \RuntimeException('Missing API key for chat completion model.');
		}

		return $apiKey;
	}

	protected function getNullableFloatOption(string $key): ?float {
		if (!array_key_exists($key, $this->options)) {
			return null;
		}

		$value = $this->options[$key];

		if ($value === null || $value === '') {
			return null;
		}

		if (!is_numeric($value)) {
			return null;
		}

		return (float)$value;
	}

	protected function getNullableIntOption(string $key): ?int {
		if (!array_key_exists($key, $this->options)) {
			if ($key === 'max_tokens' && array_key_exists('maxtokens', $this->options)) {
				$key = 'maxtokens';
			}
			else {
				return null;
			}
		}

		$value = $this->options[$key];

		if ($value === null || $value === '') {
			return null;
		}

		if (!is_numeric($value)) {
			return null;
		}

		return (int)$value;
	}

	protected function getIntOption(string $key, int $default): int {
		$value = $this->options[$key] ?? null;

		if ($value === null || $value === '' || !is_numeric($value)) {
			return $default;
		}

		$value = (int)$value;

		return $value > 0 ? $value : $default;
	}

	/**
	 * @param array<int,array<string,mixed>> $messages
	 * @return array<int,array<string,mixed>>
	 */
	protected function normalizeMessages(array $messages): array {
		$out = [];
		$validToolCallIds = [];

		foreach ($messages as $message) {
			if (!is_array($message) || !isset($message['role'])) {
				continue;
			}

			$role = (string)$message['role'];
			$content = $message['content'] ?? '';

			if ($role === 'assistant' && !empty($message['tool_calls']) && is_array($message['tool_calls'])) {
				$toolCalls = $this->normalizeToolCalls($message['tool_calls']);

				foreach ($toolCalls as $toolCall) {
					$validToolCallIds[(string)$toolCall['id']] = true;
				}

				if (count($toolCalls) > 0) {
					$out[] = [
						'role' => 'assistant',
						'content' => $this->normalizeMessageContent($content),
						'tool_calls' => $toolCalls,
					];

					continue;
				}
			}

			if ($role === 'tool') {
				$toolCallId = (string)($message['tool_call_id'] ?? '');

				if ($toolCallId === '' || empty($validToolCallIds[$toolCallId])) {
					continue;
				}

				$out[] = [
					'role' => 'tool',
					'tool_call_id' => $toolCallId,
					'content' => $this->normalizeMessageContent($content),
				];

				unset($validToolCallIds[$toolCallId]);

				continue;
			}

			$out[] = [
				'role' => $role,
				'content' => $this->normalizeMessageContent($content),
			];

			if (!empty($message['feedback']) && is_string($message['feedback'])) {
				$feedback = trim($message['feedback']);

				if ($feedback !== '') {
					$out[] = [
						'role' => 'user',
						'content' => $feedback,
					];
				}
			}
		}

		return $out;
	}

	/**
	 * @param array<int,mixed> $toolCalls
	 * @return array<int,array<string,mixed>>
	 */
	protected function normalizeToolCalls(array $toolCalls): array {
		$out = [];

		foreach ($toolCalls as $call) {
			if (!is_array($call)) {
				continue;
			}

			if (!isset($call['id'], $call['function']['name'])) {
				continue;
			}

			$out[] = [
				'id' => (string)$call['id'],
				'type' => 'function',
				'function' => [
					'name' => (string)$call['function']['name'],
					'arguments' => $this->normalizeToolArguments($call['function']['arguments'] ?? '{}'),
				],
			];
		}

		return $out;
	}

	protected function normalizeToolArguments(mixed $arguments): string {
		if (is_string($arguments)) {
			return $arguments;
		}

		$json = json_encode($arguments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if ($json === false) {
			return '{}';
		}

		return $json;
	}

	protected function normalizeMessageContent(mixed $content): string {
		if ($content === null) {
			return '';
		}

		if (is_string($content)) {
			return $content;
		}

		if (is_bool($content)) {
			return $content ? 'true' : 'false';
		}

		if (is_int($content) || is_float($content)) {
			return (string)$content;
		}

		$json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if ($json === false || $json === 'null') {
			return '';
		}

		return $json;
	}

	/**
	 * @param array<int,mixed> $tools
	 * @return array<int,array<string,mixed>>
	 */
	protected function sanitizeTools(array $tools): array {
		$out = [];

		foreach ($tools as $tool) {
			if (!is_array($tool)) {
				continue;
			}

			if (($tool['type'] ?? null) !== 'function') {
				continue;
			}

			$function = $tool['function'] ?? null;

			if (!is_array($function)) {
				continue;
			}

			$name = $function['name'] ?? null;

			if (!is_string($name) || $name === '') {
				continue;
			}

			$cleanFunction = [
				'name' => $name,
			];

			if (isset($function['description']) && is_string($function['description'])) {
				$cleanFunction['description'] = $function['description'];
			}

			$cleanFunction['parameters'] = $this->sanitizeParametersSchema($function['parameters'] ?? []);

			$out[] = [
				'type' => 'function',
				'function' => $cleanFunction,
			];
		}

		return $out;
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function sanitizeParametersSchema(mixed $schema): array {
		$schema = is_array($schema) ? $schema : [];

		if (($schema['type'] ?? null) !== 'object') {
			$schema['type'] = 'object';
		}

		if (!is_array($schema['properties'] ?? null)) {
			$schema['properties'] = new \stdClass();
		}

		if (isset($schema['required']) && !is_array($schema['required'])) {
			unset($schema['required']);
		}

		return $schema;
	}

	protected function processSseBuffer(string &$buffer, callable $onData, ?callable $onMeta): void {
		while (true) {
			$eventBlock = $this->extractNextSseEventBlock($buffer);

			if ($eventBlock === null) {
				return;
			}

			$this->handleSseEventBlock($eventBlock, $onData, $onMeta);
		}
	}

	protected function flushSseBuffer(string &$buffer, callable $onData, ?callable $onMeta): void {
		$remaining = trim($buffer);
		$buffer = '';

		if ($remaining === '') {
			return;
		}

		$this->handleSseEventBlock($remaining, $onData, $onMeta);
	}

	protected function extractNextSseEventBlock(string &$buffer): ?string {
		$separatorPos = null;
		$separatorLen = 0;

		foreach (["\r\n\r\n", "\n\n", "\r\r"] as $separator) {
			$pos = strpos($buffer, $separator);

			if ($pos === false) {
				continue;
			}

			if ($separatorPos === null || $pos < $separatorPos) {
				$separatorPos = $pos;
				$separatorLen = strlen($separator);
			}
		}

		if ($separatorPos === null) {
			return null;
		}

		$eventBlock = substr($buffer, 0, $separatorPos);
		$buffer = substr($buffer, $separatorPos + $separatorLen);

		return $eventBlock;
	}

	protected function handleSseEventBlock(string $eventBlock, callable $onData, ?callable $onMeta): void {
		$data = $this->extractSseDataPayload($eventBlock);

		if ($data === null) {
			return;
		}

		if ($data === '[DONE]') {
			if ($onMeta !== null) {
				$onMeta(['event' => 'done']);
			}

			return;
		}

		$json = json_decode($data, true);

		if (!is_array($json)) {
			return;
		}

		$choice = $json['choices'][0] ?? [];

		if ($onMeta !== null && isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
			$onMeta([
				'event' => 'meta',
				'finish_reason' => $choice['finish_reason'],
				'full' => $json,
			]);
		}

		$delta = $choice['delta']['content'] ?? null;

		if ($delta === null) {
			$delta = $choice['delta']['text'] ?? ($json['delta']['content'] ?? null);
		}

		if (is_string($delta) && $delta !== '') {
			$onData($delta);
		}

		if (!empty($choice['delta']['tool_calls']) && $onMeta !== null) {
			$onMeta([
				'event' => 'toolcall',
				'tool_calls' => $choice['delta']['tool_calls'],
			]);
		}
	}

	protected function extractSseDataPayload(string $eventBlock): ?string {
		$lines = preg_split("/\r\n|\n|\r/", $eventBlock);
		$dataLines = [];

		foreach ($lines as $line) {
			if ($line === '' || str_starts_with($line, ':')) {
				continue;
			}

			if (!str_starts_with($line, 'data:')) {
				continue;
			}

			$dataLines[] = ltrim(substr($line, 5), ' ');
		}

		if (count($dataLines) === 0) {
			return null;
		}

		return implode("\n", $dataLines);
	}
}
