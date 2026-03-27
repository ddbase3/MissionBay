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

namespace MissionBay\Dto;

/**
 * AgentEmbeddingChunk
 *
 * Unified chunk DTO for the later stages of the pipeline:
 * Chunker -> AiEmbeddingNode -> Embedder -> VectorStore (+ Normalizer validation)
 *
 * This is the ONE new DTO we agreed on for:
 * - representing a single chunk of text
 * - carrying its index and metadata
 * - optionally carrying the embedding vector once computed
 *
 * Produced by: IAgentChunker (returned as AgentEmbeddingChunk[])
 * Enriched by: AiEmbeddingNode (vector assignment)
 * Consumed by: IAgentVectorStore (upsert) and Normalizer (payload build + validation)
 *
 * Key principles:
 * - collectionKey must be present to ensure collection-safe upsert/delete semantics.
 * - chunkIndex is mandatory and enforced by the coordinator (AiEmbeddingNode).
 * - metadata is domain metadata only. Workflow control stays outside.
 */
class AgentEmbeddingChunk {

	/**
	 * Target collection key where this chunk must be stored.
	 */
	public string $collectionKey;

	/**
	 * Chunk index within the source document/item.
	 * 0..n, deterministic for a given chunking strategy.
	 */
	public int $chunkIndex;

	/**
	 * Chunk text that will be embedded and stored.
	 */
	public string $text;

	/**
	 * Embedding vector (filled after embedding).
	 * Empty array means "not embedded" or "failed".
	 *
	 * @var array<float>
	 */
	public array $vector = [];

	/**
	 * Stable hash for the source version (typically from AgentContentItem::hash).
	 * Used for duplicate detection and stable chunk tokens.
	 */
	public string $hash;

	/**
	 * Domain metadata merged from:
	 * - AgentContentItem metadata
	 * - AgentParsedContent metadata
	 * - chunker-specific metadata
	 *
	 * Must include stable lifecycle keys used by delete/replace, typically:
	 * - content_uuid
	 *
	 * Must NOT include workflow control fields.
	 */
	public array $metadata = [];

	public function __construct(
		string $collectionKey,
		int $chunkIndex,
		string $text,
		string $hash,
		array $metadata = [],
		array $vector = []
	) {
		$this->collectionKey = $collectionKey;
		$this->chunkIndex = $chunkIndex;
		$this->text = $text;
		$this->hash = $hash;
		$this->metadata = $metadata;
		$this->vector = $vector;
	}

	public function hasVector(): bool {
		return !empty($this->vector);
	}
}
