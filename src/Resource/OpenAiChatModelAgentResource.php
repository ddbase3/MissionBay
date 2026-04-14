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

namespace MissionBay\Resource;

use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Api\IAiProvider;
use Base3\Api\IClassMap;
use MissionBay\AiProvider\OpenAiProvider;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * OpenAiChatModelAgentResource
 *
 * Provides access to OpenAI's Chat Completion API via a dockable resource.
 * Supports:
 * - rich messages (id, timestamp, feedback)
 * - standard chat()
 * - raw() non-streaming function-calls
 * - stream() streaming responses with token callbacks
 *
 * Important:
 * - We MUST not send orphaned tool messages to OpenAI. A tool message is only valid
 *   if it responds to a preceding assistant message that declared matching tool_calls.
 */
class OpenAiChatModelAgentResource extends AbstractAgentResource implements IAiChatModel {

	protected IAgentConfigValueResolver $resolver;
	protected IClassMap $classMap;

	protected array|string|null $modelConfig = null;
	protected array|string|null $apikeyConfig = null;
	protected array|string|null $endpointConfig = null;
	protected array|string|null $temperatureConfig = null;

	protected array $resolvedOptions = [];

	protected ?OpenAiProvider $provider = null;

	public function __construct(IAgentConfigValueResolver $resolver, IClassMap $classMap, ?string $id = null) {
		parent::__construct($id);
		$this->resolver = $resolver;
		$this->classMap = $classMap;
	}

	public static function getName(): string {
		return 'openaichatmodelagentresource';
	}

	public function getDescription(): string {
		return 'Connects to OpenAI Chat API (GPT models). Supports streaming + function calling.';
	}

	public function setConfig(array $config): void {
		parent::setConfig($config);

		$this->modelConfig = $config['model'] ?? null;
		$this->apikeyConfig = $config['apikey'] ?? null;
		$this->endpointConfig = $config['endpoint'] ?? null;
		$this->temperatureConfig = $config['temperature'] ?? null;

		$this->resolvedOptions = [
			'model' => $this->resolver->resolveValue($this->modelConfig) ?? 'gpt-4o-mini',
			'apikey' => $this->resolver->resolveValue($this->apikeyConfig),
			'endpoint' => $this->resolver->resolveValue($this->endpointConfig) ?? 'https://api.openai.com/v1/chat/completions',
			'temperature' => (float)($this->resolver->resolveValue($this->temperatureConfig) ?? 0.7),
		];

		$this->configureProvider();
	}

	public function getOptions(): array {
		return $this->resolvedOptions;
	}

	public function setOptions(array $options): void {
		$this->resolvedOptions = array_merge($this->resolvedOptions, $options);
		$this->configureProvider();
	}

	public function chat(array $messages): string {
		$result = $this->raw($messages);

		if(!isset($result['choices'][0]['message']['content'])) {
			throw new \RuntimeException('Malformed OpenAI chat response: ' . json_encode($result));
		}

		return (string)$result['choices'][0]['message']['content'];
	}

	public function raw(array $messages, array $tools = []): mixed {
		$model = $this->resolvedOptions['model'] ?? 'gpt-4o-mini';
		$temp = $this->resolvedOptions['temperature'] ?? 0.7;

		$normalized = $this->normalizeMessages($messages);

		$payload = [
			'model' => $model,
			'messages' => $normalized,
			'temperature' => $temp,
		];

		if(!empty($tools)) {
			$payload['tools'] = $tools;
			$payload['tool_choice'] = 'auto';
		}

		return $this->getProvider()->request('/v1/chat/completions', $payload);
	}

	public function stream(
		array $messages,
		array $tools,
		callable $onData,
		callable $onMeta = null
	): void {
		$model = $this->resolvedOptions['model'] ?? 'gpt-4o-mini';
		$temp = $this->resolvedOptions['temperature'] ?? 0.7;

		$normalized = $this->normalizeMessages($messages);

		$payload = [
			'model' => $model,
			'messages' => $normalized,
			'temperature' => $temp,
			'stream' => true,
		];

		if(!empty($tools)) {
			$payload['tools'] = $tools;
			$payload['tool_choice'] = 'auto';
		}

		$sseBuffer = '';

		$this->getProvider()->stream('/v1/chat/completions', $payload, function(string $chunk) use ($onData, $onMeta, &$sseBuffer) {
			$sseBuffer .= $chunk;
			$this->processSseBuffer($sseBuffer, $onData, $onMeta);
		});
		$this->flushSseBuffer($sseBuffer, $onData, $onMeta);
	}

	private function getProvider(): OpenAiProvider {
		if($this->provider instanceof OpenAiProvider) {
			return $this->provider;
		}

		$provider = $this->classMap->getInstanceByInterfaceName(IAiProvider::class, OpenAiProvider::getName());

		if(!$provider instanceof OpenAiProvider) {
			throw new \RuntimeException(
				'Unable to resolve provider "' . OpenAiProvider::getName() . '" for interface ' . IAiProvider::class . '.'
			);
		}

		$this->provider = $provider;
		$this->configureProvider();

		return $this->provider;
	}

	private function configureProvider(): void {
		if(!$this->provider instanceof OpenAiProvider) {
			return;
		}

		$this->provider->setOptions([
			'endpoint' => $this->resolvedOptions['endpoint'] ?? 'https://api.openai.com/v1/chat/completions',
			'apikey' => $this->resolvedOptions['apikey'] ?? null,
		]);
	}

