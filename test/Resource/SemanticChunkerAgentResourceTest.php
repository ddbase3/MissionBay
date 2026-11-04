<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use MissionBay\Dto\AgentParsedContent;
use MissionBay\Resource\SemanticChunkerAgentResource;
use PHPUnit\Framework\TestCase;

final class SemanticChunkerAgentResourceTest extends TestCase {

	public function testTechnicalIdentityAndPriorityRemainStable(): void {
		$chunker = new SemanticChunkerAgentResource('chunker');
		$this->assertSame('semanticchunkeragentresource', SemanticChunkerAgentResource::getName());
		$this->assertSame('chunker', $chunker->getId());
		$this->assertSame(100, $chunker->getPriority());
	}

	public function testSupportsOnlyNonBlankText(): void {
		$chunker = new SemanticChunkerAgentResource();
		$this->assertFalse($chunker->supports(new AgentParsedContent(null)));
		$this->assertFalse($chunker->supports(new AgentParsedContent('')));
		$this->assertFalse($chunker->supports(new AgentParsedContent(" \n\t ")));
		$this->assertTrue($chunker->supports(new AgentParsedContent('Useful text')));
	}

	public function testEmptyContentProducesNoChunks(): void {
		$chunker = new SemanticChunkerAgentResource();
		$this->assertSame([], $chunker->chunk(new AgentParsedContent(null)));
		$this->assertSame([], $chunker->chunk(new AgentParsedContent('   ')));
	}

	public function testParagraphsPreserveMetadataAndChronologicalIndices(): void {
		$chunker = new SemanticChunkerAgentResource('chunker');
		$chunker->setConfig(['overlap' => 0]);
		$metadata = ['source' => 'manual', 'chunk_index' => 999];
		$chunks = $chunker->chunk(new AgentParsedContent("  First paragraph.  \n\n Second paragraph. ", $metadata));
		$this->assertCount(2, $chunks);
		$this->assertSame(['First paragraph.', 'Second paragraph.'], array_column($chunks, 'text'));
		$this->assertSame(['source' => 'manual', 'chunk_index' => 0], $chunks[0]['meta']);
		$this->assertSame(['source' => 'manual', 'chunk_index' => 1], $chunks[1]['meta']);
		$this->assertStringContainsString('chunk_', $chunks[0]['id']);
		$this->assertNotSame($chunks[0]['id'], $chunks[1]['id']);
	}

	public function testLongParagraphBreaksAtSentenceBoundaries(): void {
		$chunker = new SemanticChunkerAgentResource();
		$chunker->setConfig(['max_length' => 10, 'min_length' => 1, 'overlap' => 0]);
		$chunks = $chunker->chunk(new AgentParsedContent('Alpha. Bravo. Charlie.'));
		$this->assertSame(['Alpha.', 'Bravo.', 'Charlie.'], array_column($chunks, 'text'));
	}

	public function testShortSegmentsAreBufferedBeforeLongerSegments(): void {
		$chunker = new SemanticChunkerAgentResource();
		$chunker->setConfig(['max_length' => 10, 'min_length' => 7, 'overlap' => 0]);
		$chunks = $chunker->chunk(new AgentParsedContent('Alpha. Bravo. Charlie.'));
		$this->assertSame(["Alpha.\nBravo.", 'Charlie.'], array_column($chunks, 'text'));
	}

	public function testFinalShortSegmentsRemainAvailable(): void {
		$chunker = new SemanticChunkerAgentResource();
		$chunker->setConfig(['max_length' => 7, 'min_length' => 50, 'overlap' => 0]);
		$chunks = $chunker->chunk(new AgentParsedContent('A. B. C.'));
		$this->assertSame(['A. B. C.'], array_column($chunks, 'text'));
	}
}
