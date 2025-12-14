<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\FireworksChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * @covers \MissionBay\Resource\FireworksChatModelAgentResource
 */
class FireworksChatModelAgentResourceTest extends TestCase {

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
		$this->assertSame('fireworkschatmodelagentresource', FireworksChatModelAgentResource::getName());
	}

	public function testSetConfigResolvesDefaultsWhenMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new FireworksChatModelAgentResource($resolver, 'f1');

		$r->setConfig([]);

		$opts = $r->getOptions();
		$this->assertSame('accounts/fireworks/models/firefunction-v1', $opts['model']);
		$this->assertNull($opts['apikey']);
		$this->assertSame('https://api.fireworks.ai/inference/v1/chat/completions', $opts['endpoint']);
		$this->assertSame(0.3, $opts['temperature']);
		$this->assertSame(512, $opts['maxtokens']);
	}

	public function testSetConfigResolvesAndCastsValues(): void {
		$resolver = $this->makeResolver([
			'model_key' => 'accounts/fireworks/models/mixtral-8x7b-instruct',
			'apikey_key' => 'fwk-test',
			'endpoint_key' => 'https://example.test/chat',
			'temp_key' => '0.75',
			'max_key' => '999',
		]);

		$r = new FireworksChatModelAgentResource($resolver, 'f2');

		$r->setConfig([
			'model' => 'model_key',
			'apikey' => 'apikey_key',
			'endpoint' => 'endpoint_key',
			'temperature' => 'temp_key',
			'maxtokens' => 'max_key',
		]);

		$opts = $r->getOptions();
		$this->assertSame('accounts/fireworks/models/mixtral-8x7b-instruct', $opts['model']);
		$this->assertSame('fwk-test', $opts['apikey']);
		$this->assertSame('https://example.test/chat', $opts['endpoint']);
		$this->assertSame(0.75, $opts['temperature']);
		$this->assertSame(999, $opts['maxtokens']);
	}

	public function testSetOptionsMergesIntoResolvedOptions(): void {
		$resolver = $this->makeResolver([]);
		$r = new FireworksChatModelAgentResource($resolver, 'f3');
		$r->setConfig([]);

		$r->setOptions([
			'temperature' => 0.11,
			'maxtokens' => 42,
			'model' => 'accounts/fireworks/models/firefunction-v1',
			'foo' => 'bar',
		]);

		$opts = $r->getOptions();
		$this->assertSame(0.11, $opts['temperature']);
		$this->assertSame(42, $opts['maxtokens']);
		$this->assertSame('accounts/fireworks/models/firefunction-v1', $opts['model']);
		$this->assertSame('bar', $opts['foo']);
	}

	public function testRawThrowsIfApiKeyMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new FireworksChatModelAgentResource($resolver, 'f4');
		$r->setConfig([]); // apikey = null

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing API key for Fireworks model.');

		$r->raw([
			['role' => 'user', 'content' => 'Hi']
		]);
	}

	public function testChatReturnsAssistantContentFromRaw(): void {
		$resolver = $this->makeResolver([]);

		$r = new class($resolver, 'f5') extends FireworksChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				return [
					'choices' => [
						['message' => ['content' => 'Hello from Fireworks']]
					]
				];
			}
		};

		$r->setConfig(['apikey' => 'dummy']);

		$out = $r->chat([
			['role' => 'user', 'content' => 'Hi']
		]);

		$this->assertSame('Hello from Fireworks', $out);
	}

	public function testNormalizeMessagesConvertsNonStringContentAndInjectsFeedback(): void {
		$resolver = $this->makeResolver([]);
		$r = new FireworksChatModelAgentResource($resolver, 'f6');

		$ref = new \ReflectionClass(FireworksChatModelAgentResource::class);
		$method = $ref->getMethod('normalizeMessages');
		$method->setAccessible(true);

		$normalized = $method->invoke($r, [[
			'role' => 'user',
			'content' => ['a' => 1, 'b' => true],
			'feedback' => "  Please be concise. \n"
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
		$r = new FireworksChatModelAgentResource($resolver, 'f7');

		$ref = new \ReflectionClass(FireworksChatModelAgentResource::class);
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
