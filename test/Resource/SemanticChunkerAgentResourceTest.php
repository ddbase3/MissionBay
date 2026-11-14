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
use Base3\MissionBay\Resource\SemanticChunkerAgentResource;
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
