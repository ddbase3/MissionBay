<?php declare(strict_types=1);

namespace MissionBay\Agent;

use MissionBay\Api\IAgentRagPayloadNormalizer;

/**
 * AgentRagPayloadNormalizer
 *
 * Flat and strict payload builder for RAG vector stores.
 */
class AgentRagPayloadNormalizer implements IAgentRagPayloadNormalizer {

	/**
	 * @inheritDoc
	 */
	public function normalize(string $text, string $hash, array $metadata = []): array {
		$sourceId      = $metadata['source_id'] ?? null;
		$contentId     = $metadata['content_id'] ?? null;
		$contentUuid   = $metadata['content_uuid'] ?? null;
		$chunkIndex    = $metadata['chunk_index'] ?? 0;
		$filename      = $metadata['filename'] ?? null;
		$url           = $metadata['url'] ?? null;

		$allowedUsers  = $this->ensureArray($metadata['allowed_user_ids'] ?? []);
		$allowedGroups = $this->ensureArray($metadata['allowed_group_ids'] ?? []);
		$path          = $this->ensureArray($metadata['path'] ?? []);

		$section       = $metadata['section'] ?? null;
		$parentId      = $metadata['parent_id'] ?? null;
		$doctype       = $metadata['doctype'] ?? null;
		$lang          = $metadata['lang'] ?? null;
		$createdAt     = $metadata['created_at'] ?? null;
		$updatedAt     = $metadata['updated_at'] ?? null;

		$name          = $metadata['name'] ?? null;

		$chunktoken    = $metadata['chunktoken'] 
			?? ($chunkIndex !== null ? "{$hash}-{$chunkIndex}" : $hash);

		$known = [
			'text','hash','source_id','source_uuid','content_id','chunk_index','filename','url',
			'allowed_user_ids','allowed_group_ids','path','section','parent_id',
			'doctype','lang','created_at','updated_at','chunktoken','name'
		];

		$extra = $this->collectExtra($metadata, $known);

		return [
			'text'              => $text,
			'hash'              => $hash,
			'source_id'         => $sourceId,
			'content_id'        => $contentId,
			'content_uuid'      => $contentUuid,
			'chunktoken'        => $chunktoken,
			'chunk_index'       => $chunkIndex,
			'filename'          => $filename,
			'url'               => $url,
			'allowed_user_ids'  => $allowedUsers,
			'allowed_group_ids' => $allowedGroups,
			'path'              => $path,
			'section'           => $section,
			'parent_id'         => $parentId,
			'doctype'           => $doctype,
			'lang'              => $lang,
			'created_at'        => $createdAt,
			'updated_at'        => $updatedAt,
			'name'              => $name,
			'extra'             => $extra
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getSchema(): array {
		return [
			'text'              => [ 'type' => 'text' ],
			'hash'              => [ 'type' => 'keyword' ],
			'source_id'         => [ 'type' => 'keyword' ],
			'content_id'        => [ 'type' => 'keyword' ],
			'content_uuid'      => [ 'type' => 'keyword' ],
			'chunktoken'        => [ 'type' => 'keyword' ],
			'chunk_index'       => [ 'type' => 'integer' ],
			'filename'          => [ 'type' => 'keyword' ],
			'url'               => [ 'type' => 'keyword' ],
			'allowed_user_ids'  => [ 'type' => 'integer' ],
			'allowed_group_ids' => [ 'type' => 'integer' ],
			'path'              => [ 'type' => 'integer' ],
			'section'           => [ 'type' => 'keyword' ],
			'parent_id'         => [ 'type' => 'keyword' ],
			'doctype'           => [ 'type' => 'keyword' ],
			'lang'              => [ 'type' => 'keyword' ],
			'created_at'        => [ 'type' => 'keyword' ],
			'updated_at'        => [ 'type' => 'keyword' ],
			'name'              => [ 'type' => 'keyword' ]
		];
	}

	/**
	 * Ensures the value is an array.
	 */
	protected function ensureArray(mixed $value): array {
		if ($value === null) {
			return [];
		}
		return is_array($value) ? $value : [$value];
	}

	/**
	 * Extracts all unknown metadata fields into an extra map.
	 */
	protected function collectExtra(array $metadata, array $knownKeys): array {
		$extra = [];
		foreach ($metadata as $k => $v) {
			if (!in_array($k, $knownKeys, true)) {
				$extra[$k] = $v;
			}
		}
		return $extra;
	}
}
