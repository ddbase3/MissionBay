<?php declare(strict_types=1);

namespace MissionBay\Api;

/**
 * Chunkers split parsed content into embeddings-friendly chunks.
 * They are priority-driven and selected dynamically by supports().
 */
interface IAgentChunker {

	/**
	 * Priority for chunker selection.
	 * Lower values are chosen first.
	 */
	public function getPriority(): int;

	/**
	 * Whether this chunker supports the parsed content.
	 */
	public function supports(array $parsed): bool;

	/**
	 * Creates chunks:
	 * [
	 * 	 ['id' => string, 'text' => string, 'meta' => array],
	 * 	 ...
	 * ]
	 */
	public function chunk(array $parsed): array;
}
