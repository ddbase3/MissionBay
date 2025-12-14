<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\OpenRouterChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * @covers \MissionBay\Resource\OpenRouterChatModelAgentResource
 */
class OpenRouterChatModelAgentResourceTest extends TestCase {

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

	public function testGetName(): void {
		$this->assertSame('openrouterchatmodelagentresource', OpenRouterChatModelAgentResource::getName());
	}

	public function testSetConfigResolvesDefaultsWhenMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenRouterChatModelAgentResource($resolver, 'r1');

		$r->setConfig([]);

		$opts = $r->getOptions();

		$this->assertSame('mistralai/mistral-medium', $opts['model']);
		$this->assertNull($opts['apikey']);
		$this->assertSame('https://openrouter.ai/api/v1/chat/completions', $opts['endpoint']);
		$this->assertSame(0.3, $opts['temperature']);
		$this->assertSame(512, $opts['maxtokens']);
	}

	public function testSetConfigResolvesAndCastsValues(): void {
		$map = [
			'model_key' => 'qwen/qwen-2.5-72b-instruct',
			'apikey_key' => 'sk-test',
			'endpoint_key' => 'https://example.com/v1/chat/completions',
			'temp_key' => '0.9',
			'max_key' => '1234',
		];

		$resolver = $this->makeResolver($map);
		$r = new OpenRouterChatModelAgentResource($resolver, 'r2');

		$r->setConfig([
			'model' => 'model_key',
			'apikey' => 'apikey_key',
			'endpoint' => 'endpoint_key',
			'temperature' => 'temp_key',
			'maxtokens' => 'max_key',
		]);

		$opts = $r->getOptions();
		$this->assertSame('qwen/qwen-2.5-72b-instruct', $opts['model']);
		$this->assertSame('sk-test', $opts['apikey']);
		$this->assertSame('https://example.com/v1/chat/completions', $opts['endpoint']);
		$this->assertSame(0.9, $opts['temperature']);
		$this->assertSame(1234, $opts['maxtokens']);
	}

	public function testSetOptionsMergesIntoResolvedOptions(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenRouterChatModelAgentResource($resolver, 'r3');
		$r->setConfig([]);

		$r->setOptions([
			'temperature' => 0.11,
			'maxtokens' => 42,
			'model' => 'mistralai/mistral-small',
			'extra' => 'x',
		]);

		$opts = $r->getOptions();
		$this->assertSame(0.11, $opts['temperature']);
		$this->assertSame(42, $opts['maxtokens']);
		$this->assertSame('mistralai/mistral-small', $opts['model']);
		$this->assertSame('x', $opts['extra']);
	}

	public function testRawThrowsIfApiKeyMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenRouterChatModelAgentResource($resolver, 'r4');
		$r->setConfig([]); // apikey resolves to null

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing API key for OpenRouter.');

		$r->raw([['role' => 'user', 'content' => 'Hi']]);
	}

	public function testStreamThrowsIfApiKeyMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenRouterChatModelAgentResource($resolver, 'r5');
		$r->setConfig([]); // apikey resolves to null

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing API key for OpenRouter.');

		$r->stream(
			[['role' => 'user', 'content' => 'Hi']],
			[],
			function (string $delta): void {},
			function (array $meta): void {}
		);
	}

	public function testChatReturnsAssistantContentFromRaw(): void {
		$resolver = $this->makeResolver([]);

		$r = new class($resolver, 'r6') extends OpenRouterChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				return [
					'choices' => [
						['message' => ['content' => 'Hello Daniel']]
					]
				];
			}
		};

		$r->setConfig(['apikey' => 'dummy']);

		$out = $r->chat([['role' => 'user', 'content' => 'Hi']]);
		$this->assertSame('Hello Daniel', $out);
	}

	public function testNormalizeMessagesToolRoleRequiresToolCallId(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenRouterChatModelAgentResource($resolver, 'r7');

		$method = (new \ReflectionClass(OpenRouterChatModelAgentResource::class))->getMethod('normalizeMessages');
		$method->setAccessible(true);

		$normalized = $method->invoke($r, [
			['role' => 'tool', 'content' => 'result without id'],
			['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => ['ok' => true]],
		]);

		$this->assertCount(1, $normalized);
		$this->assertSame('tool', $normalized[0]['role']);
		$this->assertSame('call_1', $normalized[0]['tool_call_id']);
		$this->assertSame('{"ok":true}', $normalized[0]['content']);
	}

	public function testNormalizeMessagesAssistantToolCallsAreMapped(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenRouterChatModelAgentResource($resolver, 'r8');

		$method = (new \ReflectionClass(OpenRouterChatModelAgentResource::class))->getMethod('normalizeMessages');
		$method->setAccessible(true);

		$normalized = $method->invoke($r, [[
			'role' => 'assistant',
			'content' => 'I will call tools',
			'tool_calls' => [
				[
					'id' => 'tc1',
					'function' => [
						'name' => 'sum',
						'arguments' => ['a' => 1, 'b' => 2],
					]
				],
				[
					// invalid: missing id -> should be skipped
					'function' => ['name' => 'noop', 'arguments' => '{}']
				],
				[
					'id' => 'tc2',
					'function' => [
						'name' => 'echo',
						'arguments' => '{"x":"y"}',
					]
				],
			],
		]]);

		$this->assertCount(1, $normalized);
		$this->assertSame('assistant', $normalized[0]['role']);
		$this->assertSame('I will call tools', $normalized[0]['content']);
		$this->assertArrayHasKey('tool_calls', $normalized[0]);

		$tc = $normalized[0]['tool_calls'];
		$this->assertCount(2, $tc);

		$this->assertSame('tc1', $tc[0]['id']);
		$this->assertSame('function', $tc[0]['type']);
		$this->assertSame('sum', $tc[0]['function']['name']);
		$this->assertSame('{"a":1,"b":2}', $tc[0]['function']['arguments']); // array -> json string

		$this->assertSame('tc2', $tc[1]['id']);
		$this->assertSame('echo', $tc[1]['function']['name']);
		$this->assertSame('{"x":"y"}', $tc[1]['function']['arguments']); // already string
	}

	public function testNormalizeMessagesFeedbackInjectionTrimsAndSkipsEmpty(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenRouterChatModelAgentResource($resolver, 'r9');

		$method = (new \ReflectionClass(OpenRouterChatModelAgentResource::class))->getMethod('normalizeMessages');
		$method->setAccessible(true);

		$normalized = $method->invoke($r, [
			['role' => 'user', 'content' => 'Hi', 'feedback' => "  please be brief  "],
			['role' => 'assistant', 'content' => 'Ok', 'feedback' => "   "], // should not inject
		]);

		$this->assertCount(3, $normalized);

		$this->assertSame('user', $normalized[0]['role']);
		$this->assertSame('Hi', $normalized[0]['content']);

		$this->assertSame('user', $normalized[1]['role']);
		$this->assertSame('please be brief', $normalized[1]['content']);

		$this->assertSame('assistant', $normalized[2]['role']);
		$this->assertSame('Ok', $normalized[2]['content']);
	}

	public function testNormalizeMessagesSkipsInvalidEntriesAndJsonEncodesNonStringContent(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenRouterChatModelAgentResource($resolver, 'r10');

		$method = (new \ReflectionClass(OpenRouterChatModelAgentResource::class))->getMethod('normalizeMessages');
		$method->setAccessible(true);

		$normalized = $method->invoke($r, [
			'not-an-array',
			['content' => 'missing role'],
			['role' => 'user', 'content' => ['a' => 1]],
		]);

		$this->assertSame([['role' => 'user', 'content' => '{"a":1}']], $normalized);
	}
}
