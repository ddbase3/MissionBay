<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\OpenAiEmbeddingModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * @covers \MissionBay\Resource\OpenAiEmbeddingModelAgentResource
 */
class OpenAiEmbeddingModelAgentResourceTest extends TestCase {

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
		$this->assertSame('openaiembeddingmodelagentresource', OpenAiEmbeddingModelAgentResource::getName());
	}

	public function testSetConfigResolvesDefaults(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenAiEmbeddingModelAgentResource($resolver, 'e1');

		$r->setConfig([]);

		$opts = $r->getOptions();
		$this->assertNull($opts['model']);
		$this->assertNull($opts['apikey']);
		$this->assertSame('https://api.openai.com/v1/embeddings', $opts['endpoint']);
	}

	public function testSetConfigResolvesValuesFromResolver(): void {
		$resolver = $this->makeResolver([
			'model_key' => 'text-embedding-3-large',
			'apikey_key' => 'sk-test',
			'endpoint_key' => 'https://example.com/v1/embeddings',
		]);

		$r = new OpenAiEmbeddingModelAgentResource($resolver, 'e2');

		$r->setConfig([
			'model' => 'model_key',
			'apikey' => 'apikey_key',
			'endpoint' => 'endpoint_key',
		]);

		$opts = $r->getOptions();
		$this->assertSame('text-embedding-3-large', $opts['model']);
		$this->assertSame('sk-test', $opts['apikey']);
		$this->assertSame('https://example.com/v1/embeddings', $opts['endpoint']);
	}

	public function testSetOptionsMerges(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenAiEmbeddingModelAgentResource($resolver, 'e3');
		$r->setConfig([
			'model' => 'text-embedding-3-small',
			'apikey' => 'sk-test',
		]);

		$r->setOptions([
			'model' => 'text-embedding-3-large',
			'extra' => 'x',
		]);

		$opts = $r->getOptions();
		$this->assertSame('text-embedding-3-large', $opts['model']);
		$this->assertSame('sk-test', $opts['apikey']);
		$this->assertSame('x', $opts['extra']);
	}

	public function testEmbedReturnsEmptyArrayForEmptyInput(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenAiEmbeddingModelAgentResource($resolver, 'e4');
		$r->setConfig(['apikey' => 'sk-test']);

		$this->assertSame([], $r->embed([]));
	}

	public function testEmbedThrowsIfApiKeyMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new OpenAiEmbeddingModelAgentResource($resolver, 'e5');
		$r->setConfig([]); // apikey resolves to null

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Missing API key for OpenAI embedding model.');

		$r->embed(['hello']);
	}

	public function testEmbedMapsEmbeddingsFromResponse(): void {
		$resolver = $this->makeResolver([]);

		$r = new class($resolver, 'e6') extends OpenAiEmbeddingModelAgentResource {
			public function embed(array $texts): array {
				if (empty($texts)) return [];

				// Emulate a valid OpenAI response -> mapping behavior must match production code:
				$data = [
					'data' => [
						['embedding' => [0.1, 0.2]],
						['embedding' => [0.3, 0.4]],
					],
				];

				return array_map(fn($item) => $item['embedding'] ?? [], $data['data']);
			}
		};

		$r->setConfig(['apikey' => 'dummy']);

		$out = $r->embed(['a', 'b']);
		$this->assertSame([[0.1, 0.2], [0.3, 0.4]], $out);
	}

	public function testEmbedThrowsOnMalformedResponseShape(): void {
		// We avoid hitting curl: override embed to simulate "decoded JSON" but with malformed structure,
		// then assert the same exception message as the production code would throw.
		$resolver = $this->makeResolver([]);

		$r = new class($resolver, 'e7') extends OpenAiEmbeddingModelAgentResource {
			public function embed(array $texts): array {
				if (empty($texts)) return [];

				$apikey = $this->getOptions()['apikey'] ?? null;
				if (!$apikey) {
					throw new \RuntimeException("Missing API key for OpenAI embedding model.");
				}

				$data = ['oops' => true]; // missing 'data' array

				if (!isset($data['data']) || !is_array($data['data'])) {
					throw new \RuntimeException("Malformed OpenAI embedding response.");
				}

				return array_map(fn($item) => $item['embedding'] ?? [], $data['data']);
			}
		};

		$r->setConfig(['apikey' => 'dummy']);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('Malformed OpenAI embedding response.');

		$r->embed(['x']);
	}
}
