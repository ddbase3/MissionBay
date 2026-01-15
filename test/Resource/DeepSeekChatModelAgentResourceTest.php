<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\DeepSeekChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * @covers \MissionBay\Resource\DeepSeekChatModelAgentResource
 */
class DeepSeekChatModelAgentResourceTest extends TestCase {

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

				// Default behavior for tests: return raw string config (or null) unchanged.
				return $config;
			}
		};
	}

	public function testGetName(): void {
		$this->assertSame('deepseekchatmodelagentresource', DeepSeekChatModelAgentResource::getName());
	}

	public function testSetConfigResolvesDefaultsWhenMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new DeepSeekChatModelAgentResource($resolver, 'r1');

		$r->setConfig([]);

		$opts = $r->getOptions();
		$this->assertSame('deepseek-chat', $opts['model']);
		$this->assertNull($opts['apikey']);
		$this->assertSame('https://api.deepseek.com/beta/chat/completions', $opts['endpoint']);
		$this->assertSame(0.3, $opts['temperature']);
		$this->assertSame(512, $opts['maxtokens']);
	}

	public function testSetConfigResolvesAndCastsValues(): void {
		$map = [
			'model_key' => 'deepseek-reasoner',
			'apikey_key' => 'sk-test',
			'endpoint_key' => 'https://example.com/v1/chat/completions',
			'temp_key' => '0.9',
			'max_key' => '1234',
		];

		$resolver = $this->makeResolver($map);
		$r = new DeepSeekChatModelAgentResource($resolver, 'r2');

		$r->setConfig([
			'model' => 'model_key',
			'apikey' => 'apikey_key',
			'endpoint' => 'endpoint_key',
			'temperature' => 'temp_key',
			'maxtokens' => 'max_key',
		]);

		$opts = $r->getOptions();
		$this->assertSame('deepseek-reasoner', $opts['model']);
		$this->assertSame('sk-test', $opts['apikey']);
		$this->assertSame('https://example.com/v1/chat/completions', $opts['endpoint']);
		$this->assertSame(0.9, $opts['temperature']);      // cast to float
		$this->assertSame(1234, $opts['maxtokens']);        // cast to int
	}

	public function testSetOptionsMergesIntoResolvedOptions(): void {
		$resolver = $this->makeResolver([]);
		$r = new DeepSeekChatModelAgentResource($resolver, 'r3');
		$r->setConfig([]);

		$r->setOptions([
			'temperature' => 0.11,
			'maxtokens' => 42,
			'model' => 'deepseek-chat',
		]);

		$opts = $r->getOptions();
		$this->assertSame(0.11, $opts['temperature']);
		$this->assertSame(42, $opts['maxtokens']);
		$this->assertSame('deepseek-chat', $opts['model']);
	}

	public function testRawThrowsIfApiKeyMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new DeepSeekChatModelAgentResource($resolver, 'r4');
		$r->setConfig([]); // apikey resolves to null

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing API key for DeepSeek model.');

		$r->raw([
			['role' => 'user', 'content' => 'Hi']
		]);
	}

	public function testChatReturnsAssistantContentFromRaw(): void {
		$resolver = $this->makeResolver([]);

		$r = new class($resolver, 'r5') extends DeepSeekChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				return [
					'choices' => [
						['message' => ['content' => 'Hello Daniel']]
					]
				];
			}
		};

		$r->setConfig(['apikey' => 'dummy']); // not used, but mirrors real usage

		$out = $r->chat([
			['role' => 'user', 'content' => 'Hi']
		]);

		$this->assertSame('Hello Daniel', $out);
	}

	public function testNormalizeMessagesConvertsNonStringContentAndInjectsFeedback(): void {
		$resolver = $this->makeResolver([]);
		$r = new DeepSeekChatModelAgentResource($resolver, 'r6');

		$ref = new \ReflectionClass(DeepSeekChatModelAgentResource::class);
		$method = $ref->getMethod('normalizeMessages');
		$method->setAccessible(true);

		$normalized = $method->invoke($r, [[
			'role' => 'user',
			'content' => ['a' => 1, 'b' => true],
			'feedback' => 'Please be concise.'
		], [
			'role' => 'assistant',
			'content' => 'OK'
		]]);

		$this->assertIsArray($normalized);
		$this->assertCount(3, $normalized);

		$this->assertSame('user', $normalized[0]['role']);
		$this->assertSame('{"a":1,"b":true}', $normalized[0]['content']);

		$this->assertSame('user', $normalized[1]['role']);
		$this->assertSame('Please be concise.', $normalized[1]['content']);

		$this->assertSame('assistant', $normalized[2]['role']);
		$this->assertSame('OK', $normalized[2]['content']);
	}

	public function testNormalizeMessagesSkipsInvalidEntries(): void {
		$resolver = $this->makeResolver([]);
		$r = new DeepSeekChatModelAgentResource($resolver, 'r7');

		$ref = new \ReflectionClass(DeepSeekChatModelAgentResource::class);
		$method = $ref->getMethod('normalizeMessages');
		$method->setAccessible(true);

		$normalized = $method->invoke($r, [
			'not-an-array',
			['content' => 'missing role'],
			['role' => 'user', 'content' => 'ok'],
		]);

		$this->assertSame([['role' => 'user', 'content' => 'ok']], $normalized);
	}
}
