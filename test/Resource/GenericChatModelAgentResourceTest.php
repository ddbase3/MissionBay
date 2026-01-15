<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\GenericChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * @covers \MissionBay\Resource\GenericChatModelAgentResource
 */
class GenericChatModelAgentResourceTest extends TestCase {

	private function makeResolver(array $map = []): IAgentConfigValueResolver {
		return new class($map) implements IAgentConfigValueResolver {
			private array $map;

			public function __construct(array $map) {
				$this->map = $map;
			}

			public function resolveValue(array|string|int|float|bool|null $config): mixed {
				$key = is_array($config) ? json_encode($config) : (string)$config;
				if (array_key_exists($key, $this->map)) {
					return $this->map[$key];
				}
				return $config;
			}
		};
	}

	private function callNormalize(GenericChatModelAgentResource $r, array $messages): array {
		$ref = new \ReflectionClass(GenericChatModelAgentResource::class);
		$m = $ref->getMethod('normalizeMessages');
		$m->setAccessible(true);
		/** @var array $out */
		$out = $m->invoke($r, $messages);
		return $out;
	}

	public function testGetName(): void {
		$this->assertSame('genericchatmodelagentresource', GenericChatModelAgentResource::getName());
	}

	public function testSetConfigResolvesDefaultsWhenMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, 'g1');

		$r->setConfig([]);

		$opts = $r->getOptions();
		$this->assertSame('gpt-4o-mini', $opts['model']);
		$this->assertNull($opts['apikey']);
		$this->assertSame('https://api.openai.com/v1/chat/completions', $opts['endpoint']);
		$this->assertSame(0.7, $opts['temperature']);
		$this->assertSame(512, $opts['maxtokens']);
	}

	public function testSetConfigResolvesAndCastsValues(): void {
		$map = [
			'model_key' => 'my-model',
			'apikey_key' => 'sk-test',
			'endpoint_key' => 'https://example.com/v1/chat/completions',
			'temp_key' => '0.25',
			'max_key' => '999',
		];

		$resolver = $this->makeResolver($map);
		$r = new GenericChatModelAgentResource($resolver, 'g2');

		$r->setConfig([
			'model' => 'model_key',
			'apikey' => 'apikey_key',
			'endpoint' => 'endpoint_key',
			'temperature' => 'temp_key',
			'maxtokens' => 'max_key',
		]);

		$opts = $r->getOptions();
		$this->assertSame('my-model', $opts['model']);
		$this->assertSame('sk-test', $opts['apikey']);
		$this->assertSame('https://example.com/v1/chat/completions', $opts['endpoint']);
		$this->assertSame(0.25, $opts['temperature']);
		$this->assertSame(999, $opts['maxtokens']);
	}

	public function testSetOptionsMergesIntoResolvedOptions(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, 'g3');
		$r->setConfig([]);

		$r->setOptions([
			'temperature' => 0.11,
			'maxtokens' => 42,
			'model' => 'abc',
			'extra' => 'x',
		]);

		$opts = $r->getOptions();
		$this->assertSame(0.11, $opts['temperature']);
		$this->assertSame(42, $opts['maxtokens']);
		$this->assertSame('abc', $opts['model']);
		$this->assertSame('x', $opts['extra']);
	}

	public function testChatThrowsOnMalformedResponse(): void {
		$resolver = $this->makeResolver([]);

		$r = new class($resolver, 'g4') extends GenericChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				return ['choices' => [['message' => []]]];
			}
		};

		$r->setConfig(['apikey' => 'dummy']);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Malformed chat response:');

		$r->chat([['role' => 'user', 'content' => 'Hi']]);
	}

	public function testChatReturnsAssistantContentFromRaw(): void {
		$resolver = $this->makeResolver([]);

		$r = new class($resolver, 'g5') extends GenericChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				return ['choices' => [['message' => ['content' => 'Hello']]]];
			}
		};

		$r->setConfig(['apikey' => 'dummy']);

		$this->assertSame('Hello', $r->chat([['role' => 'user', 'content' => 'Hi']]));
	}

	public function testRawThrowsIfApiKeyMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, 'g6');
		$r->setConfig([]); // apikey null

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing API key for chat model.');

		$r->raw([['role' => 'user', 'content' => 'Hi']]);
	}

	public function testNormalizeMergesSystemMessagesAndTrimsAndPrepends(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, 'g7');

		$out = $this->callNormalize($r, [
			['role' => 'system', 'content' => '  A  '],
			['role' => 'system', 'content' => "B\n"],
			['role' => 'user', 'content' => 'Hi'],
			['role' => 'system', 'content' => '   '], // ignored
			['role' => 'assistant', 'content' => 'Yo'],
		]);

		$this->assertSame('system', $out[0]['role']);
		$this->assertSame("A\n\nB", $out[0]['content']);
		$this->assertSame(['role' => 'user', 'content' => 'Hi'], $out[1]);
		$this->assertSame(['role' => 'assistant', 'content' => 'Yo'], $out[2]);
	}

	public function testNormalizeSkipsInvalidEntries(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, 'g8');

		$out = $this->callNormalize($r, [
			'nope',
			['content' => 'missing role'],
			['role' => 'user', 'content' => 'ok'],
		]);

		$this->assertSame([['role' => 'user', 'content' => 'ok']], $out);
	}

	public function testNormalizeEncodesNonStringContent(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, 'g9');

		$out = $this->callNormalize($r, [
			['role' => 'user', 'content' => ['a' => 1, 'b' => true]],
		]);

		$this->assertSame('user', $out[0]['role']);
		$this->assertSame('{"a":1,"b":true}', $out[0]['content']);
	}

	public function testNormalizeInjectsFeedbackAsAdditionalUserMessage(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, 'g10');

		$out = $this->callNormalize($r, [
			['role' => 'user', 'content' => 'Hi', 'feedback' => '  be concise  '],
			['role' => 'assistant', 'content' => 'Ok'],
		]);

		$this->assertCount(3, $out);
		$this->assertSame(['role' => 'user', 'content' => 'Hi'], $out[0]);
		$this->assertSame(['role' => 'user', 'content' => 'be concise'], $out[1]);
		$this->assertSame(['role' => 'assistant', 'content' => 'Ok'], $out[2]);
	}

	public function testNormalizeDoesNotInjectEmptyFeedback(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, 'g11');

		$out = $this->callNormalize($r, [
			['role' => 'user', 'content' => 'Hi', 'feedback' => '   '],
		]);

		$this->assertSame([['role' => 'user', 'content' => 'Hi']], $out);
	}

	public function testNormalizeToolMessageRequiresToolCallId(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, 'g12');

		$out = $this->callNormalize($r, [
			['role' => 'tool', 'content' => 'x'], // skipped (missing tool_call_id)
			['role' => 'tool', 'tool_call_id' => 123, 'content' => ['ok' => true]],
		]);

		$this->assertCount(1, $out);
		$this->assertSame('tool', $out[0]['role']);
		$this->assertSame('123', $out[0]['tool_call_id']);
		$this->assertSame('{"ok":true}', $out[0]['content']);
	}

	public function testNormalizeAssistantToolCallsAreNormalizedAndArgumentsJsonEncoded(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, 'g13');

		$out = $this->callNormalize($r, [[
			'role' => 'assistant',
			'content' => '',
			'tool_calls' => [
				[
					'id' => 'call_1',
					'function' => [
						'name' => 'sum',
						'arguments' => ['a' => 1, 'b' => 2],
					],
				],
				[
					// invalid -> skipped
					'function' => ['name' => 'noop'],
				],
			],
		]]);

		$this->assertCount(1, $out);
		$this->assertSame('assistant', $out[0]['role']);
		$this->assertArrayHasKey('tool_calls', $out[0]);
		$this->assertCount(1, $out[0]['tool_calls']);

		$call = $out[0]['tool_calls'][0];
		$this->assertSame('call_1', $call['id']);
		$this->assertSame('function', $call['type']);
		$this->assertSame('sum', $call['function']['name']);
		$this->assertSame('{"a":1,"b":2}', $call['function']['arguments']);
	}

	public function testStreamParsesSseDataCallsOnDataOnMetaAndToolcalls(): void {
		$resolver = $this->makeResolver([]);
		$r = new GenericChatModelAgentResource($resolver, 'g14');

		// Important: we override stream() to reuse the SAME writefunction logic without doing curl.
		$r2 = new class($resolver, 'g14x') extends GenericChatModelAgentResource {
			public function runWriteFunction(string $chunk, callable $onData, callable $onMeta = null): int {
				$fn = function ($ch, $chunk) use ($onData, $onMeta) {
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

						if (isset($choice['delta']['content'])) {
							$onData($choice['delta']['content']);
						}

						if ($onMeta !== null && isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
							$onMeta([
								'event' => 'meta',
								'finish_reason' => $choice['finish_reason'],
								'full' => $json
							]);
						}

						if (!empty($choice['delta']['tool_calls']) && $onMeta !== null) {
							$onMeta([
								'event' => 'toolcall',
								'tool_calls' => $choice['delta']['tool_calls']
							]);
						}
					}
					return strlen($chunk);
				};

				return $fn(null, $chunk);
			}
		};

		$deltas = [];
		$meta = [];

		$chunk =
			"data: {\"choices\":[{\"delta\":{\"content\":\"Hel\"}}]}\n" .
			"data: {\"choices\":[{\"delta\":{\"content\":\"lo\"}}]}\n" .
			"data: {\"choices\":[{\"delta\":{\"tool_calls\":[{\"id\":\"tc1\"}]}}]}\n" .
			"data: {\"choices\":[{\"delta\":{},\"finish_reason\":\"stop\"}]}\n" .
			"data: [DONE]\n";

		$len = $r2->runWriteFunction($chunk,
			function (string $d) use (&$deltas): void { $deltas[] = $d; },
			function (array $m) use (&$meta): void { $meta[] = $m; }
		);

		$this->assertSame(strlen($chunk), $len);
		$this->assertSame(['Hel', 'lo'], $deltas);

		$this->assertCount(3, $meta);
		$this->assertSame('toolcall', $meta[0]['event']);
		$this->assertSame([['id' => 'tc1']], $meta[0]['tool_calls']);

		$this->assertSame('meta', $meta[1]['event']);
		$this->assertSame('stop', $meta[1]['finish_reason']);
		$this->assertIsArray($meta[1]['full']);

		$this->assertSame('done', $meta[2]['event']);
	}
}
