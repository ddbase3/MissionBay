<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\MistralChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * @covers \MissionBay\Resource\MistralChatModelAgentResource
 */
class MistralChatModelAgentResourceTest extends TestCase {

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

	public function testGetName(): void {
		$this->assertSame('mistralchatmodelagentresource', MistralChatModelAgentResource::getName());
	}

	public function testSetConfigResolvesDefaultsWhenMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new MistralChatModelAgentResource($resolver, 'm1');

		$r->setConfig([]);

		$opts = $r->getOptions();
		$this->assertSame('mistral-small-latest', $opts['model']);
		$this->assertNull($opts['apikey']);
		$this->assertSame('https://api.mistral.ai/v1/chat/completions', $opts['endpoint']);
		$this->assertSame(0.3, $opts['temperature']);
		$this->assertSame(512, $opts['maxtokens']);
	}

	public function testSetConfigResolvesAndCastsValues(): void {
		$resolver = $this->makeResolver([
			'model_key' => 'mistral-large-latest',
			'apikey_key' => 'sk-mistral-test',
			'endpoint_key' => 'https://example.com/v1/chat/completions',
			'temp_key' => '0.75',
			'max_key' => '999',
		]);

		$r = new MistralChatModelAgentResource($resolver, 'm2');

		$r->setConfig([
			'model' => 'model_key',
			'apikey' => 'apikey_key',
			'endpoint' => 'endpoint_key',
			'temperature' => 'temp_key',
			'maxtokens' => 'max_key',
		]);

		$opts = $r->getOptions();
		$this->assertSame('mistral-large-latest', $opts['model']);
		$this->assertSame('sk-mistral-test', $opts['apikey']);
		$this->assertSame('https://example.com/v1/chat/completions', $opts['endpoint']);
		$this->assertSame(0.75, $opts['temperature']);
		$this->assertSame(999, $opts['maxtokens']);
	}

	public function testSetConfigUsesDefaultEndpointIfResolvedEmpty(): void {
		$resolver = $this->makeResolver([
			'apikey_key' => 'sk-mistral-test',
			'endpoint_key' => '', // should trigger default
		]);

		$r = new MistralChatModelAgentResource($resolver, 'm3');

		$r->setConfig([
			'apikey' => 'apikey_key',
			'endpoint' => 'endpoint_key',
		]);

		$opts = $r->getOptions();
		$this->assertSame('https://api.mistral.ai/v1/chat/completions', $opts['endpoint']);
	}

	public function testSetOptionsMergesIntoResolvedOptions(): void {
		$resolver = $this->makeResolver([]);
		$r = new MistralChatModelAgentResource($resolver, 'm4');
		$r->setConfig([]);

		$r->setOptions([
			'temperature' => 0.12,
			'maxtokens' => 77,
			'extra' => 'x',
		]);

		$opts = $r->getOptions();
		$this->assertSame(0.12, $opts['temperature']);
		$this->assertSame(77, $opts['maxtokens']);
		$this->assertSame('x', $opts['extra']);
	}

	public function testRawThrowsIfApiKeyMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new MistralChatModelAgentResource($resolver, 'm5');
		$r->setConfig([]); // apikey null

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing API key for Mistral model.');

		$r->raw([
			['role' => 'user', 'content' => 'Hi']
		]);
	}

	public function testChatReturnsAssistantContentFromRaw(): void {
		$resolver = $this->makeResolver([]);

		$r = new class($resolver, 'm6') extends MistralChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				return [
					'choices' => [
						['message' => ['content' => 'Hallo!']]
					]
				];
			}
		};

		$r->setConfig(['apikey' => 'dummy']); // not used by overridden raw()

		$out = $r->chat([
			['role' => 'user', 'content' => 'Hi']
		]);

		$this->assertSame('Hallo!', $out);
	}

	public function testNormalizeMessagesPassesRoleAndContentThrough(): void {
		$resolver = $this->makeResolver([]);
		$r = new MistralChatModelAgentResource($resolver, 'm7');

		$ref = new \ReflectionClass(MistralChatModelAgentResource::class);
		$method = $ref->getMethod('normalizeMessages');
		$method->setAccessible(true);

		$normalized = $method->invoke($r, [
			['role' => 'system', 'content' => 'You are helpful'],
			['role' => 'user', 'content' => 'Ping'],
		]);

		$this->assertSame([
			['role' => 'system', 'content' => 'You are helpful'],
			['role' => 'user', 'content' => 'Ping'],
		], $normalized);
	}
}
