<?php declare(strict_types=1);

namespace MissionBay\Dto;

/**
 * AgentParsedContent
 *
 * Normalized output from any parser.
 */
class AgentParsedContent {

	/** Plain text extracted from the source (may be null before chunking) */
	public ?string $text = null;

	/** Metadata extracted or forwarded by parser */
	public array $metadata = [];

	/** Optional structured representation (JSON, docling, DOM, etc.) */
	public mixed $structured = null;

	/** Optional attachments (images, tables, binary assets) */
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
