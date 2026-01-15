<?php declare(strict_types=1);

namespace MissionBay\Dto;

/**
 * AgentParsedContent
 *
 * Unified parser output DTO.
 *
 * Produced by: IAgentContentParser::parse()
 * Consumed by: IAgentChunker::chunk()
 *
 * Key principles:
 * - Parser normalizes raw inputs (text/binary/structured) into a consistent representation.
 * - Chunkers decide how to turn this representation into semantic chunks.
 *
 * Metadata rules:
 * - metadata remains domain metadata (not workflow control).
 * - Any workflow control (action/collectionKey) stays on AgentContentItem, not here.
 */
class AgentParsedContent {

	/**
	 * Optional content type (mime / logical type) of the parsed representation.
	 * Examples: "text/plain", "text/html", "application/json", "application/x-xrm-sysentry-json"
	 */
	public ?string $contentType = null;

	/**
	 * Optional short source type hint (e.g. "xrm", "ilias", "filesystem").
	 * Purely informational for chunkers/parsers; routing stays on AgentContentItem.collectionKey.
	 */
	public ?string $sourceType = null;

	/**
	 * Optional source id (e.g. sysentry id, file id).
	 * Purely informational; lifecycle keys must live in metadata (e.g. content_uuid).
	 */
	public ?string $sourceId = null;

	/**
	 * Plain text extracted from the source.
	 * May be null/empty if chunker operates on structured data.
	 */
	public ?string $text = null;

	/**
	 * Domain metadata extracted or forwarded by parser.
	 * Example: title, lang, url, filename, created_at, updated_at, etc.
	 */
	public array $metadata = [];

	/**
	 * Optional structured representation:
	 * - associative arrays
	 * - JSON-like structures
	 * - DOM-like structures
	 * - Docling results
	 */
	public mixed $structured = null;

	/**
	 * Optional attachments (images, tables, binaries).
	 * Format is implementation-specific, but should be stable within a parser family.
	 */
	public array $attachments = [];

	public function __construct(
		?string $text,
		array $metadata = [],
		mixed $structured = null,
		array $attachments = [],
		?string $contentType = null,
		?string $sourceType = null,
		?string $sourceId = null
	) {
		$this->text = $text;
		$this->metadata = $metadata;
		$this->structured = $structured;
		$this->attachments = $attachments;

		$this->contentType = $contentType !== null ? trim($contentType) : null;
		$this->sourceType = $sourceType !== null ? trim($sourceType) : null;
		$this->sourceId = $sourceId !== null ? trim($sourceId) : null;
	}
}
