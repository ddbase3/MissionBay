<?php declare(strict_types=1);

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
