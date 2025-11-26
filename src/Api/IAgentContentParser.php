<?php declare(strict_types=1);

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
