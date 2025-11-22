<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentContentExtractor;
use MissionBay\Api\IAgentContext;

/**
 * DummyExtractorAgentResource
 *
 * Simple static extractor for testing embedding pipelines.
 */
class DummyExtractorAgentResource extends AbstractAgentResource implements IAgentContentExtractor {

	public static function getName(): string {
		return 'dummyextractoragentresource';
	}

	public function getDescription(): string {
		return 'Returns a fixed list of simple text contents for testing.';
	}

	/**
	 * @return array<int,string>
	 */
	public function extract(IAgentContext $context): array {
		return [
			"Hello world, this is a test.",
			"Second test content block."
		];
	}
}
