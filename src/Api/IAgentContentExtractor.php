<?php declare(strict_types=1);

namespace MissionBay\Api;

use MissionBay\Dto\AgentContentItem;

/**
 * IAgentContentExtractor
 *
 * Extractors produce normalized content items from external sources.
 *
 * Extended contract:
 * - extract() returns items (optionally "claimed" work units)
 * - ack()/fail() allow the coordinator to report processing outcome back
 *   to the extractor implementation (e.g. queue tables, checkpoints, locks).
 *
 * Rationale:
 * - keeps queue-specific knowledge inside extractor
 * - avoids additional interfaces/resources
 * - coordinator stays generic and only reports outcome
 */
interface IAgentContentExtractor {

	/**
	 * Extracts content items from a given context.
	 *
	 * @param IAgentContext $context
	 * @return AgentContentItem[] Normalized, hash-stable content items
	 */
	public function extract(IAgentContext $context): array;

	/**
	 * Acknowledge that an extracted item was processed successfully.
	 *
	 * Implementations may update queue state, release locks, set deleted_at, etc.
	 * Must be safe to call even if the extractor does not use a queue (no-op).
	 *
	 * @param AgentContentItem $item
	 * @param array<string,mixed> $result Optional result metadata (e.g. deleted_count, num_chunks)
	 */
	public function ack(AgentContentItem $item, array $result = []): void;

	/**
	 * Report that processing of an extracted item failed.
	 *
	 * Implementations may update queue state, apply retry policy, store error messages, etc.
	 * Must be safe to call even if the extractor does not use a queue (no-op).
	 *
	 * @param AgentContentItem $item
	 * @param string $errorMessage Short error message to persist/log
	 * @param bool $retryHint If true, implementation should prefer retry if supported
	 */
	public function fail(AgentContentItem $item, string $errorMessage, bool $retryHint = true): void;
}
