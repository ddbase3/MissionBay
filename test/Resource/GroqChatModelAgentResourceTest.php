<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\GroqChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * @covers \MissionBay\Resource\GroqChatModelAgentResource
 */
class GroqChatModelAgentResourceTest extends TestCase {

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
		$this->assertSame('groqchatmodelagentresource', GroqChatModelAgentResource::getName());
	}

	public function testSetConfigResolvesDefaultsWhenMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new GroqChatModelAgentResource($resolver, 'gr1');

		$r->setConfig([]);

		$opts = $r->getOptions();
		$this->assertSame('llama3-8b-8192', $opts['model']);
		$this->assertNull($opts['apikey']);
		$this->assertSame('https://api.groq.com/openai/v1/chat/completions', $opts['endpoint']);
		$this->assertSame(0.3, $opts['temperature']);
		$this->assertSame(512, $opts['maxtokens']);
	}

	public function testSetConfigResolvesAndCastsValues(): void {
		$map = [
			'model_key' => 'mixtral-8x7b-32768',
			'apikey_key' => 'groq-sk-test',
			'endpoint_key' => 'https://example.com/openai/v1/chat/completions',
			'temp_key' => '0.9',
			'max_key' => '1234',
		];

		$resolver = $this->makeResolver($map);
		$r = new GroqChatModelAgentResource($resolver, 'gr2');

		$r->setConfig([
			'model' => 'model_key',
			'apikey' => 'apikey_key',
			'endpoint' => 'endpoint_key',
			'temperature' => 'temp_key',
			'maxtokens' => 'max_key',
		]);

		$opts = $r->getOptions();
		$this->assertSame('mixtral-8x7b-32768', $opts['model']);
		$this->assertSame('groq-sk-test', $opts['apikey']);
		$this->assertSame('https://example.com/openai/v1/chat/completions', $opts['endpoint']);
		$this->assertSame(0.9, $opts['temperature']);
		$this->assertSame(1234, $opts['maxtokens']);
	}

	public function testSetOptionsMergesIntoResolvedOptions(): void {
		$resolver = $this->makeResolver([]);
		$r = new GroqChatModelAgentResource($resolver, 'gr3');
		$r->setConfig([]);

		$r->setOptions([
			'temperature' => 0.11,
			'maxtokens' => 42,
			'model' => 'llama3-8b-8192',
		]);

		$opts = $r->getOptions();
		$this->assertSame(0.11, $opts['temperature']);
		$this->assertSame(42, $opts['maxtokens']);
		$this->assertSame('llama3-8b-8192', $opts['model']);
	}

	public function testRawThrowsIfApiKeyMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new GroqChatModelAgentResource($resolver, 'gr4');
		$r->setConfig([]); // apikey resolves to null

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing API key for Groq model.');

		$r->raw([
			['role' => 'user', 'content' => 'Hi']
		]);
	}

	public function testChatReturnsAssistantContentFromRaw(): void {
		$resolver = $this->makeResolver([]);

		$r = new class($resolver, 'gr5') extends GroqChatModelAgentResource {
			public function raw(array $messages, array $tools = []): mixed {
				return [
					'choices' => [
						['message' => ['content' => 'Hello from Groq']]
					]
				];
			}
		};

		$r->setConfig(['apikey' => 'dummy']);

		$out = $r->chat([
			['role' => 'user', 'content' => 'Hi']
		]);

		$this->assertSame('Hello from Groq', $out);
	}

	public function testNormalizeMessagesConvertsNonStringContentAndInjectsFeedback(): void {
		$resolver = $this->makeResolver([]);
		$r = new GroqChatModelAgentResource($resolver, 'gr6');

		$ref = new \ReflectionClass(GroqChatModelAgentResource::class);
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
		$r = new GroqChatModelAgentResource($resolver, 'gr7');

		$ref = new \ReflectionClass(GroqChatModelAgentResource::class);
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
