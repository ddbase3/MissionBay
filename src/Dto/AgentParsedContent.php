<?php declare(strict_types=1);

namespace MissionBay\Dto;

/**
 * AgentParsedContent
 *
 * Normalized output from any parser.
 */
class AgentParsedContent {

	/** General plain text extracted from the source */
	public string $text;

	/** Metadata extracted or forwarded by parser */
	public array $metadata = [];

	/**
	 * Optional structured representation:
	 * - Docling document
	 * - DOM tree (HTML)
	 * - JSON structure
	 * - Markdown AST
	 * - etc.
	 */
	public mixed $structured = null;

	/**
	 * Optional attachments:
	 * - extracted images
	 * - extracted tables
	 * - binary assets
	 */
	public array $attachments = [];

	public function __construct(
		string $text,
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
