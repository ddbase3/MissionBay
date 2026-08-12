<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace MissionBay\Retrieval;

use AssistantFoundation\Api\IRetrievalCollectionDefinition;
use AssistantFoundation\Dto\RetrievalIndexItem;

/**
 * Generic default retrieval collection definition.
 *
 * Projects only explicitly declared context fields. Applications with a domain
 * schema are expected to replace this service at composition time.
 */
final class DefaultRetrievalCollectionDefinition implements IRetrievalCollectionDefinition {

	private const COLLECTION_KEY = 'default';

	public function getCollectionKeys(): array {
		return [self::COLLECTION_KEY];
	}

	public function getBackendCollectionName(string $collectionKey): string {
		$this->assertCollectionKey($collectionKey);
		return 'content_v2';
	}

	public function getIndexSchema(string $collectionKey): array {
		$this->assertCollectionKey($collectionKey);

		return [
			'dense' => [
				'name' => 'dense_text_v1',
				'size' => 1536,
				'distance' => 'Cosine'
			],
			'lexical' => [
				'name' => 'bm25_text_v1',
				'model' => 'qdrant/bm25',
				'source' => 'text',
				'modifier' => 'idf'
			],
			'phrase' => [
				'field' => 'text',
				'source' => 'text'
			]
		];
	}

	public function getPayloadSchema(string $collectionKey): array {
		$this->assertCollectionKey($collectionKey);

		return [
			'text' => [
				'type' => 'text',
				'index' => true,
				'params' => [
					'tokenizer' => 'word',
					'lowercase' => true,
					'phrase_matching' => true
				]
			],
			'hash' => ['type' => 'keyword', 'index' => true],
			'collection_key' => ['type' => 'keyword', 'index' => false],
			'content_uuid' => ['type' => 'keyword', 'index' => true],
			'chunktoken' => ['type' => 'keyword', 'index' => true],
			'chunk_index' => ['type' => 'integer', 'index' => true],
			'num_chunks' => ['type' => 'integer', 'index' => false]
		];
	}

	public function getAgentFilterSchema(string $collectionKey): array {
		$this->assertCollectionKey($collectionKey);

		return [
			'content_uuid' => [
				'type' => 'keyword',
				'operators' => ['eq', 'in']
			]
		];
	}

	public function getAgentContextFields(string $collectionKey): array {
		$this->assertCollectionKey($collectionKey);

		return [
			'text',
			'chunk_index',
			'num_chunks'
		];
	}

	public function getContextSchema(string $collectionKey): array {
		$this->assertCollectionKey($collectionKey);

		return [
			'group_field' => 'content_uuid',
			'position_field' => 'chunk_index'
		];
	}

	public function getPhoneticEncoderNames(string $collectionKey, array $context = []): array {
		$this->assertCollectionKey($collectionKey);
		return [];
	}

	public function validate(RetrievalIndexItem $item): void {
		$this->assertCollectionKey($item->collectionKey);

		if($item->chunkIndex < 0) {
			throw new \RuntimeException('RetrievalIndexItem.chunkIndex must be >= 0.');
		}
		if(trim($item->text) === '') {
			throw new \RuntimeException('RetrievalIndexItem.text must be non-empty.');
		}
		if(trim($item->hash) === '') {
			throw new \RuntimeException('RetrievalIndexItem.hash must be non-empty.');
		}

		$contentUuid = $item->metadata['content_uuid'] ?? null;
		if(!is_string($contentUuid) || trim($contentUuid) === '') {
			throw new \RuntimeException('RetrievalIndexItem.metadata.content_uuid is required.');
		}
	}

	public function buildPayload(RetrievalIndexItem $item): array {
		$this->validate($item);

		$payload = [
			'text' => trim($item->text),
			'hash' => trim($item->hash),
			'collection_key' => self::COLLECTION_KEY,
			'content_uuid' => trim((string)$item->metadata['content_uuid']),
			'chunktoken' => $this->buildChunkToken($item->hash, $item->chunkIndex),
			'chunk_index' => $item->chunkIndex
		];

		$numChunks = $item->metadata['num_chunks'] ?? null;
		if(is_int($numChunks) && $numChunks > 0) {
			$payload['num_chunks'] = $numChunks;
		}

		return $payload;
	}

	public function projectPayload(string $collectionKey, array $payload): array {
		$allowed = array_flip($this->getAgentContextFields($collectionKey));
		return array_intersect_key($payload, $allowed);
	}

	private function assertCollectionKey(string $collectionKey): void {
		if(trim($collectionKey) !== self::COLLECTION_KEY) {
			throw new \InvalidArgumentException("Unknown collectionKey '{$collectionKey}'.");
		}
	}

	private function buildChunkToken(string $hash, int $chunkIndex): string {
		$hash = trim($hash);
		return $chunkIndex > 0 ? $hash . '-' . $chunkIndex : $hash;
	}
}
