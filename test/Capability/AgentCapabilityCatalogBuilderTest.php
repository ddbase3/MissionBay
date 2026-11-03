<?php declare(strict_types=1);

namespace MissionBay\Test\Capability;

use MissionBay\Capability\AgentCapabilityCatalogBuilder;
use PHPUnit\Framework\TestCase;

final class AgentCapabilityCatalogBuilderTest extends TestCase {

	public function testEmptyDefinitionsProduceEmptyCatalog(): void {
		$catalog = (new AgentCapabilityCatalogBuilder())->build([], []);
		$this->assertCount(0, $catalog);
		$this->assertSame([], $catalog->names());
		$this->assertSame([], $catalog->getToolDefinitions());
	}

	public function testBuildNormalizesCapabilityMetadataAndTags(): void {
		$definition = [
			'label' => ' Search ',
			'category' => 'retrieval',
			'tags' => [' NEWS ', 'news', 'current', ''],
			'priority' => '12',
			'always_available' => 'yes',
			'readOnlyHint' => true,
			'function' => ['name' => 'search', 'description' => ' Look up information ']
		];
		$catalog = (new AgentCapabilityCatalogBuilder())->build([], [$definition]);
		$this->assertCount(1, $catalog);
		$this->assertTrue($catalog->has('search'));
		$capability = $catalog->get('search');
		$this->assertSame('Search', $capability->getTitle());
		$this->assertSame('retrieval', $capability->getCategory());
		$this->assertSame(['news', 'current'], $capability->getTags());
		$this->assertSame(12, $capability->getPriority());
		$this->assertTrue($capability->isAlwaysAvailable());
		$this->assertSame(['readOnlyHint' => true], $capability->getMetadata());
		$this->assertSame('', $capability->getSourceName());
	}

	public function testIndependentBatchToolsReceiveRuntimeDescriptionHint(): void {
		$catalog = (new AgentCapabilityCatalogBuilder())->build([], [[
			'function' => ['name' => 'add_member', 'description' => 'Adds one member'],
			'batchable' => true,
			'batchIndependent' => true,
			'maxBatchSize' => 8
		]]);
		$this->assertStringContainsString('for up to 8 independent argument sets', $catalog->get('add_member')->getDefinition()['function']['description']);
		$this->assertSame(['batchable' => true, 'batchIndependent' => true, 'maxBatchSize' => 8], $catalog->get('add_member')->getMetadata());
	}

	public function testDuplicateAndEmptyFunctionNamesAreRejected(): void {
		$builder = new AgentCapabilityCatalogBuilder();
		foreach ([[['function' => []]], [
			['function' => ['name' => 'same']],
			['function' => ['name' => 'same']]
		]] as $definitions) {
			try {
				$builder->build([], $definitions);
				$this->fail('Invalid catalog accepted.');
			}
			catch (\RuntimeException $exception) {
				$this->assertNotEmpty($exception->getMessage());
			}
		}
	}
}
