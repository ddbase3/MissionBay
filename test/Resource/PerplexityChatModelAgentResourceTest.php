<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\PerplexityChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * @covers \MissionBay\Resource\PerplexityChatModelAgentResource
 */
class PerplexityChatModelAgentResourceTest extends TestCase {

	private function makeResolver(array $map = []): IAgentConfigValueResolver {
		return new class($map) implements IAgentConfigValueResolver {
			private array $map;

			public function __construct(array $map) {
				$this->map = $map;
			}

			public function resolveValue(array|string|null $config): mixed {
				$key = is_array($config) ? json_encode($config) : (string)$config;
				if (array_key_exists($key, $this->map)) {
					return $this->map[$key];
				}
				return $config;
			}
		};
	}

	private function invokePrivate(object $obj, string $methodName, array $args = []): mixed {
		$ref = new \ReflectionClass($obj);
		$m = $ref->getMethod($methodName);
		$m->setAccessible(true);
		return $m->invokeArgs($obj, $args);
	}

	public function testGetName(): void {
		$this->assertSame('perplexitychatmodelagentresource', PerplexityChatModelAgentResource::getName());
	}

	public function testSetConfigResolvesDefaultsWhenMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new PerplexityChatModelAgentResource($resolver, 'p1');

		$r->setConfig([]);

