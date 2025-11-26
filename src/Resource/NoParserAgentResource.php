<?php declare(strict_types=1);

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
