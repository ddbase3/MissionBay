<?php declare(strict_types=1);

namespace MissionBay\Api;

use MissionBay\Dto\AgentParsedContent;

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
	 * @param AgentParsedContent $parsed
	 * @return bool
	 */
	public function supports(AgentParsedContent $parsed): bool;

	/**
	 * Creates chunks:
	 * [
	 * 	 ['id' => string, 'text' => string, 'meta' => array],
	 * 	 ...
	 * ]
	 * @param AgentParsedContent $parsed
	 * @return array<int,array<string,mixed>>
	 */
	public function chunk(AgentParsedContent $parsed): array;
}
