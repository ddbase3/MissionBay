<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use MissionBay\Dto\AgentContentItem;
use MissionBay\Resource\StructuredObjectParserAgentResource;
use PHPUnit\Framework\TestCase;

final class StructuredObjectParserAgentResourceTest extends TestCase {

	public function testSupportsOnlyStructuredAgentContentItems(): void {
		$parser = new StructuredObjectParserAgentResource();
		$this->assertTrue($parser->supports($this->item(['customer' => 3])));
		$this->assertTrue($parser->supports($this->item((object)['customer' => 3])));
		$this->assertFalse($parser->supports($this->item('text')));
		$this->assertFalse($parser->supports(null));
	}

	public function testParsePreservesStructuredDataAndDomainMetadata(): void {
		$parser = new StructuredObjectParserAgentResource();
		$structured = ['user' => ['id' => 77]];
		$item = $this->item($structured);
		$parsed = $parser->parse($item);
		$this->assertSame('', $parsed->text);
		$this->assertSame($structured, $parsed->structured);
		$this->assertSame(['content_uuid' => 'item-1', 'title' => 'Example'], $parsed->metadata);
		$this->assertSame([], $parsed->attachments);
	}

	public function testNonContentItemIsRejectedBeforeParsing(): void {
		$parser = new StructuredObjectParserAgentResource();
		try {
			$parser->parse(['invalid' => true]);
			$this->fail('Parser must reject an untyped content item.');
		} catch (\InvalidArgumentException $e) {
			$this->assertStringContainsString('AgentContentItem', $e->getMessage());
		}
	}

	public function testNameAndPriorityAreStable(): void {
		$parser = new StructuredObjectParserAgentResource();
		$this->assertSame('structuredobjectparseragentresource', StructuredObjectParserAgentResource::getName());
		$this->assertSame(100, $parser->getPriority());
	}

	private function item(mixed $content): AgentContentItem {
		return new AgentContentItem('upsert', 'collection', 'item-1', 'hash', 'application/json', $content, false, 0, ['content_uuid' => 'item-1', 'title' => 'Example']);
	}
}
