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

namespace Base3\MissionBay\Test\Resource;

use Base3\MissionBay\Dto\AgentParsedContent;
use Base3\MissionBay\Resource\XrmChunkerAgentResource;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/XrmChunkerIdentityResolverStub.php';

final class XrmChunkerBoundaryCoverageTest extends TestCase {

	private function chunker(array $config = []): XrmChunkerAgentResource {
		$chunker = new XrmChunkerAgentResource(new XrmChunkerIdentityResolverStub(), 'xrm-test');
		$chunker->setConfig($config);
		return $chunker;
	}

	private function parsed(array|object $structured, array $metadata = []): AgentParsedContent {
		return new AgentParsedContent(null, $metadata, $structured);
	}

	public function testTechnicalNameAndResourceIdentity(): void {
		$chunker = $this->chunker();
		$this->assertSame('xrmchunkeragentresource', XrmChunkerAgentResource::getName());
		$this->assertSame('xrm-test', $chunker->id());
		$this->assertSame(40, $chunker->getPriority());
		$this->assertStringContainsString('XRM', $chunker->getDescription());
	}

	public function testDefaultSettingsAreResolvedAndExposed(): void {
		$this->assertSame(['max_length' => 800, 'min_length' => 200, 'overlap' => 50, 'inline_meta_fields' => ['name', 'tags', 'type']], $this->chunker()->getOptions());
	}

	public function testConfigValuesUseTheExistingConfigValueResolver(): void {
		$chunker = $this->chunker([
			'max_length' => ['mode' => 'fixed', 'value' => 160],
			'min_length' => ['mode' => 'fixed', 'value' => 20],
			'overlap' => ['mode' => 'fixed', 'value' => 0],
			'inline_meta_fields' => ['mode' => 'fixed', 'value' => ' title , owner ,,']
		]);
		$this->assertSame(['max_length' => 160, 'min_length' => 20, 'overlap' => 0, 'inline_meta_fields' => ['title', 'owner']], $chunker->getOptions());
	}

	public function testArrayBasedMetadataFieldsAreTrimmed(): void {
		$this->assertSame(['tags', 'name'], $this->chunker(['inline_meta_fields' => [' tags ', '', 'name']])->getOptions()['inline_meta_fields']);
	}

	public function testEmptyMetadataFieldStringsKeepDefaults(): void {
		$this->assertSame(['name', 'tags', 'type'], $this->chunker(['inline_meta_fields' => ', ,'])->getOptions()['inline_meta_fields']);
	}

	public function testEmptyMetadataFieldArraysKeepDefaults(): void {
		$this->assertSame(['name', 'tags', 'type'], $this->chunker(['inline_meta_fields' => []])->getOptions()['inline_meta_fields']);
	}

	public function testUnsupportedMetadataFieldTypeKeepsDefaults(): void {
		$this->assertSame(['name', 'tags', 'type'], $this->chunker(['inline_meta_fields' => false])->getOptions()['inline_meta_fields']);
	}

	public function testSupportsNewPayloadShape(): void {
		$this->assertTrue($this->chunker()->supports($this->parsed(['payload' => ['name' => 'Alpha']])));
	}

	public function testSupportsObjectPayloadShape(): void {
		$this->assertTrue($this->chunker()->supports($this->parsed((object)['payload' => (object)['name' => 'Alpha']])));
	}

	public function testSupportsLegacyIdAndDataShape(): void {
		$this->assertTrue($this->chunker()->supports($this->parsed(['id' => 17, 'data' => []])));
	}

	public function testDoesNotSupportPlainText(): void {
		$this->assertFalse($this->chunker()->supports(new AgentParsedContent('plain text')));
	}

	public function testDoesNotSupportIdWithoutData(): void {
		$this->assertFalse($this->chunker()->supports($this->parsed(['id' => 17])));
	}

	public function testDoesNotSupportDataWithoutId(): void {
		$this->assertFalse($this->chunker()->supports($this->parsed(['data' => ['name' => 'Alpha']])));
	}

	public function testDoesNotSupportScalarPayload(): void {
		$this->assertFalse($this->chunker()->supports($this->parsed(['payload' => 'text'])));
	}

