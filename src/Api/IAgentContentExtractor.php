<?php declare(strict_types=1);

namespace MissionBay\Api;

use MissionBay\Dto\AgentContentItem;

/**
 * Extractors produce normalized content items from external sources
 * (uploads, database rows, filesystem paths, streams, etc).
 *
 * Each extractor must return a list of stable AgentContentItem objects.
 */
interface IAgentContentExtractor {

	/**
	 * Extracts content items from a given context.
	 *
	 * @param IAgentContext $context
	 * @return AgentContentItem[] Normalized, hash-stable content items
	 */
	public function extract(IAgentContext $context): array;
}
