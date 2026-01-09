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
 * Typical usage:
 * - For structured DB entities: parser may set structured to an array/object and leave text empty.
 * - For documents: parser may set text to extracted content and optionally structured to a richer form.
 *
 * Metadata rules:
 * - metadata remains domain metadata (not workflow control).
 * - Any workflow control (action/collectionKey) stays on AgentContentItem, not here.
 */
class AgentParsedContent {

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
		array $attachments = []
	) {
		$this->text = $text;
		$this->metadata = $metadata;
		$this->structured = $structured;
		$this->attachments = $attachments;
	}
}
