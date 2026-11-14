<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace Base3\MissionBay\Test\Capability;

use Base3\MissionBay\Capability\AgentCapabilityCatalogBuilder;
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
