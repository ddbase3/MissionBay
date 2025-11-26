<?php declare(strict_types=1);

namespace MissionBay\Dto;

/**
 * AgentContentItem
 *
 * Unified content model with a single content string.
 * $content always contains the raw bytes or text.
 */
class AgentContentItem {

	/** internal extractor-local ID, never used for vector storage */
        public string $id;
        public string $hash;
        public string $contentType;

        /** Raw bytes OR text — always a string */
        public mixed $content;

        /** true = binary, false = text */
        public bool $isBinary;

        public int $size;

        /** Free-form metadata */
        public array $metadata = [];

        public function __construct(
                string $id,
                string $hash,
                string $contentType,
                mixed $content,
                bool $isBinary,
                int $size,
                array $metadata = []
        ) {
                $this->id = $id;
                $this->hash = $hash;
                $this->contentType = $contentType;
                $this->content = $content;
                $this->isBinary = $isBinary;
                $this->size = $size;
                $this->metadata = $metadata;
        }

        public function isText(): bool {
                return !$this->isBinary;
        }
}