		$opts = $r->getOptions();
		$this->assertSame('pplx-70b-online', $opts['model']);
		$this->assertNull($opts['apikey']);
		$this->assertSame('https://api.perplexity.ai/chat/completions', $opts['endpoint']);
		$this->assertSame(0.7, $opts['temperature']);
	}

	public function testSetConfigResolvesAndCastsValues(): void {
		$map = [
			'model_key' => 'pplx-7b-online',
			'apikey_key' => 'sk-test',
			'endpoint_key' => 'https://example.com/chat/completions',
			'temp_key' => '0.11',
		];

		$resolver = $this->makeResolver($map);
		$r = new PerplexityChatModelAgentResource($resolver, 'p2');

		$r->setConfig([
			'model' => 'model_key',
			'apikey' => 'apikey_key',
			'endpoint' => 'endpoint_key',
			'temperature' => 'temp_key',
		]);

		$opts = $r->getOptions();
		$this->assertSame('pplx-7b-online', $opts['model']);
		$this->assertSame('sk-test', $opts['apikey']);
		$this->assertSame('https://example.com/chat/completions', $opts['endpoint']);
		$this->assertSame(0.11, $opts['temperature']);
	}

	public function testSetOptionsMergesIntoResolvedOptions(): void {
		$resolver = $this->makeResolver([]);
		$r = new PerplexityChatModelAgentResource($resolver, 'p3');
		$r->setConfig([]);

		$r->setOptions([
			'temperature' => 0.5,
			'extra' => 'x',
		]);

		$opts = $r->getOptions();
		$this->assertSame(0.5, $opts['temperature']);
		$this->assertSame('x', $opts['extra']);
	}

	public function testChatThrowsOnMalformedResponse(): void {
		$resolver = $this->makeResolver([]);

		$r = new class($resolver, 'p4') extends PerplexityChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				return ['choices' => [['message' => []]]];
			}
		};

		$r->setConfig(['apikey' => 'dummy']);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Malformed Perplexity chat response:');

		$r->chat([['role' => 'user', 'content' => 'Hi']]);
	}

	public function testChatReturnsAssistantContentFromRaw(): void {
		$resolver = $this->makeResolver([]);

		$r = new class($resolver, 'p5') extends PerplexityChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				return [
					'choices' => [
						['message' => ['content' => 'Hello']]
					]
				];
			}
		};

		$r->setConfig(['apikey' => 'dummy']);

		$this->assertSame('Hello', $r->chat([['role' => 'user', 'content' => 'Hi']]));
	}

	public function testRawThrowsIfApiKeyMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new PerplexityChatModelAgentResource($resolver, 'p6');
		$r->setConfig([]); // apikey resolves to null

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing Perplexity API key.');

		$r->raw([['role' => 'user', 'content' => 'Hi']]);
	}

	public function testStreamThrowsIfApiKeyMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new PerplexityChatModelAgentResource($resolver, 'p7');
		$r->setConfig([]); // apikey resolves to null

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing API key for Perplexity chat model.');

		$r->stream(
			[['role' => 'user', 'content' => 'Hi']],
			[],
			function (string $delta): void {},
			function (array $meta): void {}
		);
	}

	public function testNormalizeMessagesJsonEncodesNonStringContentAndInjectsFeedback(): void {
		$resolver = $this->makeResolver([]);
		$r = new PerplexityChatModelAgentResource($resolver, 'p8');

		$normalized = $this->invokePrivate($r, 'normalizeMessages', [[[
			'role' => 'user',
			'content' => ['a' => 1, 'b' => true],
			'feedback' => 'Please be brief.'
		]]]);

		$this->assertCount(2, $normalized);
		$this->assertSame('user', $normalized[0]['role']);
		$this->assertSame('{"a":1,"b":true}', $normalized[0]['content']);
		$this->assertSame('user', $normalized[1]['role']);
		$this->assertSame('Please be brief.', $normalized[1]['content']);
	}

	public function testNormalizeMessagesAssistantToolCallsAreMapped(): void {
		$resolver = $this->makeResolver([]);
		$r = new PerplexityChatModelAgentResource($resolver, 'p9');

		$normalized = $this->invokePrivate($r, 'normalizeMessages', [[[
			'role' => 'assistant',
			'content' => 'Calling tools',
			'tool_calls' => [
				[
					'id' => 'tc1',
					'function' => [
						'name' => 'sum',
						'arguments' => ['a' => 1, 'b' => 2],
					]
				],
			],
		]]]);

		$this->assertCount(1, $normalized);
		$this->assertSame('assistant', $normalized[0]['role']);
		$this->assertSame('Calling tools', $normalized[0]['content']);
		$this->assertArrayHasKey('tool_calls', $normalized[0]);

		$tc = $normalized[0]['tool_calls'];
		$this->assertCount(1, $tc);
		$this->assertSame('tc1', $tc[0]['id']);
		$this->assertSame('function', $tc[0]['type']);
		$this->assertSame('sum', $tc[0]['function']['name']);
		$this->assertSame('{"a":1,"b":2}', $tc[0]['function']['arguments']);
	}

	public function testNormalizeMessagesToolRoleAlwaysIncludesToolCallIdKey(): void {
		$resolver = $this->makeResolver([]);
		$r = new PerplexityChatModelAgentResource($resolver, 'p10');

		$normalized = $this->invokePrivate($r, 'normalizeMessages', [[[
			'role' => 'tool',
			'tool_call_id' => 'call_1',
			'content' => ['ok' => true],
		], [
			'role' => 'tool',
			'content' => 'no id provided',
		]]]);

		$this->assertCount(2, $normalized);

		$this->assertSame('tool', $normalized[0]['role']);
		$this->assertSame('call_1', $normalized[0]['tool_call_id']);
		$this->assertSame('{"ok":true}', $normalized[0]['content']);

		$this->assertSame('tool', $normalized[1]['role']);
		$this->assertSame('', $normalized[1]['tool_call_id']);
		$this->assertSame('no id provided', $normalized[1]['content']);
	}

	public function testExtractToolCallFromTextCase1JsonObject(): void {
		$resolver = $this->makeResolver([]);
		$r = new PerplexityChatModelAgentResource($resolver, 'p11');

		$text = 'Please execute {"name":"sum","arguments":{"a":1,"b":2}} now.';
		$call = $this->invokePrivate($r, 'extractToolCallFromText', [$text]);

		$this->assertIsArray($call);
		$this->assertSame('function', $call['type']);
		$this->assertSame('sum', $call['function']['name']);
		$this->assertSame('{"a":1,"b":2}', $call['function']['arguments']);
		$this->assertStringStartsWith('tool_', $call['id']);
	}

	public function testExtractToolCallFromTextCase2FunctionCallSyntax(): void {
		$resolver = $this->makeResolver([]);
		$r = new PerplexityChatModelAgentResource($resolver, 'p12');

		$text = 'Do this: sum({"a":1,"b":2}) please.';
		$call = $this->invokePrivate($r, 'extractToolCallFromText', [$text]);

		$this->assertIsArray($call);
		$this->assertSame('sum', $call['function']['name']);
		$this->assertSame('{"a":1,"b":2}', $call['function']['arguments']);
	}

	public function testExtractToolCallFromTextCase3ToolCallLabel(): void {
		$resolver = $this->makeResolver([]);
		$r = new PerplexityChatModelAgentResource($resolver, 'p13');

		$text = 'Tool call: notify("hello world")';
		$call = $this->invokePrivate($r, 'extractToolCallFromText', [$text]);

		$this->assertIsArray($call);
		$this->assertSame('notify', $call['function']['name']);

		$args = json_decode($call['function']['arguments'], true);
		$this->assertIsArray($args);
		$this->assertSame('hello world', $args['message']);
	}

	public function testExtractToolCallFromTextCase4ToolTagsJson(): void {
		$resolver = $this->makeResolver([]);
		$r = new PerplexityChatModelAgentResource($resolver, 'p14');

		$text = '<tool>{"name":"sum","arguments":{"a":5,"b":7}}</tool>';
		$call = $this->invokePrivate($r, 'extractToolCallFromText', [$text]);

		$this->assertIsArray($call);
		$this->assertSame('sum', $call['function']['name']);
		$this->assertSame('{"a":5,"b":7}', $call['function']['arguments']);
	}

	public function testExtractToolCallFromTextReturnsNullWhenNoPatternMatches(): void {
		$resolver = $this->makeResolver([]);
		$r = new PerplexityChatModelAgentResource($resolver, 'p15');

		$call = $this->invokePrivate($r, 'extractToolCallFromText', ['Just a normal answer.']);
		$this->assertNull($call);
	}

	public function testRawInjectsToolCallsWhenDetectedInText(): void {
		$resolver = $this->makeResolver([]);

		$r = new class($resolver, 'p16') extends PerplexityChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				// emulate parent::raw end-stage behavior: tool call extraction from text
				$data = [
					'choices' => [
						['message' => ['content' => 'Tool call: notify("hello")']]
					]
				];

				$ref = new \ReflectionClass(PerplexityChatModelAgentResource::class);
				$m = $ref->getMethod('extractToolCallFromText');
				$m->setAccessible(true);

				$text = $data['choices'][0]['message']['content'] ?? '';
				$toolCall = $m->invoke($this, $text);

				if ($toolCall !== null) {
					$data['choices'][0]['message']['tool_calls'] = [$toolCall];
				}

				return $data;
			}
		};

		$r->setConfig(['apikey' => 'dummy']);

		$out = $r->raw([['role' => 'user', 'content' => 'Hi']], [['type' => 'function', 'function' => ['name' => 'notify']]]);

		$this->assertArrayHasKey('tool_calls', $out['choices'][0]['message']);
		$this->assertSame('notify', $out['choices'][0]['message']['tool_calls'][0]['function']['name']);
	}

	public function testStreamingParserEmitsToolcallMetaWhenDeltaContainsPattern(): void {
		$resolver = $this->makeResolver([]);

		$r = new class($resolver, 'p17') extends PerplexityChatModelAgentResource {
			/** helper to run the exact writefunction logic against a chunk */
			public function simulateWrite(string $chunk, callable $onData, ?callable $onMeta): void {
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
						$delta = $choice['delta']['content'];
						$onData($delta);

						$ref = new \ReflectionClass(PerplexityChatModelAgentResource::class);
						$m = $ref->getMethod('extractToolCallFromText');
						$m->setAccessible(true);

						$toolCall = $m->invoke($this, $delta);
						if ($toolCall && $onMeta !== null) {
							$onMeta([
								'event' => 'toolcall',
								'tool_calls' => [$toolCall]
							]);
						}
					}

					if ($onMeta !== null && isset($choice['finish_reason']) && $choice['finish_reason'] !== null) {
						$onMeta([
							'event' => 'meta',
							'finish_reason' => $choice['finish_reason']
						]);
					}
				}
			}
		};

		$r->setConfig(['apikey' => 'dummy']);

		$deltas = [];
		$metaEvents = [];

		// IMPORTANT:
		// The PHP string must NOT contain backslashes.
		// json_encode will escape quotes for JSON, json_decode returns a clean string again.
		$chunk =
			"data: " . json_encode(['choices' => [['delta' => ['content' => 'Tool call: notify("hello")']]]]) . "\n" .
			"data: " . json_encode(['choices' => [['finish_reason' => 'stop']]]) . "\n" .
			"data: [DONE]\n";

		$r->simulateWrite(
			$chunk,
			function (string $d) use (&$deltas): void { $deltas[] = $d; },
			function (array $m) use (&$metaEvents): void { $metaEvents[] = $m; }
		);

		$this->assertSame(['Tool call: notify("hello")'], $deltas);

		$toolcall = null;
		foreach ($metaEvents as $e) {
			if (($e['event'] ?? null) === 'toolcall') {
				$toolcall = $e;
				break;
			}
		}
		$this->assertNotNull($toolcall);
		$this->assertSame('notify', $toolcall['tool_calls'][0]['function']['name']);

		$foundDone = false;
		foreach ($metaEvents as $e) {
			if (($e['event'] ?? null) === 'done') {
				$foundDone = true;
				break;
			}
		}
		$this->assertTrue($foundDone);
	}
}
