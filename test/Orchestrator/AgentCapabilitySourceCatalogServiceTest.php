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

namespace Base3\MissionBay\Test\Orchestrator;

use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\AssistantFoundation\Dto\AgentCapability;
use Base3\AssistantFoundation\Dto\AgentCapabilityCatalog;
use Base3\AssistantFoundation\Dto\AgentCapabilitySelectionConfig;
use Base3\MissionBay\Api\IAgentCapabilitySourceMetadata;
use Base3\MissionBay\Api\IAgentTool;
use Base3\MissionBay\Orchestrator\Service\AgentCapabilitySourceCatalogService;
use PHPUnit\Framework\TestCase;

final class AgentCapabilitySourceCatalogServiceTest extends TestCase {

	public function testBuildsCompactSourcesFromExactToolMetadata(): void {
		$reporting = new CapabilitySourceToolDouble(
			'ilias-reporting',
			'ILIAS Reporting',
			'Queries structured ILIAS reporting data.',
			['report_count', 'report_query']
		);
		$retrieval = new CapabilitySourceToolDouble(
			'ilias_retrieval',
			'ILIAS Retrieval',
			'Searches indexed ILIAS content.',
			['retrieval_search']
		);
		$catalog = $this->catalog([$reporting, $retrieval]);

		$sources = (new AgentCapabilitySourceCatalogService())->buildSources(
			$catalog,
			[$reporting, $retrieval],
			AgentCapabilitySelectionConfig::fromArray(['enabled' => false, 'max_tools' => 8, 'max_sources' => 4])
		);

		$this->assertSame(['ilias-reporting', 'ilias_retrieval'], array_keys($sources));
		$this->assertSame('ILIAS Reporting', $sources['ilias-reporting']['label']);
		$this->assertSame('Queries structured ILIAS reporting data.', $sources['ilias-reporting']['description']);
		$this->assertCount(2, $sources['ilias-reporting']['capabilities']);
		$this->assertSame('ILIAS Retrieval', $sources['ilias_retrieval']['label']);
		$this->assertSame('Searches indexed ILIAS content.', $sources['ilias_retrieval']['description']);
	}

	public function testSourceSelectionReplacesWorkingSetAndSupportsExplicitCombinations(): void {
		$reporting = new CapabilitySourceToolDouble(
			'ilias-reporting',
			'ILIAS Reporting',
			'Queries structured ILIAS reporting data.',
			['report_count', 'report_query']
		);
		$retrieval = new CapabilitySourceToolDouble(
			'ilias_retrieval',
			'ILIAS Retrieval',
			'Searches indexed ILIAS content.',
			['retrieval_search', 'retrieval_context']
		);
		$catalog = $this->catalog([$reporting, $retrieval]);
		$config = AgentCapabilitySelectionConfig::fromArray(['enabled' => false, 'max_tools' => 8, 'max_sources' => 4]);
		$service = new AgentCapabilitySourceCatalogService();
		$sources = $service->buildSources($catalog, [$reporting, $retrieval], $config);

		$first = $service->selectSources($catalog, $sources, ['ilias-reporting'], $config, [], 1);
		$second = $service->selectSources($catalog, $sources, ['ilias_retrieval'], $config, [], 2);
		$combined = $service->selectSources(
			$catalog,
			$sources,
			['ilias-reporting', 'ilias_retrieval'],
			$config,
			[],
			3
		);

		$this->assertSame(['report_count', 'report_query'], $first->getToolNames());
		$this->assertSame(['retrieval_context', 'retrieval_search'], $second->getToolNames());
		$this->assertNotContains('report_query', $second->getToolNames());
		$this->assertSame(
			['report_count', 'report_query', 'retrieval_context', 'retrieval_search'],
			$combined->getToolNames()
		);
	}

