<?php declare(strict_types=1);

namespace MissionBay\Resource\Test;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\CrmProductXrmExtractorAgentResource;
use MissionBay\Api\IAgentContentExtractor;
use MissionBay\Api\IAgentContext;
use MissionBay\Dto\AgentContentItem;
use ResourceFoundation\Api\IEntityDataService;

class CrmProductXrmExtractorAgentResourceTest extends TestCase {

	public function testImplementsContentExtractorInterface(): void {
		$eds = new CrmEntityDataServiceStub([]);
		$res = new CrmProductXrmExtractorAgentResource($eds, 'id1');

		$this->assertInstanceOf(IAgentContentExtractor::class, $res);
	}

	public function testGetNameAndDescription(): void {
		$eds = new CrmEntityDataServiceStub([]);
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

		$eds = new CrmEntityDataServiceStub($entries);
		$res = new CrmProductXrmExtractorAgentResource($eds, 'id1');
		$context = $this->createStub(IAgentContext::class);

		$items = $res->extract($context);

		$this->assertIsArray($eds->lastOptions);
		$this->assertSame('product', $eds->lastOptions['type'] ?? null);
		$this->assertSame(['crm'], $eds->lastOptions['tag'] ?? null);
		$this->assertTrue($eds->lastOptions['loadname'] ?? false);
		$this->assertTrue($eds->lastOptions['loaddata'] ?? false);
		$this->assertFalse($eds->lastOptions['loadaccess'] ?? true);
		$this->assertSame('all', $eds->lastOptions['archive'] ?? null);

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
		$eds = new CrmEntityDataServiceStub([]);
		$res = new CrmProductXrmExtractorAgentResource($eds, 'id1');

		$ref = new \ReflectionClass($res);
		$m = $ref->getMethod('mapEntriesToItems');
		$m->setAccessible(true);

		$out = $m->invoke($res, []);
		$this->assertSame([], $out);
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

}

class CrmEntityDataServiceStub implements IEntityDataService {

	public array $lastOptions = [];

	public function __construct(private array $entries) {}

	public function getEntries(array $options = []): array {
		$this->lastOptions = $options;
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

}
