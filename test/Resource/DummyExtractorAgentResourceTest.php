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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Base3\MissionBay\Resource\DummyExtractorAgentResource;
use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\MissionBay\Dto\AgentContentItem;

#[CoversClass(\Base3\MissionBay\Resource\DummyExtractorAgentResource::class)]
class DummyExtractorAgentResourceTest extends TestCase {

	private function makeContextStub(): IAgentContext {
		// DummyExtractorAgentResource nutzt $context nicht.
		// createStub() erzeugt einen Interface-Stub ohne Expectations -> keine PHPUnit Notices.
		return $this->createStub(IAgentContext::class);
	}

	public function testGetName(): void {
		$this->assertSame('dummyextractoragentresource', DummyExtractorAgentResource::getName());
	}

	public function testGetDescription(): void {
		$r = new DummyExtractorAgentResource('x1');
		$this->assertSame(
			'Returns a fixed list of plain-text content items for testing.',
			$r->getDescription()
		);
	}

	public function testExtractReturnsTwoStablePlainTextItems(): void {
		$r = new DummyExtractorAgentResource('x2');
		$context = $this->makeContextStub();

		$items = $r->extract($context);

		$this->assertIsArray($items);
		$this->assertCount(2, $items);

		foreach ($items as $item) {
			$this->assertInstanceOf(AgentContentItem::class, $item);
			$this->assertSame('text/plain', $item->contentType);
			$this->assertFalse($item->isBinary);
			$this->assertTrue($item->isText());
			$this->assertIsString($item->content);
			$this->assertSame([], $item->metadata);
			$this->assertSame(strlen((string)$item->content), $item->size);
		}

		$this->assertSame('Hello world, this is a test.', $items[0]->content);
		$this->assertSame('Second test content block.', $items[1]->content);
	}

	public function testExtractUsesSha256HashAsIdAndHash(): void {
		$r = new DummyExtractorAgentResource('x3');
		$context = $this->makeContextStub();

		$items = $r->extract($context);

		$this->assertCount(2, $items);

		foreach ($items as $item) {
			$expected = hash('sha256', (string)$item->content);

			$this->assertSame($expected, $item->hash);
			$this->assertSame($expected, $item->id);
		}
	}

	public function testExtractIsDeterministicAcrossCalls(): void {
		$r = new DummyExtractorAgentResource('x4');
		$context = $this->makeContextStub();

		$a = $r->extract($context);
		$b = $r->extract($context);

		$this->assertCount(2, $a);
		$this->assertCount(2, $b);

		for ($i = 0; $i < 2; $i++) {
			$this->assertSame($a[$i]->id, $b[$i]->id);
			$this->assertSame($a[$i]->hash, $b[$i]->hash);
			$this->assertSame($a[$i]->contentType, $b[$i]->contentType);
			$this->assertSame($a[$i]->content, $b[$i]->content);
			$this->assertSame($a[$i]->isBinary, $b[$i]->isBinary);
			$this->assertSame($a[$i]->size, $b[$i]->size);
			$this->assertSame($a[$i]->metadata, $b[$i]->metadata);
		}
	}
}
