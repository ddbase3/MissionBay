<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\NoEmbeddingModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * @covers \MissionBay\Resource\NoEmbeddingModelAgentResource
 */
class NoEmbeddingModelAgentResourceTest extends TestCase {

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
		$this->assertSame('noembeddingmodelagentresource', NoEmbeddingModelAgentResource::getName());
	}

	public function testSetConfigResolvesOptionsEvenIfUnused(): void {
		$resolver = $this->makeResolver([
			'model_key' => 'text-embedding-3-large',
			'apikey_key' => 'sk-test',
			'endpoint_key' => 'https://example.com/embed',
		]);

		$r = new NoEmbeddingModelAgentResource($resolver, 'n1');

		$r->setConfig([
			'model' => 'model_key',
			'apikey' => 'apikey_key',
			'endpoint' => 'endpoint_key',
		]);

		$opts = $r->getOptions();
		$this->assertSame('text-embedding-3-large', $opts['model']);
		$this->assertSame('sk-test', $opts['apikey']);
		$this->assertSame('https://example.com/embed', $opts['endpoint']);
	}

	public function testSetOptionsMergesIntoResolvedOptions(): void {
		$resolver = $this->makeResolver([]);
		$r = new NoEmbeddingModelAgentResource($resolver, 'n2');

		$r->setConfig([
			'model' => 'm',
			'apikey' => 'k',
			'endpoint' => 'e',
		]);

		$r->setOptions([
			'model' => 'override-model',
			'extra' => 'x',
		]);

		$opts = $r->getOptions();
		$this->assertSame('override-model', $opts['model']);
		$this->assertSame('k', $opts['apikey']);
		$this->assertSame('e', $opts['endpoint']);
		$this->assertSame('x', $opts['extra']);
	}

	public function testEmbedReturnsEmptyArrayForEmptyInput(): void {
		$resolver = $this->makeResolver([]);
		$r = new NoEmbeddingModelAgentResource($resolver, 'n3');
		$r->setConfig([]);

		$this->assertSame([], $r->embed([]));
	}

	public function testEmbedReturnsOneEmptyVectorPerText(): void {
		$resolver = $this->makeResolver([]);
		$r = new NoEmbeddingModelAgentResource($resolver, 'n4');
		$r->setConfig([]);

		$out = $r->embed(['a', 'b', 'c']);

		$this->assertIsArray($out);
		$this->assertCount(3, $out);

		$this->assertSame([], $out[0]);
		$this->assertSame([], $out[1]);
		$this->assertSame([], $out[2]);
	}

	public function testEmbedKeepsInputCountEvenWithEmptyStrings(): void {
		$resolver = $this->makeResolver([]);
		$r = new NoEmbeddingModelAgentResource($resolver, 'n5');
		$r->setConfig([]);

		$out = $r->embed(['', '  ', "\n"]);

		$this->assertCount(3, $out);
		foreach ($out as $vec) {
			$this->assertSame([], $vec);
		}
	}
}