	public function testHardToolFiltersDefineTheSourceUniverseWithoutRelevancePreselection(): void {
		$reporting = new CapabilitySourceToolDouble(
			'ilias-reporting',
			'ILIAS Reporting',
			'Queries structured ILIAS reporting data.',
			['report_count', 'report_query']
		);
		$retrieval = new CapabilitySourceToolDouble(
			'ilias_retrieval',
			'ILIAS Retrieval',
			'Searches indexed ILIAS content.',
			['retrieval_search']
		);
		$catalog = $this->catalog([$reporting, $retrieval]);
		$config = AgentCapabilitySelectionConfig::fromArray([
			'enabled' => false,
			'max_tools' => 8,
			'max_sources' => 4,
			'include_tools' => ['report_query']
		]);

		$sources = (new AgentCapabilitySourceCatalogService())->buildSources(
			$catalog,
			[$reporting, $retrieval],
			$config
		);

		$this->assertSame(['ilias-reporting'], array_keys($sources));
		$this->assertSame(['report_query'], array_map(
			static fn(AgentCapability $capability): string => $capability->getName(),
			$sources['ilias-reporting']['capabilities']
		));
	}


	public function testCatalogCompactionKeepsEverySourceDiscoverable(): void {
		$tools = [];
		for ($index = 1; $index <= 40; $index++) {
			$tools[] = new CapabilitySourceToolDouble(
				'source-' . $index,
				'Source ' . $index,
				str_repeat('Long capability source description for routing. ', 30),
				['source_' . $index . '_read']
			);
		}
		$catalog = $this->catalog($tools);
		$config = AgentCapabilitySelectionConfig::fromArray([
			'enabled' => false,
			'max_tools' => 64,
			'max_sources' => 8,
			'semantic_max_prompt_characters' => 8000
		]);
		$service = new AgentCapabilitySourceCatalogService();
		$sources = $service->buildSources($catalog, $tools, $config);
		$rendered = $service->renderCatalog($sources, 2000);

		$this->assertStringContainsString('source-1', $rendered);
		$this->assertStringContainsString('source-40', $rendered);
		$this->assertCount(40, $sources);
	}

	public function testRejectsUnknownSourceAndSourceSetsThatExceedFunctionLimit(): void {
		$reporting = new CapabilitySourceToolDouble(
			'ilias-reporting',
			'ILIAS Reporting',
			'Queries structured ILIAS reporting data.',
			['report_count', 'report_query']
		);
		$catalog = $this->catalog([$reporting]);
		$service = new AgentCapabilitySourceCatalogService();
		$config = AgentCapabilitySelectionConfig::fromArray(['enabled' => false, 'max_tools' => 1, 'max_sources' => 2]);
		$sources = $service->buildSources($catalog, [$reporting], $config);

		try {
			$service->selectSources($catalog, $sources, ['missing-source'], $config, [], 1);
			$this->fail('Unknown source should be rejected.');
		}
		catch (\RuntimeException $e) {
			$this->assertStringContainsString('Unknown capability source id', $e->getMessage());
		}

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('maxTools is 1');
		$service->selectSources($catalog, $sources, ['ilias-reporting'], $config, [], 1);
	}

	/** @param array<int,CapabilitySourceToolDouble> $tools */
	private function catalog(array $tools): AgentCapabilityCatalog {
		$capabilities = [];
		foreach ($tools as $tool) {
			foreach ($tool->getToolDefinitions() as $definition) {
				$name = (string)($definition['function']['name'] ?? '');
				$capabilities[] = new AgentCapability(
					name: $name,
					title: $name,
					description: (string)($definition['function']['description'] ?? ''),
					category: 'test',
					tags: ['test'],
					priority: 0,
					definition: $definition,
					sourceId: $tool->getCapabilitySourceId(),
					sourceName: $tool::getName()
				);
			}
		}
		return new AgentCapabilityCatalog($capabilities);
	}
}

require_once __DIR__ . '/CapabilitySourceToolDouble.php';
