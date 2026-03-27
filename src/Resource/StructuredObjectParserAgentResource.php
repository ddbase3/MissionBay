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

use MissionBay\Api\IAgentContentParser;
use MissionBay\Dto\AgentContentItem;
use MissionBay\Dto\AgentParsedContent;

class StructuredObjectParserAgentResource extends AbstractAgentResource implements IAgentContentParser {

	public static function getName(): string {
		return 'structuredobjectparseragentresource';
	}

	public function getDescription(): string {
		return 'Parser for associative arrays or structured CRM/CMS objects.';
	}

	public function getPriority(): int {
		return 100;
	}

	public function supports(mixed $item): bool {
		if (!$item instanceof AgentContentItem) {
			return false;
		}

		return is_array($item->content) || is_object($item->content);
	}

	public function parse(mixed $item): AgentParsedContent {
		if (!$item instanceof AgentContentItem) {
			throw new \InvalidArgumentException(
				"StructuredObjectParser expects AgentContentItem."
			);
		}

		return new AgentParsedContent(
			text: '',					// no text yet
			metadata: $item->metadata,
			structured: $item->content,	// raw structured data
			attachments: []
		);
	}
}