	public function testShortStringsAreInlineMetadataAndNotSeparateSections(): void {
		$chunks = $this->chunker()->chunk($this->parsed(['id' => 1, 'data' => ['name' => 'Example', 'city' => 'Berlin']]));
		$this->assertCount(1, $chunks);
		$this->assertStringContainsString('City: Berlin', $chunks[0]['text']);
		$this->assertStringNotContainsString('## City', $chunks[0]['text']);
	}

	public function testLongStringsCreateNamedSections(): void {
		$chunks = $this->chunker(['max_length' => 900, 'min_length' => 30, 'overlap' => 0])->chunk($this->parsed(['id' => 1, 'data' => ['description' => str_repeat('A', 120)]]));
		$this->assertStringContainsString('## Description', $chunks[0]['text']);
	}

	public function testZeroAndNullValuesAreSkipped(): void {
		$chunks = $this->chunker()->chunk($this->parsed(['id' => 1, 'data' => ['count' => 0, 'text' => '0', 'blank' => '', 'missing' => null, 'active' => true]]));
		$this->assertStringContainsString('Active: true', $chunks[0]['text']);
		$this->assertStringNotContainsString('Count:', $chunks[0]['text']);
		$this->assertStringNotContainsString('Text: 0', $chunks[0]['text']);
		$this->assertStringNotContainsString('Blank:', $chunks[0]['text']);
	}

	public function testNumericAndBooleanFieldsBecomeMetadata(): void {
		$chunks = $this->chunker()->chunk($this->parsed(['id' => 1, 'data' => ['rating' => 4.5, 'enabled' => true]]));
		$this->assertStringContainsString('Rating: 4.5', $chunks[0]['text']);
		$this->assertStringContainsString('Enabled: true', $chunks[0]['text']);
	}

	public function testShortNestedObjectBecomesMetadata(): void {
		$chunks = $this->chunker()->chunk($this->parsed(['id' => 1, 'data' => ['detail' => ['k' => 'v']]]));
		$this->assertStringContainsString('Detail:', $chunks[0]['text']);
		$this->assertStringNotContainsString('## Detail', $chunks[0]['text']);
	}

	public function testLongNestedObjectBecomesSection(): void {
		$chunks = $this->chunker(['max_length' => 2000, 'overlap' => 0])->chunk($this->parsed(['id' => 1, 'data' => ['detail' => ['long' => str_repeat('A', 160)]]]));
		$this->assertStringContainsString('## Detail', $chunks[0]['text']);
	}

	public function testDataNameOverridesRootName(): void {
		$chunks = $this->chunker()->chunk($this->parsed(['id' => 1, 'name' => 'Root', 'data' => ['name' => 'Data']]));
		$this->assertStringContainsString('# Data', $chunks[0]['text']);
	}

	public function testRootNameIsUsedWhenPayloadHasNoName(): void {
		$chunks = $this->chunker()->chunk($this->parsed(['payload' => [], 'name' => 'Root']));
		$this->assertStringContainsString('# Root', $chunks[0]['text']);
	}

	public function testNewShapeFallsBackToTypeAlias(): void {
		$chunks = $this->chunker()->chunk($this->parsed(['payload' => [], 'type' => ['alias' => 'product']]));
		$this->assertStringContainsString('# Entity (product)', $chunks[0]['text']);
	}

	public function testNewShapeFallsBackToSysentryId(): void {
		$chunks = $this->chunker()->chunk($this->parsed(['payload' => [], 'sysentry' => ['id' => 29]]));
		$this->assertStringContainsString('# Entity #29', $chunks[0]['text']);
	}

	public function testLegacyShapeFallsBackToId(): void {
		$chunks = $this->chunker()->chunk($this->parsed(['id' => '29', 'data' => []]));
		$this->assertStringContainsString('# Entity #29', $chunks[0]['text']);
	}

	public function testUnknownIdentityFallsBackToEntity(): void {
		$chunks = $this->chunker()->chunk($this->parsed(['payload' => []]));
		$this->assertStringContainsString('# Entity', $chunks[0]['text']);
	}

