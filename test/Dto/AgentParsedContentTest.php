<?php declare(strict_types=1);

namespace MissionBay\Test\Dto;

use MissionBay\Dto\AgentParsedContent;
use PHPUnit\Framework\TestCase;

final class AgentParsedContentTest extends TestCase {

	public function testContentMetadataAndAttachedStructuresArePreserved(): void {
		$structured = ['content' => ['heading' => 'Title']];
		$attachments = [['type' => 'image', 'id' => 8]];
		$content = new AgentParsedContent(' Hello ', ['source' => 'file'], $structured, $attachments, ' text/html ', '  xrm ', ' a-1 ');
		$this->assertSame(' Hello ', $content->text);
		$this->assertSame(['source' => 'file'], $content->metadata);
		$this->assertSame($structured, $content->structured);
		$this->assertSame($attachments, $content->attachments);
		$this->assertSame('text/html', $content->contentType);
		$this->assertSame('xrm', $content->sourceType);
		$this->assertSame('a-1', $content->sourceId);
	}

	public function testMissingOptionalFieldsRetainNullAndEmptyDefaults(): void {
		$content = new AgentParsedContent(null);
		$this->assertNull($content->text);
		$this->assertNull($content->sourceType);
		$this->assertNull($content->sourceId);
		$this->assertNull($content->contentType);
		$this->assertNull($content->structured);
		$this->assertSame([], $content->metadata);
		$this->assertSame([], $content->attachments);
	}
}
