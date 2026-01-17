<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\GeminiChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * @covers \MissionBay\Resource\GeminiChatModelAgentResource
 */
class GeminiChatModelAgentResourceTest extends TestCase {

	private function makeResolver(array $map = []): IAgentConfigValueResolver {
		return new class($map) implements IAgentConfigValueResolver {
			private array $map;

			public function __construct(array $map) {
				$this->map = $map;
			}

			public function resolveValue(array|string|int|float|bool|null $config): mixed {
				$key = is_array($config) ? json_encode($config) : $config;

				if (is_string($key) && array_key_exists($key, $this->map)) {
					return $this->map[$key];
				}

				return $config;
			}
		};
	}

	public function testGetName(): void {
		$this->assertSame('geminichatmodelagentresource', GeminiChatModelAgentResource::getName());
	}

	public function testSetConfigResolvesDefaultsWhenMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new GeminiChatModelAgentResource($resolver, 'g1');

		$r->setConfig([]);

		$opts = $r->getOptions();
		$this->assertSame('gemini-1.5-flash', $opts['model']);
		$this->assertNull($opts['apikey']);
		$this->assertSame('https://generativelanguage.googleapis.com/v1beta/models', $opts['endpoint']);
		$this->assertSame(0.7, $opts['temperature']);
		$this->assertSame(4096, $opts['maxtokens']);
	}

	public function testSetConfigResolvesAndCastsValues(): void {
		$map = [
			'model_key' => 'gemini-2.0-flash-exp',
			'apikey_key' => 'sk-gemini-test',
			'endpoint_key' => 'https://example.com/v1beta/models',
			'temp_key' => '0.25',
			'max_key' => '123',
		];

		$resolver = $this->makeResolver($map);
		$r = new GeminiChatModelAgentResource($resolver, 'g2');

		$r->setConfig([
			'model' => 'model_key',
			'apikey' => 'apikey_key',
			'endpoint' => 'endpoint_key',
			'temperature' => 'temp_key',
			'maxtokens' => 'max_key',
		]);

		$opts = $r->getOptions();
		$this->assertSame('gemini-2.0-flash-exp', $opts['model']);
		$this->assertSame('sk-gemini-test', $opts['apikey']);
		$this->assertSame('https://example.com/v1beta/models', $opts['endpoint']);
		$this->assertSame(0.25, $opts['temperature']);
		$this->assertSame(123, $opts['maxtokens']);
	}

	public function testSetOptionsMergesIntoResolvedOptions(): void {
		$resolver = $this->makeResolver([]);
		$r = new GeminiChatModelAgentResource($resolver, 'g3');

		$r->setConfig([]);

		$r->setOptions([
			'temperature' => 0.11,
			'maxtokens' => 42,
			'model' => 'gemini-1.5-pro',
		]);

		$opts = $r->getOptions();
		$this->assertSame(0.11, $opts['temperature']);
		$this->assertSame(42, $opts['maxtokens']);
		$this->assertSame('gemini-1.5-pro', $opts['model']);
	}

	public function testChatThrowsOnMalformedOpenAiCompatibleResponse(): void {
		$resolver = $this->makeResolver([]);
		$r = new class($resolver, 'g4') extends GeminiChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				return ['choices' => [['message' => []]]]; // missing content
			}
		};

		$r->setConfig(['apikey' => 'dummy']); // avoid setConfig defaults confusion

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Malformed Gemini(OpenAI-mode) response:');

		$r->chat([['role' => 'user', 'content' => 'Hi']]);
	}

	public function testChatReturnsAssistantContentFromRaw(): void {
		$resolver = $this->makeResolver([]);
		$r = new class($resolver, 'g5') extends GeminiChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				return [
					'choices' => [
						[
							'message' => ['content' => 'Hello from Gemini'],
							'finish_reason' => 'stop',
						]
					]
				];
			}
		};

		$r->setConfig(['apikey' => 'dummy']);

		$out = $r->chat([['role' => 'user', 'content' => 'Hi']]);
		$this->assertSame('Hello from Gemini', $out);
	}

	public function testRawThrowsIfApiKeyMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new GeminiChatModelAgentResource($resolver, 'g6');
		$r->setConfig([]); // apikey resolves to null

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing Gemini API key.');

		$r->raw([['role' => 'user', 'content' => 'Hi']]);
	}

	public function testNormalizeMessagesConvertsRolesAndSerializesNonString(): void {
		$resolver = $this->makeResolver([]);
		$r = new GeminiChatModelAgentResource($resolver, 'g7');

		$ref = new \ReflectionClass(GeminiChatModelAgentResource::class);
		$method = $ref->getMethod('normalizeMessages');
		$method->setAccessible(true);

		$normalized = $method->invoke($r, [
			['role' => 'system', 'content' => 'You are helpful.'],
			['role' => 'user', 'content' => ['a' => 1]],
			['role' => 'assistant', 'content' => 'OK'],
			// NOTE: tool messages are skipped unless they reference a prior tool_call_id
			// that was introduced by an assistant tool_call in the SAME message history.
			['role' => 'tool', 'content' => 'result: 123'],
		]);

		$this->assertIsArray($normalized);
		$this->assertArrayHasKey('system', $normalized);
		$this->assertArrayHasKey('contents', $normalized);

		// In current implementation:
		// - system is extracted into $normalized['system']
		// - tool without tool_call_id is skipped (orphan tool message)
		// so we only get 2 contents: user + model
		$this->assertSame('You are helpful.', $normalized['system']);
		$this->assertCount(2, $normalized['contents']);

		// user stays user, non-string encoded
		$this->assertSame('user', $normalized['contents'][0]['role']);
		$this->assertSame('{"a":1}', $normalized['contents'][0]['parts'][0]['text']);

		// assistant -> model
		$this->assertSame('model', $normalized['contents'][1]['role']);
		$this->assertSame('OK', $normalized['contents'][1]['parts'][0]['text']);
	}

	public function testNormalizeToolsConvertsOpenAiToolSchemaToGeminiFunctionDeclarations(): void {
		$resolver = $this->makeResolver([]);
		$r = new GeminiChatModelAgentResource($resolver, 'g8');

		$ref = new \ReflectionClass(GeminiChatModelAgentResource::class);
		$method = $ref->getMethod('normalizeTools');
		$method->setAccessible(true);

		$tools = [
			[
				'type' => 'function',
				'function' => [
					'name' => 'get_weather',
					'description' => 'Fetch weather.',
					'parameters' => [
						'type' => 'object',
						'properties' => [
							'city' => ['type' => 'string'],
						],
						'required' => ['city'],
					]
				]
			],
			[
				'type' => 'function',
				// missing function key should be ignored by implementation
			],
		];

		$gemini = $method->invoke($r, $tools);

		$this->assertSame([
			[
				'name' => 'get_weather',
				'description' => 'Fetch weather.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'city' => ['type' => 'string'],
					],
					'required' => ['city'],
				]
			]
		], $gemini);
	}

	public function testStreamCallsOnDataAndOnMetaWhenFedJsonLines(): void {
		$resolver = $this->makeResolver([]);
		$r = new class($resolver, 'g9') extends GeminiChatModelAgentResource {
			public function testFeedChunk(string $chunk, callable $onData, ?callable $onMeta): void {
				$lines = preg_split("/\r\n|\n|\r/", $chunk);

				foreach ($lines as $line) {
					$line = trim($line);
					if ($line === '') {
						continue;
					}

					$json = json_decode($line, true);
					if (!is_array($json)) {
						continue;
					}

					$candidate = $json['candidates'][0] ?? null;
					if (!$candidate) {
						continue;
					}

					$parts = $candidate['content']['parts'][0] ?? [];

					if (isset($parts['text'])) {
						$onData($parts['text']);
					}

					if (isset($parts['functionCall']) && $onMeta !== null) {
						$onMeta([
							'event' => 'toolcall',
							'tool_calls' => [
								[
									'id' => 'tool_test', // deterministic for test
									'type' => 'function',
									'function' => [
										'name' => $parts['functionCall']['name'] ?? '',
										'arguments' => json_encode($parts['functionCall']['args'] ?? []),
									]
								]
							]
						]);
					}

					if ($onMeta !== null && isset($candidate['finishReason'])) {
						$onMeta([
							'event' => 'meta',
							'finish_reason' => $candidate['finishReason'],
						]);
					}
				}
			}
		};

		$dataChunks = [];
		$metaChunks = [];

		$onData = function (string $delta) use (&$dataChunks): void {
			$dataChunks[] = $delta;
		};

		$onMeta = function (array $meta) use (&$metaChunks): void {
			$metaChunks[] = $meta;
		};

		$chunk =
			'{"candidates":[{"content":{"parts":[{"text":"Hel"}]},"finishReason":null}]}' . "\n" .
			'{"candidates":[{"content":{"parts":[{"text":"lo","functionCall":{"name":"doThing","args":{"x":1}}}]},"finishReason":"stop"}]}' . "\n";

		$r->testFeedChunk($chunk, $onData, $onMeta);

		$this->assertSame(['Hel', 'lo'], $dataChunks);

		$this->assertCount(2, $metaChunks);
		$this->assertSame('toolcall', $metaChunks[0]['event']);
		$this->assertSame('doThing', $metaChunks[0]['tool_calls'][0]['function']['name']);
		$this->assertSame('{"x":1}', $metaChunks[0]['tool_calls'][0]['function']['arguments']);

		$this->assertSame('meta', $metaChunks[1]['event']);
		$this->assertSame('stop', $metaChunks[1]['finish_reason']);
	}
}
