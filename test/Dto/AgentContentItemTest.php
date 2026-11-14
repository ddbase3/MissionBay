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

namespace Base3\MissionBay\Test\Dto;

use Base3\MissionBay\Dto\AgentContentItem;
use PHPUnit\Framework\TestCase;

final class AgentContentItemTest extends TestCase {

	public function testTextUpsertPreservesPayloadAndRoutingMetadata(): void {
		$item = new AgentContentItem('UPSERT', 'course', 'queue-id', 'hash', 'text/plain', 'Text', false, 4, ['content_uuid' => 'uuid']);
		$this->assertTrue($item->isText());
		$this->assertTrue($item->isUpsert());
		$this->assertFalse($item->isDelete());
		$this->assertSame('course', $item->collectionKey);
		$this->assertSame('queue-id', $item->id);
		$this->assertSame('uuid', $item->metadata['content_uuid']);
		$this->assertSame('Text', $item->content);
	}

	public function testBinaryDeleteKeepsMetadataWithoutRewritingContent(): void {
		$item = new AgentContentItem('DELETE', 'course', 'queue-id', '', 'application/octet-stream', "\x00", true, 1);
		$this->assertTrue($item->isDelete());
		$this->assertFalse($item->isText());
		$this->assertFalse($item->isUpsert());
		$this->assertSame("\x00", $item->content);
		$this->assertSame([], $item->metadata);
	}
}