	private function processSseBuffer(string &$buffer, callable $onData, ?callable $onMeta): void {
		while(true) {
			$eventBlock = $this->extractNextSseEventBlock($buffer);
			if($eventBlock === null) {
				return;
			}

			$this->handleSseEventBlock($eventBlock, $onData, $onMeta);
		}
	}

	private function flushSseBuffer(string &$buffer, callable $onData, ?callable $onMeta): void {
		$remaining = trim($buffer);
		$buffer = '';

		if($remaining === '') {
			return;
		}

		$this->handleSseEventBlock($remaining, $onData, $onMeta);
	}

	private function extractNextSseEventBlock(string &$buffer): ?string {
		$separatorPos = null;
		$separatorLen = 0;

		foreach(["\r\n\r\n", "\n\n", "\r\r"] as $separator) {
			$pos = strpos($buffer, $separator);
			if($pos === false) {
				continue;
			}

			if($separatorPos === null || $pos < $separatorPos) {
				$separatorPos = $pos;
				$separatorLen = strlen($separator);
			}
		}

		if($separatorPos === null) {
			return null;
		}

		$eventBlock = substr($buffer, 0, $separatorPos);
		$buffer = substr($buffer, $separatorPos + $separatorLen);

		return $eventBlock;
	}

	private function handleSseEventBlock(string $eventBlock, callable $onData, ?callable $onMeta): void {
		$data = $this->extractSseDataPayload($eventBlock);
		if($data === null) {
			return;
		}

		if($data === '[DONE]') {
			if($onMeta !== null) {
				$onMeta(['event' => 'done']);
			}
			return;
		}

		$json = json_decode($data, true);
		if(!is_array($json)) {
			return;
		}

		$choice = $json['choices'][0] ?? [];

		if($onMeta !== null && isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
			$onMeta([
				'event' => 'meta',
				'finish_reason' => $choice['finish_reason'],
				'full' => $json,
			]);
		}

		$delta = $choice['delta']['content'] ?? null;
		if($delta !== null) {
			$onData($delta);
		}

		if(!empty($choice['delta']['tool_calls']) && $onMeta !== null) {
			$onMeta([
				'event' => 'toolcall',
				'tool_calls' => $choice['delta']['tool_calls'],
			]);
		}
	}

	private function extractSseDataPayload(string $eventBlock): ?string {
		$lines = preg_split("/\r\n|\n|\r/", $eventBlock);
		$dataLines = [];

		foreach($lines as $line) {
			if($line === '' || str_starts_with($line, ':')) {
				continue;
			}

			if(!str_starts_with($line, 'data:')) {
				continue;
			}

			$dataLines[] = ltrim(substr($line, 5), ' ');
		}

		if(count($dataLines) === 0) {
			return null;
		}

		return implode("\n", $dataLines);
	}

	/**
	 * NORMALIZATION OF RICH MESSAGE OBJECTS
	 *
	 * Critical invariant:
	 * - A tool message can only be sent if we have seen a preceding assistant tool_calls
	 *   message that declared the same tool_call_id in THIS outgoing payload.
	 *
	 * Important normalization rules:
	 * - null content must become an empty string, not the literal string "null"
	 * - assistant tool-call planning messages must be preserved cleanly
	 * - orphaned tool messages must still be skipped
	 */
	private function normalizeMessages(array $messages): array {
		$out = [];
		$validToolCallIds = [];

		foreach($messages as $m) {
			if(!is_array($m) || !isset($m['role'])) {
				continue;
			}

			$role = (string)$m['role'];
			$content = $m['content'] ?? '';

			if($role === 'assistant' && !empty($m['tool_calls']) && is_array($m['tool_calls'])) {
				$toolCalls = [];

				foreach($m['tool_calls'] as $call) {
					if(!isset($call['id'], $call['function']['name'])) {
						continue;
					}

					$callId = (string)$call['id'];
					$args = $this->normalizeToolArguments($call['function']['arguments'] ?? '{}');

					$toolCalls[] = [
						'id' => $callId,
						'type' => 'function',
						'function' => [
							'name' => (string)$call['function']['name'],
							'arguments' => $args,
						],
					];

					$validToolCallIds[$callId] = true;
				}

				if(count($toolCalls) > 0) {
					$out[] = [
						'role' => 'assistant',
						'content' => $this->normalizeMessageContent($content),
						'tool_calls' => $toolCalls,
					];

					continue;
				}
			}

			if($role === 'tool') {
				$toolCallId = (string)($m['tool_call_id'] ?? '');

				if($toolCallId === '' || empty($validToolCallIds[$toolCallId])) {
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

			if(!empty($m['feedback']) && is_string($m['feedback'])) {
				$fb = trim($m['feedback']);
				if($fb !== '') {
					$out[] = [
						'role' => 'user',
						'content' => $fb,
					];
				}
			}
		}

		return $out;
	}

	private function normalizeToolArguments(mixed $args): string {
		if(is_string($args)) {
			return $args;
		}

		$json = json_encode($args, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if($json === false) {
			return '{}';
		}

		return $json;
	}

	private function normalizeMessageContent(mixed $content): string {
		if($content === null) {
			return '';
		}

		if(is_string($content)) {
			return $content;
		}

		if(is_bool($content)) {
			return $content ? 'true' : 'false';
		}

		if(is_int($content) || is_float($content)) {
			return (string)$content;
		}

		$json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if($json === false || $json === 'null') {
			return '';
		}

		return $json;
	}
}
