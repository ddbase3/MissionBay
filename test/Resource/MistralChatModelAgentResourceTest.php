<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Base3\Event\EventManager;
use MissionBay\Ai\AiProviderRequestEventDispatcher;
use MissionBay\Resource\MistralChatModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

#[CoversClass(\MissionBay\Resource\MistralChatModelAgentResource::class)]
class MistralChatModelAgentResourceTest extends TestCase {

	private function makeResolver(array $map = []): IAgentConfigValueResolver {
		return new MistralChatModelAgentResourceTestDouble1($map);
	}

	private function providerRequestEvents(): AiProviderRequestEventDispatcher {
		return new AiProviderRequestEventDispatcher(new EventManager());
	}

	public function testGetName(): void {
		$this->assertSame('mistralchatmodelagentresource', MistralChatModelAgentResource::getName());
	}

	public function testSetConfigResolvesDefaultsWhenMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new MistralChatModelAgentResource($resolver, $this->providerRequestEvents(), 'm1');

		$r->setConfig([]);

		$opts = $r->getOptions();
		$this->assertSame('mistral-medium-3-5', $opts['model']);
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

		$r = new MistralChatModelAgentResource($resolver, $this->providerRequestEvents(), 'm2');

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

		$r = new MistralChatModelAgentResource($resolver, $this->providerRequestEvents(), 'm3');

		$r->setConfig([
			'apikey' => 'apikey_key',
			'endpoint' => 'endpoint_key',
		]);

		$opts = $r->getOptions();
		$this->assertSame('https://api.mistral.ai/v1/chat/completions', $opts['endpoint']);
	}

	public function testSetOptionsMergesIntoResolvedOptions(): void {
		$resolver = $this->makeResolver([]);
		$r = new MistralChatModelAgentResource($resolver, $this->providerRequestEvents(), 'm4');
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
		$r = new MistralChatModelAgentResource($resolver, $this->providerRequestEvents(), 'm5');
		$r->setConfig([]); // apikey null

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing API key for Mistral model.');

		$r->raw([
			['role' => 'user', 'content' => 'Hi']
		]);
	}

	public function testChatReturnsAssistantContentFromRaw(): void {
		$resolver = $this->makeResolver([]);

		$r = new MistralChatModelAgentResourceTestDouble2($resolver, $this->providerRequestEvents(), 'm6');

		$r->setConfig(['apikey' => 'dummy']); // not used by overridden raw()

		$out = $r->chat([
			['role' => 'user', 'content' => 'Hi']
		]);

		$this->assertSame('Hallo!', $out);
	}

	public function testNormalizeMessagesPassesRoleAndContentThrough(): void {
		$resolver = $this->makeResolver([]);
		$r = new MistralChatModelAgentResource($resolver, $this->providerRequestEvents(), 'm7');

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

require_once __DIR__ . '/MistralChatModelAgentResourceTestDouble1.php';

require_once __DIR__ . '/MistralChatModelAgentResourceTestDouble2.php';
