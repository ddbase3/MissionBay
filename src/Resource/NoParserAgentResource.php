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

class NoParserAgentResource extends AbstractAgentResource implements IAgentContentParser {

	public static function getName(): string {
		return 'noparseragentresource';
	}

	public function getDescription(): string {
		return 'Pass-through parser for plain-text items. Does not handle binary content.';
	}

	public function getPriority(): int {
		return 999;
	}

	public function supports(mixed $item): bool {
		if (!$item instanceof AgentContentItem) {
			return false;
		}

		if ($item->isBinary === true) {
			return false;
		}

		if (!is_string($item->content)) {
			return false;
		}

		if (trim($item->content) === '') {
			return false;
		}

		return true;
	}

	public function parse(mixed $item): AgentParsedContent {
		if (!$item instanceof AgentContentItem) {
			throw new \InvalidArgumentException("NoParser: Expected AgentContentItem.");
		}

		return new AgentParsedContent(
			text: trim($item->content),
			metadata: $item->metadata,
			structured: null,
			attachments: []
		);
	}
}
