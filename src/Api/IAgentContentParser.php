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

namespace MissionBay\Api;

use MissionBay\Dto\AgentContentItem;
use MissionBay\Dto\AgentParsedContent;

/**
 * Parsers convert an AgentContentItem into AgentParsedContent.
 */
interface IAgentContentParser {

	public function getPriority(): int;

	/**
	 * Determines whether this parser can handle the content.
	 *
	 * @param AgentContentItem $item
	 */
	public function supports(AgentContentItem $item): bool;

	/**
	 * Parses raw content into a normalized parsed content object.
	 *
	 * @param AgentContentItem $item
	 * @return AgentParsedContent
	 */
	public function parse(AgentContentItem $item): AgentParsedContent;
}
