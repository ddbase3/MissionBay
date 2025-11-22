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
                return 'Pass-through parser for content items that already contain plain text.';
        }

        public function getPriority(): int {
                return 999; // last fallback
        }

        public function supports(mixed $item): bool {
                return $item instanceof AgentContentItem
                        && is_string($item->content)
                        && trim($item->content) !== '';
        }

        public function parse(mixed $item): AgentParsedContent {
                if (!$item instanceof AgentContentItem) {
                        throw new \InvalidArgumentException("NoParser: Expected AgentContentItem.");
                }

                return new AgentParsedContent(
                        text: trim($item->content),
                        metadata: $item->metadata
                );
        }
}