	public function testMetadataIsPreservedOnAllChunks(): void {
		$chunks = $this->chunker(['max_length' => 90, 'min_length' => 20, 'overlap' => 0])->chunk($this->parsed(['id' => 1, 'data' => ['description' => str_repeat('Long content. ', 30)]], ['source_id' => 'x1']));
		$this->assertTrue(count($chunks) > 1);
		foreach ($chunks as $chunk) {
			$this->assertSame(['source_id' => 'x1'], $chunk['meta']);
			$this->assertStringContainsString('<!-- meta:', $chunk['text']);
			$this->assertStringContainsString('chunk_', $chunk['id']);
		}
	}

	public function testAllChunksReceiveInlineMetadata(): void {
		$chunks = $this->chunker(['max_length' => 90, 'min_length' => 20, 'overlap' => 0])->chunk($this->parsed(['id' => 1, 'tags' => ['a', 'b'], 'data' => ['name' => 'Test', 'description' => str_repeat('Long content. ', 30)]]));
		$this->assertTrue(count($chunks) > 1);
		foreach ($chunks as $chunk) {
			$this->assertStringContainsString('name="Test"', $chunk['text']);
			$this->assertStringContainsString('tags="a,b"', $chunk['text']);
		}
	}

	public function testInlineMetadataQuotesAreEscaped(): void {
		$chunks = $this->chunker()->chunk($this->parsed(['id' => 1, 'name' => '"quoted"', 'data' => []]));
		$this->assertStringContainsString("name=\"'quoted'\"", $chunks[0]['text']);
	}

	public function testInlineMetadataCanBeRestrictedToCustomFields(): void {
		$chunks = $this->chunker(['inline_meta_fields' => 'city'])->chunk($this->parsed(['id' => 1, 'name' => 'Example', 'data' => ['city' => 'Berlin']]));
		$this->assertStringContainsString('city="Berlin"', $chunks[0]['text']);
		$this->assertStringNotContainsString('name="Example"', $chunks[0]['text']);
	}

	public function testInlineMetadataObjectIsJsonEncoded(): void {
		$chunks = $this->chunker(['inline_meta_fields' => 'attrs'])->chunk($this->parsed(['id' => 1, 'attrs' => (object)['region' => 'eu'], 'data' => []]));
		$this->assertStringContainsString('attrs=', $chunks[0]['text']);
		$this->assertStringContainsString('region', $chunks[0]['text']);
	}

	public function testNewlinesAreNormalized(): void {
		$chunks = $this->chunker(['max_length' => 900, 'min_length' => 20, 'overlap' => 0])->chunk($this->parsed(['id' => 1, 'data' => ['description' => str_repeat('a', 60) . "\r\n" . str_repeat('b', 60)]]));
		$this->assertStringNotContainsString("\r", $chunks[0]['text']);
	}

	public function testSmallMaxLengthForcesHardSplits(): void {
		$chunks = $this->chunker(['max_length' => 50, 'min_length' => 1, 'overlap' => 0])->chunk($this->parsed(['id' => 1, 'data' => ['description' => str_repeat('a', 280)]]));
		$this->assertTrue(count($chunks) > 2);
	}

	public function testOverlapPrefixesChunksAfterTheFirst(): void {
		$data = ['id' => 1, 'data' => ['description' => str_repeat('Hello world. ', 25)]];
		$withOverlap = $this->chunker(['max_length' => 100, 'min_length' => 5, 'overlap' => 15])->chunk($this->parsed($data));
		$withoutOverlap = $this->chunker(['max_length' => 100, 'min_length' => 5, 'overlap' => 0])->chunk($this->parsed($data));
		$this->assertTrue(count($withOverlap) > 1);
		$this->assertCount(count($withoutOverlap), $withOverlap);
		$this->assertSame($withoutOverlap[0]['text'], $withOverlap[0]['text']);
		$this->assertTrue(strlen($withOverlap[1]['text']) > strlen($withoutOverlap[1]['text']));
	}

	public function testOptionsCanBeUpdatedWithoutChangingResourceId(): void {
		$chunker = $this->chunker();
		$chunker->setConfig(['max_length' => 75, 'overlap' => 0]);
		$this->assertSame('xrm-test', $chunker->getId());
		$this->assertSame(75, $chunker->getOptions()['max_length']);
		$this->assertSame(['max_length' => 75, 'overlap' => 0], $chunker->getConfig());
	}
}
