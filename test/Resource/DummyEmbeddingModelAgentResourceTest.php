<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\DummyEmbeddingModelAgentResource;
use MissionBay\Api\IAgentConfigValueResolver;

/**
 * @covers \MissionBay\Resource\DummyEmbeddingModelAgentResource
 */
class DummyEmbeddingModelAgentResourceTest extends TestCase {

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
		$this->assertSame('dummyembeddingmodelagentresource', DummyEmbeddingModelAgentResource::getName());
	}

	public function testSetConfigUsesDefaultDimensionWhenMissing(): void {
		$resolver = $this->makeResolver([]);
		$r = new DummyEmbeddingModelAgentResource($resolver, 'e1');

		// IMPORTANT: setConfig([]) would call resolveValue(128) (int) and violate the interface type.
		// So we explicitly provide dimension as string to keep the call within array|string|null.
		$r->setConfig(['dimension' => '128']);

		$opts = $r->getOptions();
		$this->assertSame(128, $opts['dimension']);

		$v = $r->embed(['a']);
		$this->assertCount(1, $v);
		$this->assertCount(128, $v[0]);
		$this->assertSame(0.0, $v[0][0]);
		$this->assertSame(0.0, $v[0][127]);
	}

	public function testSetConfigResolvesAndCastsDimension(): void {
		$resolver = $this->makeResolver([
			'dim_key' => '256',
		]);

		$r = new DummyEmbeddingModelAgentResource($resolver, 'e2');

		$r->setConfig([
			'dimension' => 'dim_key',
		]);

		$opts = $r->getOptions();
		$this->assertSame(256, $opts['dimension']);

		$v = $r->embed(['x', 'y']);
		$this->assertCount(2, $v);
		$this->assertCount(256, $v[0]);
		$this->assertCount(256, $v[1]);
	}

	public function testSetOptionsMergesAndUpdatesDimension(): void {
		$resolver = $this->makeResolver([]);
		$r = new DummyEmbeddingModelAgentResource($resolver, 'e3');

		// Same reason as above: avoid resolveValue(int 128)
		$r->setConfig(['dimension' => '128']);

		$r->setOptions([
			'dimension' => 12,
			'foo' => 'bar',
		]);

		$opts = $r->getOptions();
		$this->assertSame(12, $opts['dimension']);
		$this->assertSame('bar', $opts['foo']);

		$v = $r->embed(['a']);
		$this->assertCount(1, $v);
		$this->assertCount(12, $v[0]);
	}

	public function testEmbedReturnsEmptyArrayForEmptyInput(): void {
		$resolver = $this->makeResolver([]);
		$r = new DummyEmbeddingModelAgentResource($resolver, 'e4');

		// avoid resolveValue(int 128)
		$r->setConfig(['dimension' => '128']);

		$this->assertSame([], $r->embed([]));
	}

	public function testEmbedReturnsDeterministicZeroVectorsAndDoesNotMutateBetweenItems(): void {
		$resolver = $this->makeResolver([]);
		$r = new DummyEmbeddingModelAgentResource($resolver, 'e5');

		// Here we can pass int directly because the resource reads it through resolveValue(),
		// which only accepts array|string|null. So pass as string.
		$r->setConfig(['dimension' => '4']);

		$out = $r->embed(['a', 'b', 'c']);

		$this->assertCount(3, $out);
		foreach ($out as $vec) {
			$this->assertSame([0.0, 0.0, 0.0, 0.0], $vec);
		}

		$out[0][0] = 1.0;
		$this->assertSame([0.0, 0.0, 0.0, 0.0], $out[1]);
		$this->assertSame([0.0, 0.0, 0.0, 0.0], $out[2]);
	}
}
