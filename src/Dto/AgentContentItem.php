<?php declare(strict_types=1);

namespace MissionBay\Dto;

/**
 * AgentContentItem
 *
 * Unified input DTO for the embedding pipeline.
 *
 * Produced by: IAgentContentExtractor
 * Consumed by: AiEmbeddingNode (and then parsers/chunkers depending on action)
 *
 * Key principles:
 * - Explicit control fields: action + collectionKey are FIRST-CLASS fields, never hidden in metadata.
 * - Metadata is domain data only (payload context), not workflow control.
 * - The extractor is the single source of truth for routing (collectionKey) and operation type (action).
 *
 * Mandatory fields:
 * - action: 'upsert' | 'delete'
 * - collectionKey: target vector collection key (e.g. 'lm', 'scorm', 'html', 'text_v1')
 * - id: extractor-local identifier (e.g. queue job_id), never used as vector id
 *
 * Upsert requirements:
 * - hash SHOULD be stable for the content version (e.g. sha256(uuid:etag))
 * - content MUST be available (raw bytes or text)
 *
 * Delete requirements:
 * - content may be empty
 * - hash may be empty
 * - metadata MUST include the stable delete key used by the vector store, typically:
 *   - content_uuid (string, usually hex) and optionally additional scope keys if needed
 *
 * IMPORTANT:
 * - Do not store queue control fields in metadata (job_id, attempts, locked_until, etc.).
 *   Those belong to the extractor implementation, not to the content domain.
 */
class AgentContentItem {

	/**
	 * Processing action for this item.
	 * Allowed: 'upsert' | 'delete'
	 */
	public string $action;

	/**
	 * Target collection key for vector storage operations.
	 * This is NOT the physical collection name. It is a stable key that the normalizer
	 * can map to a concrete backend collection name and schema.
	 */
	public string $collectionKey;

	/**
	 * Extractor-local identifier (e.g. queue job_id).
	 * Never use this as vector-store id.
	 */
	public string $id;

	/**
	 * Stable content hash / version marker.
	 * For upsert: should be stable for this content version.
	 * For delete: may be empty if not available.
	 */
	public string $hash;

	/**
	 * MIME-like content type (e.g. text/plain, application/pdf).
	 * Describes the raw content format, not the logical domain type.
	 */
	public string $contentType;

	/**
	 * Raw content payload.
	 * - For text: UTF-8 string
	 * - For binary: raw bytes as string
	 *
	 * NOTE:
	 * The pipeline may also use structured arrays/objects as content, depending on parsers.
	 * Keep this flexible (mixed), but treat it as "raw input" only.
	 */
	public mixed $content;

	/**
	 * True if content is binary (bytes), false if text.
	 */
	public bool $isBinary;

	/**
	 * Best-effort size in bytes (for diagnostics/limits).
	 */
	public int $size;

	/**
	 * Domain metadata only.
	 * Examples: content_uuid, title, lang, url, type_alias, created_at, updated_at, etc.
	 *
	 * Must NOT contain workflow control fields such as:
	 * - job_id, attempts, action, collectionKey, claim_token, etc.
	 */
	public array $metadata = [];

	public function __construct(
		string $action,
		string $collectionKey,
		string $id,
		string $hash,
		string $contentType,
		mixed $content,
		bool $isBinary,
		int $size,
		array $metadata = []
	) {
		$this->action = $action;
		$this->collectionKey = $collectionKey;
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

	public function isDelete(): bool {
		return strtolower($this->action) === 'delete';
	}

	public function isUpsert(): bool {
		return strtolower($this->action) === 'upsert';
	}
}
