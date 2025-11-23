<?php declare(strict_types=1);

namespace MissionBay\Agent;

use MissionBay\Api\IAgentRagPayloadNormalizer;

/**
 * AgentRagPayloadNormalizer
 *
 * Default implementation that flattens metadata into a unified RAG payload:
 * - text (string, full chunk text)
 * - hash (string, content hash for duplicate detection)
 * - source_id (string|null, e.g. upload-source, system, etc.)
 * - content_id (int|string|null, logical content entity id)
 * - chunk_index (int|null, position within same content)
 * - filename (string|null, original file name if available)
 * - url (string|null, reference URL for the source)
 * - allowed_user_ids (int[]: user IDs allowed to see this chunk)
 * - allowed_group_ids (int[]: group IDs allowed to see this chunk)
 * - path (int[]: node path from root to this content in a tree)
 * - extra (array: any remaining metadata fields)
 */
class AgentRagPayloadNormalizer implements IAgentRagPayloadNormalizer {

	/**
	 * @inheritDoc
	 */
	public function normalize(string $text, string $hash, array $metadata = []): array {
		// well-known keys
		$sourceId        = $metadata['source_id'] ?? null;
		$contentId       = $metadata['content_id'] ?? null;
		$chunkIndex      = $metadata['chunk_index'] ?? null;
		$filename        = $metadata['filename'] ?? null;
		$url             = $metadata['url'] ?? null;
		$allowedUsers    = $metadata['allowed_user_ids'] ?? [];
		$allowedGroups   = $metadata['allowed_group_ids'] ?? [];
		$path            = $metadata['path'] ?? [];

		// normalize array-like fields
		if (!is_array($allowedUsers) && $allowedUsers !== null) {
			$allowedUsers = [$allowedUsers];
		}
		if (!is_array($allowedGroups) && $allowedGroups !== null) {
			$allowedGroups = [$allowedGroups];
		}
		if (!is_array($path) && $path !== null) {
			$path = [$path];
		}

		// strip known keys from metadata to build "extra"
		$knownKeys = [
			'source_id',
			'content_id',
			'chunk_index',
			'filename',
			'url',
			'allowed_user_ids',
			'allowed_group_ids',
			'path'
		];

		$extra = [];
		foreach ($metadata as $k => $v) {
			if (!in_array($k, $knownKeys, true)) {
				$extra[$k] = $v;
			}
		}

		return [
			'text'              => $text,
			'hash'              => $hash,
			'source_id'         => $sourceId,
			'content_id'        => $contentId,
			'chunk_index'       => $chunkIndex,
			'filename'          => $filename,
			'url'               => $url,
			'allowed_user_ids'  => $allowedUsers,
			'allowed_group_ids' => $allowedGroups,
			'path'              => $path,
			'extra'             => $extra
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getSchema(): array {
		// Logical schema description; backend-specific mapping is done in the vector store.
		return [
			'fields' => [
				'text'              => 'string',
				'hash'              => 'string',
				'source_id'         => 'string|null',
				'content_id'        => 'int|string|null',
				'chunk_index'       => 'int|null',
				'filename'          => 'string|null',
				'url'               => 'string|null',
				'allowed_user_ids'  => 'int[]',
				'allowed_group_ids' => 'int[]',
				'path'              => 'int[]',
				'extra'             => 'mixed'
			]
		];
	}
}
