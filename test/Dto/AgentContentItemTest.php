<?php declare(strict_types=1);

namespace MissionBay\Test\Dto;

use MissionBay\Dto\AgentContentItem;
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
