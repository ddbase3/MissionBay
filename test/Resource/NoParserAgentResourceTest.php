<?php declare(strict_types=1);

namespace Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\NoParserAgentResource;
use MissionBay\Dto\AgentContentItem;
use MissionBay\Dto\AgentParsedContent;

/**
 * @covers \MissionBay\Resource\NoParserAgentResource
 */
class NoParserAgentResourceTest extends TestCase {

	public function testGetName(): void {
		$this->assertSame('noparseragentresource', NoParserAgentResource::getName());
	}

	public function testPriority(): void {
		$r = new NoParserAgentResource('p1');
		$this->assertSame(999, $r->getPriority());
	}

	public function testSupportsFalseForNonAgentContentItem(): void {
		$r = new NoParserAgentResource('p2');

		$this->assertFalse($r->supports(new \stdClass()));
		$this->assertFalse($r->supports('x'));
		$this->assertFalse($r->supports(null));
	}

	public function testSupportsFalseForBinaryItem(): void {
		$r = new NoParserAgentResource('p3');

		$item = new AgentContentItem(
			id: 'id1',
			hash: 'h1',
			contentType: 'application/octet-stream',
			content: "\x00\x01\x02",
			isBinary: true,
			size: 3,
			metadata: ['a' => 1]
		);

		$this->assertFalse($r->supports($item));
	}

	public function testSupportsFalseWhenContentIsNotString(): void {
		$r = new NoParserAgentResource('p4');

		$item = new AgentContentItem(
			id: 'id2',
			hash: 'h2',
			contentType: 'text/plain',
			content: ['not a string'],
			isBinary: false,
			size: 0,
			metadata: []
		);

		$this->assertFalse($r->supports($item));
	}

	public function testSupportsFalseWhenContentIsEmptyOrWhitespace(): void {
		$r = new NoParserAgentResource('p5');

		$item1 = new AgentContentItem(
			id: 'id3',
			hash: 'h3',
			contentType: 'text/plain',
			content: '',
			isBinary: false,
			size: 0,
			metadata: []
		);

		$item2 = new AgentContentItem(
			id: 'id4',
			hash: 'h4',
			contentType: 'text/plain',
			content: "   \n\t ",
			isBinary: false,
			size: 5,
			metadata: []
		);

		$this->assertFalse($r->supports($item1));
		$this->assertFalse($r->supports($item2));
	}

	public function testSupportsTrueForPlainTextItem(): void {
		$r = new NoParserAgentResource('p6');

		$item = new AgentContentItem(
			id: 'id5',
			hash: 'h5',
			contentType: 'text/plain',
			content: " Hello \n",
			isBinary: false,
			size: 8,
			metadata: ['source' => 'unit-test']
		);

		$this->assertTrue($r->supports($item));
	}

	public function testParseThrowsForNonAgentContentItem(): void {
		$r = new NoParserAgentResource('p7');

		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('NoParser: Expected AgentContentItem.');

		/** @phpstan-ignore-next-line */
		$r->parse(new \stdClass());
	}

	public function testParseReturnsParsedContentWithTrimmedTextAndForwardedMetadata(): void {
		$r = new NoParserAgentResource('p8');

		$item = new AgentContentItem(
			id: 'id6',
			hash: 'h6',
			contentType: 'text/plain',
			content: "  Hello world \n",
			isBinary: false,
			size: 14,
			metadata: ['k' => 'v', 'n' => 1]
		);

		$parsed = $r->parse($item);

		$this->assertInstanceOf(AgentParsedContent::class, $parsed);
		$this->assertSame('Hello world', $parsed->text);
		$this->assertSame(['k' => 'v', 'n' => 1], $parsed->metadata);
		$this->assertNull($parsed->structured);
		$this->assertSame([], $parsed->attachments);
	}
}
