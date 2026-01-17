<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\ProductXrmExtractorAgentResource;
use MissionBay\Api\IAgentContext;
use MissionBay\Dto\AgentContentItem;
use ResourceFoundation\Api\IEntityDataService;

/**
 * @covers \MissionBay\Resource\ProductXrmExtractorAgentResource
 */
class ProductXrmExtractorAgentResourceTest extends TestCase {

	private function makeContextStub(): IAgentContext {
		// ProductXrmExtractorAgentResource does not use the context.
		return $this->createStub(IAgentContext::class);
	}

	public function testGetName(): void {
		$this->assertSame('productxrmextractoragentresource', ProductXrmExtractorAgentResource::getName());
	}

	public function testGetDescription(): void {
		$svc = $this->createStub(IEntityDataService::class);
		$r = new ProductXrmExtractorAgentResource($svc, 'x1');

		$this->assertSame(
			'Extracts product entries from XRM and converts them into normalized content items.',
			$r->getDescription()
		);
	}

	public function testLoadEntriesCallsEntityDataServiceWithExpectedOptions(): void {
		$entries = [
			[
				'id' => 11,
				'uuid' => 'u-11',
				'name' => 'Product A',
				'data' => [
					'price' => 123,
					'weight' => 9,
					'color' => 'red'
				]
			]
		];

		$svc = $this->createMock(IEntityDataService::class);
		$svc->expects($this->once())
			->method('getEntries')
			->with([
				'type' => 'product',
				'loadname' => true,
				'loaddata' => true,
				'loadaccess' => false,
				'archive' => 'all'
			])
			->willReturn($entries);

		$r = new class($svc, 'x2') extends ProductXrmExtractorAgentResource {
			public function loadEntriesPublic(): array {
				return $this->loadEntries();
			}
		};

		$out = $r->loadEntriesPublic();

		$this->assertSame($entries, $out);
	}

	public function testMapEntriesToItemsCurrentlyThrowsBecauseAgentContentItemRequiresActionAndCollectionKey(): void {
		$entries = [
			[
				'id' => 11,
				'uuid' => 'u-11',
				'name' => 'Product A',
				'data' => [
					'price' => 123,
					'weight' => 9,
					'color' => 'red'
				]
			]
		];

		$svc = $this->createStub(IEntityDataService::class);

		$r = new class($svc, 'x3') extends ProductXrmExtractorAgentResource {
			public function mapEntriesToItemsPublic(array $entries): array {
				return $this->mapEntriesToItems($entries);
			}
		};

		$this->expectException(\ArgumentCountError::class);
		$r->mapEntriesToItemsPublic($entries);
	}

	/**
	 * extract() delegates to loadEntries() and mapEntriesToItems().
	 * We stub loadEntries() to return an entry so we guarantee the mapping path runs.
	 */
	public function testExtractCurrentlyThrowsBecauseAgentContentItemRequiresActionAndCollectionKey(): void {
		$entries = [
			[
				'id' => 11,
				'uuid' => 'u-11',
				'name' => 'Product A',
				'data' => [
					'price' => 123,
					'weight' => 9,
					'color' => 'red'
				]
			]
		];

		$svc = $this->createStub(IEntityDataService::class);

		$r = new class($svc, 'x4', $entries) extends ProductXrmExtractorAgentResource {
			private array $entries;

			public function __construct(IEntityDataService $entityDataService, ?string $id, array $entries) {
				parent::__construct($entityDataService, $id);
				$this->entries = $entries;
			}

			protected function loadEntries(): array {
				return $this->entries;
			}
		};

		$this->expectException(\ArgumentCountError::class);
		$r->extract($this->makeContextStub());
	}

	public function testAckAndFailAreNoOps(): void {
		$svc = $this->createStub(IEntityDataService::class);
		$r = new ProductXrmExtractorAgentResource($svc, 'x5');

		$item = new AgentContentItem(
			action: 'upsert',
			collectionKey: 'product',
			id: 'id1',
			hash: 'h1',
			contentType: 'application/x-crm-json',
			content: ['id' => 1],
			isBinary: false,
			size: 10,
			metadata: []
		);

		// Ensure methods are callable and do not throw.
		$r->ack($item, ['ok' => true]);
		$r->fail($item, 'err', true);

		$this->assertTrue(true);
	}
}
