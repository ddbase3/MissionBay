<?php declare(strict_types=1);

namespace MissionBay\Node\Ai;

use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentVectorStore;
use MissionBay\Api\IAgentContentExtractor;
use MissionBay\Api\IAgentContentParser;
use MissionBay\Api\IAgentChunker;
use AssistantFoundation\Api\IAiEmbeddingModel;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Dto\AgentContentItem;
use MissionBay\Dto\AgentParsedContent;
use MissionBay\Dto\AgentEmbeddingChunk;
use MissionBay\Node\AbstractAgentNode;

final class AiEmbeddingNode extends AbstractAgentNode {

	protected ?ILogger $logger = null;

	public static function getName(): string {
		return 'aiembeddingnode';
	}

	public function getDescription(): string {
		return 'Extraction → parsing → chunking → embedding → vector store. Node creates AgentEmbeddingChunk objects.';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'mode',
				description: 'Upsert policy for action=upsert: skip | append | replace. Delete is driven by item.action=delete.',
				type: 'string',
				default: 'skip',
				required: false
			)
		];
	}

	public function getOutputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'stats',
				description: 'Execution statistics.',
				type: 'array',
				default: [],
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message.',
				type: 'string',
				default: null,
				required: false
			)
		];
	}

	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'extractor',
				description: 'Extractors producing AgentContentItem (queue owner implements ack/fail).',
				interface: IAgentContentExtractor::class,
				maxConnections: 99,
				required: true
			),
			new AgentNodeDock(
				name: 'parser',
				description: 'Parsers producing AgentParsedContent.',
				interface: IAgentContentParser::class,
				maxConnections: 99,
				required: true
			),
			new AgentNodeDock(
				name: 'chunker',
				description: 'Chunkers producing semantic chunks.',
				interface: IAgentChunker::class,
				maxConnections: 99,
				required: true
			),
			new AgentNodeDock(
				name: 'embedder',
				description: 'Embedding model.',
				interface: IAiEmbeddingModel::class,
				maxConnections: 1,
				required: true
			),
			new AgentNodeDock(
				name: 'vectordb',
				description: 'Vector-store back-end.',
				interface: IAgentVectorStore::class,
				maxConnections: 1,
				required: true
			),
			new AgentNodeDock(
				name: 'logger',
				description: 'Optional logger.',
				interface: ILogger::class,
				maxConnections: 1,
				required: false
			)
		];
	}

	public function execute(array $inputs, array $resources, IAgentContext $context): array {
		$this->logger = $resources['logger'][0] ?? null;

		$extractors = $resources['extractor'] ?? [];
		$parsers = $resources['parser'] ?? [];
		$chunkers = $resources['chunker'] ?? [];

		usort($parsers, fn($a, $b) => $a->getPriority() <=> $b->getPriority());
		usort($chunkers, fn($a, $b) => $a->getPriority() <=> $b->getPriority());

		$embedder = $resources['embedder'][0] ?? null;
		$store = $resources['vectordb'][0] ?? null;

		if (!$embedder || !$store) {
			return ['error' => 'Missing embedder or vector store.'];
		}

		$mode = strtolower((string)($inputs['mode'] ?? 'skip'));
		if (!in_array($mode, ['skip', 'append', 'replace'], true)) {
			return ['error' => "Unsupported mode: $mode"];
		}

		$stats = [
			'mode' => $mode,
			'num_extractors' => count($extractors),

			'num_items' => 0,
			'num_items_done' => 0,
			'num_items_failed' => 0,

			'num_skipped' => 0,
			'num_deleted' => 0,

			'num_parsed' => 0,
			'num_chunks' => 0,

			'num_vectors' => 0,
			'num_vectors_skipped_empty' => 0,

			'num_store_upserts' => 0,
			'num_store_errors' => 0,

			'num_embed_errors' => 0,
			'num_parser_errors' => 0,
			'num_chunker_errors' => 0,
			'num_extractor_errors' => 0,

			'num_ack_errors' => 0,
			'num_fail_errors' => 0
		];

		$itemsWithOwner = $this->stepExtractWithOwners($extractors, $context, $stats);
		$stats['num_items'] = count($itemsWithOwner);

		foreach ($itemsWithOwner as $bundle) {
			$item = $bundle['item'];
			$owner = $bundle['owner'];

			try {
				$resultMeta = $this->processItem($item, $mode, $parsers, $chunkers, $embedder, $store, $stats);
				$this->safeAck($owner, $item, $resultMeta, $stats);
				$stats['num_items_done']++;
			} catch (\Throwable $e) {
				$stats['num_items_failed']++;
				$this->log('Item failed: ' . $e->getMessage());
				$this->safeFail($owner, $item, $e->getMessage(), true, $stats);
			}
		}

		return ['stats' => $stats];
	}

	/**
	 * @return array<int,array{owner:IAgentContentExtractor,item:AgentContentItem}>
	 */
	protected function stepExtractWithOwners(array $extractors, IAgentContext $ctx, array &$stats): array {
		$out = [];

		foreach ($extractors as $ext) {
			try {
				$list = $ext->extract($ctx);
				if (!is_array($list)) {
					continue;
				}

				foreach ($list as $item) {
					if ($item instanceof AgentContentItem) {
						$out[] = ['owner' => $ext, 'item' => $item];
					}
				}
			} catch (\Throwable $e) {
				$stats['num_extractor_errors']++;
				$this->log('Extractor failed: ' . $e->getMessage());
			}
		}

		return $out;
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function processItem(
		AgentContentItem $item,
		string $mode,
		array $parsers,
		array $chunkers,
		IAiEmbeddingModel $embedder,
		IAgentVectorStore $store,
		array &$stats
	): array {
		$action = strtolower(trim((string)$item->action));
		$collectionKey = trim((string)$item->collectionKey);

		if ($action === '') {
			$action = 'upsert';
		}
		if ($collectionKey === '') {
			$id = $item->id ?? '(no-id)';
			throw new \RuntimeException("Missing required item.collectionKey for item $id");
		}

		$hash = trim((string)$item->hash);

		// DELETE: collection scoped by API, so filter only needs domain keys
		if ($action === 'delete') {
			$uuid = $this->requireMetadataString($item, 'content_uuid');

			$deleted = $store->deleteByFilter($collectionKey, [
				'content_uuid' => $uuid
			]);

			$stats['num_deleted'] += (int)$deleted;
			$this->log("Deleted: collection=$collectionKey content_uuid=$uuid (deleted=$deleted)");

			return [
				'action' => 'delete',
				'collection_key' => $collectionKey,
				'deleted' => (int)$deleted
			];
		}

		// SKIP: duplicate detection by hash inside the same collection
		if ($mode === 'skip') {
			if ($hash !== '' && $store->existsByHash($collectionKey, $hash)) {
				$stats['num_skipped']++;
				$this->log("Skipped duplicate: collection=$collectionKey hash=$hash");
				return [
					'action' => 'skip',
					'collection_key' => $collectionKey,
					'hash' => $hash
				];
			}
		}

		// REPLACE: delete by stable key inside the same collection
		if ($mode === 'replace') {
			$uuid = $this->requireMetadataString($item, 'content_uuid');

			$deleted = $store->deleteByFilter($collectionKey, [
				'content_uuid' => $uuid
			]);

			$stats['num_deleted'] += (int)$deleted;
			$this->log("Replace: deleted collection=$collectionKey content_uuid=$uuid (deleted=$deleted)");
		}

		$parsed = $this->stepParse($parsers, $item, $stats);
		if (!$parsed) {
			return ['action' => 'upsert', 'collection_key' => $collectionKey, 'status' => 'no-parse'];
		}

		// Chunkers may return arrays. Node will create AgentEmbeddingChunk objects.
		$rawChunks = $this->stepChunk($chunkers, $parsed, $stats);
		if (!$rawChunks) {
			return ['action' => 'upsert', 'collection_key' => $collectionKey, 'status' => 'no-chunks'];
		}

		$chunks = $this->buildEmbeddingChunks($item, $parsed, $rawChunks, $collectionKey, $hash, $stats);
		if (!$chunks) {
			return ['action' => 'upsert', 'collection_key' => $collectionKey, 'status' => 'no-chunks'];
		}

		$this->stepEmbedAssign($embedder, $chunks, $stats);

		$stored = $this->stepStoreChunks($store, $chunks, $stats);

		return [
			'action' => 'upsert',
			'collection_key' => $collectionKey,
			'chunks' => count($chunks),
			'stored' => $stored
		];
	}

	protected function requireMetadataString(AgentContentItem $item, string $key): string {
		$value = $item->metadata[$key] ?? null;
		if (!is_string($value)) {
			$id = $item->id ?? '(no-id)';
			throw new \RuntimeException("Missing required item metadata '$key' for item $id");
		}
		$value = trim($value);
		if ($value === '') {
			$id = $item->id ?? '(no-id)';
			throw new \RuntimeException("Empty required item metadata '$key' for item $id");
		}
		return $value;
	}

	protected function stepParse(array $parsers, AgentContentItem $item, array &$stats): ?AgentParsedContent {
		foreach ($parsers as $parser) {
			if ($parser->supports($item)) {
				try {
					$parsed = $parser->parse($item);
					$stats['num_parsed']++;
					return $parsed;
				} catch (\Throwable $e) {
					$stats['num_parser_errors']++;
					$this->log('Parser failed: ' . $e->getMessage());
					return null;
				}
			}
		}

		$this->log('No parser supports this content.');
		return null;
	}

	/**
	 * Chunkers are allowed to return array chunks (legacy).
	 * Node will map them into AgentEmbeddingChunk.
	 *
	 * Expected chunk array format (minimum):
	 * - ['text' => '...']
	 * Optional:
	 * - ['meta' => [...]]
	 */
	protected function stepChunk(array $chunkers, AgentParsedContent $parsed, array &$stats): array {
		foreach ($chunkers as $chunker) {
			if ($chunker->supports($parsed)) {
				try {
					$chunks = $chunker->chunk($parsed);
					$chunks = is_array($chunks) ? $chunks : [];
					$stats['num_chunks'] += count($chunks);
					return $chunks;
				} catch (\Throwable $e) {
					$stats['num_chunker_errors']++;
					$this->log('Chunker failed: ' . $e->getMessage());
					return [];
				}
			}
		}

		$this->log('No chunker supports parsed content.');
		return [];
	}

	/**
	 * Builds AgentEmbeddingChunk objects from raw chunk arrays.
	 *
	 * Merge order (later wins):
	 * - item.metadata (extractor)
	 * - parsed.metadata (parser)
	 * - rawChunk.meta (chunker)
	 *
	 * Enforced:
	 * - collectionKey and hash copied from item (hash may be empty; normalizer can enforce later)
	 * - chunkIndex is deterministic by position here (0..n)
	 *
	 * @param array<int,array<string,mixed>> $rawChunks
	 * @return AgentEmbeddingChunk[]
	 */
	protected function buildEmbeddingChunks(
		AgentContentItem $item,
		AgentParsedContent $parsed,
		array $rawChunks,
		string $collectionKey,
		string $hash,
		array &$stats
	): array {
		$out = [];

		$baseMeta = [];
		if (is_array($item->metadata) && !empty($item->metadata)) {
			$baseMeta = $item->metadata;
		}
		if (is_array($parsed->metadata) && !empty($parsed->metadata)) {
			$baseMeta = array_merge($baseMeta, $parsed->metadata);
		}

		$idx = 0;
		foreach ($rawChunks as $raw) {
			if (!is_array($raw)) {
				continue;
			}

			$text = trim((string)($raw['text'] ?? ''));
			if ($text === '') {
				continue;
			}

			$meta = $baseMeta;

			$chunkMeta = $raw['meta'] ?? null;
			if (is_array($chunkMeta) && !empty($chunkMeta)) {
				$meta = array_merge($meta, $chunkMeta);
			}

			$out[] = new AgentEmbeddingChunk(
				collectionKey: $collectionKey,
				chunkIndex: $idx,
				text: $text,
				hash: $hash,
				metadata: $meta,
				vector: []
			);

			$idx++;
		}

		// num_chunks already counted as raw chunk count; keep it as-is.
		// If you want "effective chunks", add a new stat later. For now: keep stable.

		return $out;
	}

	/**
	 * Embeds in batch and assigns vectors back to the AgentEmbeddingChunk objects.
	 *
	 * @param AgentEmbeddingChunk[] $chunks
	 */
	protected function stepEmbedAssign(IAiEmbeddingModel $embedder, array &$chunks, array &$stats): void {
		$texts = [];
		$posToChunkIndex = [];

		foreach ($chunks as $i => $chunk) {
			$text = trim($chunk->text);
			if ($text === '') {
				continue;
			}
			$posToChunkIndex[] = $i;
			$texts[] = $text;
		}

		if (!$texts) {
			return;
		}

		try {
			$embeddings = $embedder->embed($texts);
		} catch (\Throwable $e) {
			$stats['num_embed_errors']++;
			$this->log('Embedding failed (batch): ' . $e->getMessage());
			return;
		}

		foreach ($posToChunkIndex as $pos => $chunkIndex) {
			$vec = $embeddings[$pos] ?? null;
			if (!is_array($vec) || !$vec) {
				$chunks[$chunkIndex]->vector = [];
				continue;
			}
			$chunks[$chunkIndex]->vector = $vec;
			$stats['num_vectors']++;
		}
	}

	/**
	 * Stores all chunks. Returns number of successful upserts.
	 *
	 * @param AgentEmbeddingChunk[] $chunks
	 */
	protected function stepStoreChunks(IAgentVectorStore $store, array $chunks, array &$stats): int {
		$stored = 0;

		foreach ($chunks as $chunk) {
			$text = trim($chunk->text);
			if ($text === '') {
				continue;
			}

			if (!$chunk->hasVector()) {
				$stats['num_vectors_skipped_empty']++;
				continue;
			}

			try {
				$store->upsert($chunk);
				$stats['num_store_upserts']++;
				$stored++;
			} catch (\Throwable $e) {
				$stats['num_store_errors']++;
				$this->log('Vector store upsert failed: ' . $e->getMessage());
			}
		}

		return $stored;
	}

	protected function safeAck(IAgentContentExtractor $ext, AgentContentItem $item, array $resultMeta, array &$stats): void {
		try {
			$ext->ack($item, $resultMeta);
		} catch (\Throwable $e) {
			$stats['num_ack_errors']++;
			$this->log('ack() failed: ' . $e->getMessage());
		}
	}

	protected function safeFail(IAgentContentExtractor $ext, AgentContentItem $item, string $msg, bool $retryHint, array &$stats): void {
		try {
			$ext->fail($item, $msg, $retryHint);
		} catch (\Throwable $e) {
			$stats['num_fail_errors']++;
			$this->log('fail() failed: ' . $e->getMessage());
		}
	}

	protected function log(string $msg): void {
		if ($this->logger) {
			$this->logger->log('AiEmbeddingNode', '[' . $this->getName() . '|' . $this->getId() . '] ' . $msg);
		}
	}
}
