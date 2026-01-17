<?php declare(strict_types=1);

namespace MissionBay\Resource\Test;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\CrmProductXrmExtractorAgentResource;
use MissionBay\Api\IAgentContentExtractor;
use MissionBay\Api\IAgentContext;
use MissionBay\Dto\AgentContentItem;
use ResourceFoundation\Api\IEntityDataService;

class CrmProductXrmExtractorAgentResourceTest extends TestCase {

	private function makeEntityDataServiceStub(array $entries, array &$lastOptionsRef): IEntityDataService {
		return new class($entries, $lastOptionsRef) implements IEntityDataService {

			private array $entries;
			private array $lastOptionsRef;

			public function __construct(array $entries, array &$lastOptionsRef) {
				$this->entries = $entries;
				$this->lastOptionsRef = &$lastOptionsRef;
			}

			public function getEntries(array $options = []): array {
				$this->lastOptionsRef = $options;
				return $this->entries;
			}

			public function getEntry(int|string $id, array $options = []): ?array {
				return null;
			}

			public function saveEntry(array $data): int|string {
				return 0;
			}

			public function deleteEntry(int|string $id): bool {
				return false;
			}
		};
	}

	private function newAgentContentItem(array $props): AgentContentItem {
		$ref = new \ReflectionClass(AgentContentItem::class);
		$ctor = $ref->getConstructor();
		if ($ctor === null) {
			/** @phpstan-ignore-next-line */
			return new AgentContentItem();
		}

		$args = [];
		foreach ($ctor->getParameters() as $p) {
			$name = $p->getName();

			if (array_key_exists($name, $props)) {
				$args[] = $props[$name];
				continue;
			}

			if ($p->isDefaultValueAvailable()) {
				$args[] = $p->getDefaultValue();
				continue;
			}

			$type = $p->getType();
			$typeName = $type instanceof \ReflectionNamedType ? $type->getName() : null;

			if ($typeName === 'string') { $args[] = ''; continue; }
			if ($typeName === 'int') { $args[] = 0; continue; }
			if ($typeName === 'bool') { $args[] = false; continue; }
			if ($typeName === 'array') { $args[] = []; continue; }

			$args[] = null;
		}

		/** @phpstan-ignore-next-line */
		return $ref->newInstanceArgs($args);
	}

	private function readProp(object $obj, string $prop): mixed {
		if (property_exists($obj, $prop)) {
			return $obj->$prop;
		}

		$r = new \ReflectionObject($obj);

		if ($r->hasProperty($prop)) {
			$p = $r->getProperty($prop);
			$p->setAccessible(true);
			return $p->getValue($obj);
		}

		$getter = 'get' . ucfirst($prop);
		if ($r->hasMethod($getter)) {
			$m = $r->getMethod($getter);
			$m->setAccessible(true);
			return $m->invoke($obj);
		}

		$this->fail("Cannot read property '$prop' from " . $obj::class);
	}

	public function testImplementsContentExtractorInterface(): void {
		$lastOptions = [];
		$eds = $this->makeEntityDataServiceStub([], $lastOptions);
		$res = new CrmProductXrmExtractorAgentResource($eds, 'id1');

		$this->assertInstanceOf(IAgentContentExtractor::class, $res);
	}

	public function testGetNameAndDescription(): void {
		$lastOptions = [];
		$eds = $this->makeEntityDataServiceStub([], $lastOptions);
		$res = new CrmProductXrmExtractorAgentResource($eds, 'id1');

		$this->assertSame('crmproductxrmextractoragentresource', CrmProductXrmExtractorAgentResource::getName());
		$this->assertSame(
			'Extracts CRM product entries from XRM and converts them into normalized content items.',
			$res->getDescription()
		);
	}

	public function testExtractLoadsEntriesWithExpectedOptionsAndMapsToItems(): void {
		$entries = [
			[
				'id' => 123,
				'uuid' => 'uuid-123',
				'name' => 'Product A',
				'data' => [
					'price' => 999,
					'weight' => 10,
					'keep' => 'yes',
				],
			],
		];

		$lastOptions = [];
		$eds = $this->makeEntityDataServiceStub($entries, $lastOptions);
		$res = new CrmProductXrmExtractorAgentResource($eds, 'id1');
		$context = $this->createStub(IAgentContext::class);

		$items = $res->extract($context);

		$this->assertIsArray($lastOptions);
		$this->assertSame('product', $lastOptions['type'] ?? null);
		$this->assertSame(['crm'], $lastOptions['tag'] ?? null);
		$this->assertTrue($lastOptions['loadname'] ?? false);
		$this->assertTrue($lastOptions['loaddata'] ?? false);
		$this->assertFalse($lastOptions['loadaccess'] ?? true);
		$this->assertSame('all', $lastOptions['archive'] ?? null);

		$this->assertIsArray($items);
		$this->assertCount(1, $items);
		$this->assertInstanceOf(AgentContentItem::class, $items[0]);

		$content0 = $this->readProp($items[0], 'content');
		$this->assertIsArray($content0);

		$this->assertSame(123, $content0['id']);
		$this->assertSame('Product A', $content0['name']);

		$this->assertIsArray($content0['data']);
		$this->assertArrayNotHasKey('price', $content0['data']);
		$this->assertArrayNotHasKey('weight', $content0['data']);
		$this->assertSame(['keep' => 'yes'], $content0['data']);
	}

	public function testMapEntriesToItemsReturnsEmptyArrayForNoEntries(): void {
		$lastOptions = [];
		$eds = $this->makeEntityDataServiceStub([], $lastOptions);
		$res = new CrmProductXrmExtractorAgentResource($eds, 'id1');

		$ref = new \ReflectionClass($res);
		$m = $ref->getMethod('mapEntriesToItems');
		$m->setAccessible(true);

		$out = $m->invoke($res, []);
		$this->assertSame([], $out);
	}

	/**
	 * Optional smoke test: proves the suite can still construct AgentContentItem even if ctor changed.
	 * Not required for extractor logic, but helpful given recent ctor changes.
	 */
	public function testAgentContentItemConstructorIsSatisfiedInThisTestSuite(): void {
		$item = $this->newAgentContentItem([
			'action' => 'extract',
			'id' => 'x',
			'hash' => 'x',
			'contentType' => 'text/plain',
			'content' => 'hello',
			'isBinary' => false,
			'size' => 5,
			'metadata' => [],
		]);

		$this->assertInstanceOf(AgentContentItem::class, $item);
	}
}
