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

namespace MissionBay\Resource;

use MissionBay\Api\IAgentContentExtractor;
use AssistantFoundation\Api\IAgentContext;
use MissionBay\Dto\AgentContentItem;

/**
 * DummyExtractorAgentResource
 *
 * Produces fixed text items for testing embedding pipelines.
 */
class DummyExtractorAgentResource extends AbstractAgentResource implements IAgentContentExtractor {

	public static function getName(): string {
		return 'dummyextractoragentresource';
	}

	public function getDescription(): string {
		return 'Returns a fixed list of plain-text content items for testing.';
	}

	/**
	 * @return AgentContentItem[]
	 */
	public function extract(IAgentContext $context): array {
		$items = [];

		$texts = [
			"Hello world, this is a test.",
			"Second test content block."
		];

		foreach ($texts as $text) {
			$hash = hash('sha256', $text);

			$items[] = new AgentContentItem(
				action: 'upsert',
				collectionKey: 'dummy_text',
				id: $hash,
				hash: $hash,
				contentType: 'text/plain',
				content: $text,
				isBinary: false,
				size: strlen($text),
				metadata: []
			);
		}

		return $items;
	}

	/**
	 * Ack hook (dummy).
	 */
	public function ack(AgentContentItem $item, array $result = []): void {
		// no-op
	}

	/**
	 * Fail hook (dummy).
	 */
	public function fail(AgentContentItem $item, string $errorMessage, bool $retryHint = true): void {
		// no-op
	}
}
