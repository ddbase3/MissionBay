<?php declare(strict_types=1);

namespace MissionBay\Resource;

use MissionBay\Api\IAgentContentExtractor;
use MissionBay\Api\IAgentContext;
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
}
