<?php declare(strict_types=1);

/**
 * Filename: plugin/MissionBay/test/Resource/OpenAiChatModelAgentResourceTest.php
 */

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\OpenAiChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * @covers \MissionBay\Resource\OpenAiChatModelAgentResource
 */
class OpenAiChatModelAgentResourceTest extends TestCase {

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

	private function callNormalize(OpenAiChatModelAgentResource $r, array $messages): array {
		$ref = new \ReflectionClass(OpenAiChatModelAgentResource::class);
		$m = $ref->getMethod('normalizeMessages');
		$m->setAccessible(true);
		/** @var array $out */
		$out = $m->invoke($r, $messages);
		return $out;
	}

	public function testGetName(): void {
		$this->assertSame('openaichatmodelagentresource', OpenAiChatModelAgentResource::getName());
	}

	public function testSetConfigResolvesDefaultsWhenMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenAiChatModelAgentResource($resolver, 'r1');

		$r->setConfig([]);

		$opts = $r->getOptions();
		$this->assertSame('gpt-4o-mini', $opts['model']);
		$this->assertNull($opts['apikey']);
		$this->assertSame('https://api.openai.com/v1/chat/completions', $opts['endpoint']);
		$this->assertSame(0.7, $opts['temperature']);
	}

	public function testSetConfigResolvesAndCastsTemperature(): void {
		$resolver = $this->makeResolver([
			'model_key' => 'gpt-4.1-mini',
			'apikey_key' => 'sk-test',
			'endpoint_key' => 'https://example.com/v1/chat/completions',
			'temp_key' => '0.12',
		]);

		$r = new OpenAiChatModelAgentResource($resolver, 'r2');

		$r->setConfig([
			'model' => 'model_key',
			'apikey' => 'apikey_key',
			'endpoint' => 'endpoint_key',
			'temperature' => 'temp_key',
		]);

		$opts = $r->getOptions();
		$this->assertSame('gpt-4.1-mini', $opts['model']);
		$this->assertSame('sk-test', $opts['apikey']);
		$this->assertSame('https://example.com/v1/chat/completions', $opts['endpoint']);
		$this->assertSame(0.12, $opts['temperature']);
	}

	public function testSetOptionsMergesIntoResolvedOptions(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenAiChatModelAgentResource($resolver, 'r3');
		$r->setConfig([]);

		$r->setOptions([
			'temperature' => 0.99,
			'model' => 'gpt-4o-mini',
			'extra' => 'x',
		]);

		$opts = $r->getOptions();
		$this->assertSame(0.99, $opts['temperature']);
		$this->assertSame('gpt-4o-mini', $opts['model']);
		$this->assertSame('x', $opts['extra']);
	}

	public function testChatThrowsOnMalformedResponse(): void {
		$resolver = $this->makeResolver([]);

		$r = new class($resolver, 'r4') extends OpenAiChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				return ['choices' => [['message' => []]]];
			}
		};

		$r->setConfig(['apikey' => 'dummy']);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Malformed OpenAI chat response:');

		$r->chat([['role' => 'user', 'content' => 'Hi']]);
	}

	public function testChatReturnsAssistantContentFromRaw(): void {
		$resolver = $this->makeResolver([]);

		$r = new class($resolver, 'r5') extends OpenAiChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				return [
					'choices' => [
						['message' => ['content' => 'Hello!']]
					]
				];
			}
		};

		$r->setConfig(['apikey' => 'dummy']);

		$this->assertSame('Hello!', $r->chat([['role' => 'user', 'content' => 'Hi']]));
	}

	public function testRawThrowsIfApiKeyMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenAiChatModelAgentResource($resolver, 'r6');
		$r->setConfig([]);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing API key for OpenAI chat model.');

		$r->raw([['role' => 'user', 'content' => 'Hi']]);
	}

	public function testNormalizeMessagesSkipsInvalidEntries(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenAiChatModelAgentResource($resolver, 'r7');

		$normalized = $this->callNormalize($r, [
			'not-an-array',
			['content' => 'missing role'],
			['role' => 'user', 'content' => 'ok'],
		]);

		$this->assertSame([['role' => 'user', 'content' => 'ok']], $normalized);
	}

	public function testNormalizeMessagesInjectsFeedbackAsExtraUserMessage(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenAiChatModelAgentResource($resolver, 'r8');

		$normalized = $this->callNormalize($r, [[
			'role' => 'user',
			'content' => 'Hi',
			'feedback' => '  Please be concise.  ',
		]]);

		$this->assertSame([
			['role' => 'user', 'content' => 'Hi'],
			['role' => 'user', 'content' => 'Please be concise.'],
		], $normalized);
	}

	public function testNormalizeMessagesDoesNotInjectEmptyFeedback(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenAiChatModelAgentResource($resolver, 'r9');

		$normalized = $this->callNormalize($r, [[
			'role' => 'user',
			'content' => 'Hi',
			'feedback' => '   ',
		]]);

		$this->assertSame([
			['role' => 'user', 'content' => 'Hi'],
		], $normalized);
	}

	public function testNormalizeMessagesEncodesNonStringContent(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenAiChatModelAgentResource($resolver, 'r10');

		$normalized = $this->callNormalize($r, [[
			'role' => 'user',
			'content' => ['a' => 1, 'b' => true],
		]]);

		$this->assertSame([
			['role' => 'user', 'content' => '{"a":1,"b":true}'],
		], $normalized);
	}

	public function testNormalizeMessagesToolRoleRequiresToolCallId(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenAiChatModelAgentResource($resolver, 'r11');

		// IMPORTANT:
		// Prod code deliberately drops orphaned tool messages unless a matching assistant tool_calls
		// exists in the SAME payload. So we must include the assistant tool_calls first.
		$normalized = $this->callNormalize($r, [
			[
				'role' => 'assistant',
				'content' => '',
				'tool_calls' => [
					[
						'id' => '123',
						'function' => [
							'name' => 'noop',
							'arguments' => '{}',
						],
					],
				],
			],
			['role' => 'tool', 'content' => 'x'], // missing tool_call_id -> skipped
			['role' => 'tool', 'tool_call_id' => 123, 'content' => ['ok' => 1]],
		]);

		// We only assert the tool message is preserved and normalized (stringified id + json content).
		// Assistant tool_calls normalization is already covered by the next test.
		$this->assertSame([
			[
				'role' => 'assistant',
				'content' => '',
				'tool_calls' => [
					[
						'id' => '123',
						'type' => 'function',
						'function' => [
							'name' => 'noop',
							'arguments' => '{}',
						],
					],
				],
			],
			[
				'role' => 'tool',
				'tool_call_id' => '123',
				'content' => '{"ok":1}',
			],
		], $normalized);
	}

	public function testNormalizeMessagesAssistantToolCallsAreMappedToOpenAiShape(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenAiChatModelAgentResource($resolver, 'r12');

		$normalized = $this->callNormalize($r, [[
			'role' => 'assistant',
			'content' => 'Calling tools...',
			'tool_calls' => [
				[
					'id' => 'call_1',
					'function' => [
						'name' => 'doThing',
						'arguments' => ['a' => 1],
					],
				],
				[
					// invalid: missing id -> should be skipped
					'function' => ['name' => 'nope', 'arguments' => '{}'],
				],
				[
					'id' => 987,
					'function' => [
						'name' => 'doOther',
						'arguments' => '{"x":true}',
					],
				],
			],
		]]);

		$this->assertSame([
			[
				'role' => 'assistant',
				'content' => 'Calling tools...',
				'tool_calls' => [
					[
						'id' => 'call_1',
						'type' => 'function',
						'function' => [
							'name' => 'doThing',
							'arguments' => '{"a":1}',
						],
					],
					[
						'id' => '987',
						'type' => 'function',
						'function' => [
							'name' => 'doOther',
							'arguments' => '{"x":true}',
						],
					],
				],
			],
		], $normalized);
	}
}
