<?php declare(strict_types=1);

namespace MissionBay\Api;

use MissionBay\Api\IAgentContext;

/**
 * Extractors produce raw content objects directly from sources
 * (database rows, filesystem, http, streams, etc).
 */
interface IAgentContentExtractor {

	/**
	 * Extracts raw content objects.
	 *
	 * @param IAgentContext $context
	 * @return array<int,mixed> Raw content items
	 */
	public function extract(IAgentContext $context): array;
}
