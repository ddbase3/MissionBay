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

	private const META_CONTENT_UUID = 'content_uuid';

	protected ?ILogger $logger = null;

	private bool $debugEnabled = false;
	private int $debugMaxTextPreview = 180;
	private int $debugMaxMetaKeys = 60;

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
			),
			new AgentNodePort(
				name: 'debug',
				description: 'Enable verbose CLI output (echo inside log()).',
				type: 'bool',
				default: false,
				required: false
			),
			new AgentNodePort(
				name: 'debug_preview_len',
				description: 'Max characters for text previews in debug output.',
				type: 'int',
				default: 180,
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

		$this->debugEnabled = (bool)($inputs['debug'] ?? false);
		$this->debugMaxTextPreview = max(20, (int)($inputs['debug_preview_len'] ?? $this->debugMaxTextPreview));

		$extractors = $resources['extractor'] ?? [];
		$parsers = $resources['parser'] ?? [];
		$chunkers = $resources['chunker'] ?? [];

		usort($parsers, fn($a, $b) => $a->getPriority() <=> $b->getPriority());
		usort($chunkers, fn($a, $b) => $a->getPriority() <=> $b->getPriority());

		$embedder = $resources['embedder'][0] ?? null;
		$store = $resources['vectordb'][0] ?? null;

		if (!$embedder || !$store) {
			$this->log('ERROR Missing embedder or vector store.');
			return ['error' => 'Missing embedder or vector store.'];
		}

		$mode = strtolower((string)($inputs['mode'] ?? 'skip'));
		if (!in_array($mode, ['skip', 'append', 'replace'], true)) {
			$this->log('ERROR Unsupported mode: ' . $mode);
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

		$this->log('Start mode=' . $mode . ' extractors=' . count($extractors) . ' parsers=' . count($parsers) . ' chunkers=' . count($chunkers));

		$itemsWithOwner = $this->stepExtractWithOwners($extractors, $context, $stats);
		$stats['num_items'] = count($itemsWithOwner);

		$this->log('Extract items=' . $stats['num_items']);

		$idx = 0;
		foreach ($itemsWithOwner as $bundle) {
			$idx++;
			$item = $bundle['item'];
			$owner = $bundle['owner'];

			$this->log('Item #' . $idx . ' id=' . (string)$item->id . ' action=' . (string)$item->action . ' col=' . (string)$item->collectionKey . ' hash=' . (string)$item->hash);

			try {
				$resultMeta = $this->processItem($item, $mode, $parsers, $chunkers, $embedder, $store, $stats);

				$this->assertItemSuccessOrThrow($item, $resultMeta);

				$this->safeAck($owner, $item, $resultMeta, $stats);
				$stats['num_items_done']++;

				$this->log('Item #' . $idx . ' ok action=' . (string)($resultMeta['action'] ?? '') . ' stored=' . (string)($resultMeta['stored'] ?? '') . ' deleted=' . (string)($resultMeta['deleted'] ?? '') . ' status=' . (string)($resultMeta['status'] ?? ''));
			} catch (\Throwable $e) {
				$stats['num_items_failed']++;
				$this->log('Item #' . $idx . ' FAIL ' . $e->getMessage());
				$this->safeFail($owner, $item, $e->getMessage(), true, $stats);
			}
		}

		$this->log('Done ' . $this->safeJson($stats));

		return ['stats' => $stats];
	}

	/**
	 * Hard rule:
	 * - Only ACK if the item was actually processed successfully.
	 * - For upsert, success means stored > 0.
	 * - For delete, success means the delete call did not throw.
	 * - For skip, success means it was intentionally skipped.
	 *
	 * @param array<string,mixed> $resultMeta
	 */
	private function assertItemSuccessOrThrow(AgentContentItem $item, array $resultMeta): void {
		$action = (string)($resultMeta['action'] ?? '');
		if ($action === 'delete' || $action === 'skip') {
			return;
		}

		$stored = (int)($resultMeta['stored'] ?? 0);
		if ($stored > 0) {
			return;
		}

		$status = (string)($resultMeta['status'] ?? '');
		$id = (string)($item->id ?? '(no-id)');
		$col = (string)($item->collectionKey ?? '');
		$hash = (string)($item->hash ?? '');

		$msg = 'Upsert failed (stored=0)';
		if ($status !== '') {
			$msg .= ' status=' . $status;
		}
		$msg .= ' id=' . $id . ' col=' . $col . ' hash=' . $hash;

		throw new \RuntimeException($msg);
	}

	/**
	 * @return array<int,array{owner:IAgentContentExtractor,item:AgentContentItem}>
	 */
	protected function stepExtractWithOwners(array $extractors, IAgentContext $ctx, array &$stats): array {
		$out = [];
		$extIdx = 0;

		foreach ($extractors as $ext) {
			$extIdx++;

			try {
				$list = $ext->extract($ctx);
				$count = is_array($list) ? count($list) : 0;

				$this->log('Extractor #' . $extIdx . ' ' . get_class($ext) . ' items=' . $count);

				foreach ($list as $it) {
					if ($it instanceof AgentContentItem) {
						$out[] = ['owner' => $ext, 'item' => $it];
					}
				}
			} catch (\Throwable $e) {
				$stats['num_extractor_errors']++;
				$this->log('Extractor #' . $extIdx . ' ERROR ' . $e->getMessage());
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

		if ($action === 'delete') {
			return $this->processDeleteItem($item, $collectionKey, $store, $stats);
		}

		return $this->processUpsertItem($item, $collectionKey, $mode, $parsers, $chunkers, $embedder, $store, $stats);
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function processDeleteItem(
		AgentContentItem $item,
		string $collectionKey,
		IAgentVectorStore $store,
		array &$stats
	): array {
		$uuid = $this->requireMetadataString($item, self::META_CONTENT_UUID);

		$deleted = $store->deleteByFilter($collectionKey, [
			self::META_CONTENT_UUID => $uuid
		]);

		$stats['num_deleted'] += (int)$deleted;

		$this->log('Delete ok col=' . $collectionKey . ' content_uuid=' . $uuid . ' deleted=' . (string)$deleted);

		return [
			'action' => 'delete',
			'collection_key' => $collectionKey,
			'deleted' => (int)$deleted
		];
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function processUpsertItem(
		AgentContentItem $item,
		string $collectionKey,
		string $mode,
		array $parsers,
		array $chunkers,
		IAiEmbeddingModel $embedder,
		IAgentVectorStore $store,
		array &$stats
	): array {
		$hash = trim((string)$item->hash);

		if ($mode === 'skip' && $hash !== '') {
			if ($store->existsByHash($collectionKey, $hash)) {
				$stats['num_skipped']++;
				$this->log('Skip duplicate col=' . $collectionKey . ' hash=' . $hash);

				return [
					'action' => 'skip',
					'collection_key' => $collectionKey,
					'hash' => $hash
				];
			}
		}

		if ($mode === 'replace') {
			$uuid = $this->requireMetadataString($item, self::META_CONTENT_UUID);

			$deleted = $store->deleteByFilter($collectionKey, [
				self::META_CONTENT_UUID => $uuid
			]);

			$stats['num_deleted'] += (int)$deleted;
			$this->log('Replace delete ok col=' . $collectionKey . ' content_uuid=' . $uuid . ' deleted=' . (string)$deleted);
		}

		$parsed = $this->stepParse($parsers, $item, $stats);
		if (!$parsed) {
			return ['action' => 'upsert', 'collection_key' => $collectionKey, 'stored' => 0, 'status' => 'no-parse'];
		}

		$rawChunks = $this->stepChunk($chunkers, $parsed, $stats);
		if (!$rawChunks) {
			return ['action' => 'upsert', 'collection_key' => $collectionKey, 'stored' => 0, 'status' => 'no-chunks'];
		}

		$chunks = $this->buildEmbeddingChunks($item, $parsed, $rawChunks, $collectionKey, $hash);
		if (!$chunks) {
			return ['action' => 'upsert', 'collection_key' => $collectionKey, 'stored' => 0, 'status' => 'no-chunks'];
		}

		$this->stepEmbedAssign($embedder, $chunks, $stats);

		$stored = $this->stepStoreChunks($store, $chunks, $stats);

		return [
			'action' => 'upsert',
			'collection_key' => $collectionKey,
			'chunks' => count($chunks),
			'stored' => $stored,
			'status' => $stored > 0 ? 'ok' : 'store-failed'
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
			try {
				if (!$parser->supports($item)) {
					continue;
				}
			} catch (\Throwable $e) {
				$stats['num_parser_errors']++;
				$this->log('Parse supports ERROR ' . get_class($parser) . ' ' . $e->getMessage());
				continue;
			}

			try {
				$parsed = $parser->parse($item);
				$stats['num_parsed']++;
				$this->log('Parse ok parser=' . get_class($parser));
				return $parsed;
			} catch (\Throwable $e) {
				$stats['num_parser_errors']++;
				$msg = 'Parse ERROR ' . get_class($parser) . ': ' . $e->getMessage();
				$this->log($msg);
				throw new \RuntimeException($msg, 0, $e);
			}
		}

		$this->log('Parse none');
		return null;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	protected function stepChunk(array $chunkers, AgentParsedContent $parsed, array &$stats): array {
		foreach ($chunkers as $chunker) {
			try {
				if (!$chunker->supports($parsed)) {
					continue;
				}
			} catch (\Throwable $e) {
				$stats['num_chunker_errors']++;
				$this->log('Chunk supports ERROR ' . get_class($chunker) . ' ' . $e->getMessage());
				continue;
			}

			try {
				$chunks = $chunker->chunk($parsed);
				$chunks = is_array($chunks) ? $chunks : [];
				$stats['num_chunks'] += count($chunks);

				$this->log('Chunk ok chunker=' . get_class($chunker) . ' chunks=' . count($chunks));
				return $chunks;
			} catch (\Throwable $e) {
				$stats['num_chunker_errors']++;
				$msg = 'Chunk ERROR ' . get_class($chunker) . ': ' . $e->getMessage();
				$this->log($msg);
				throw new \RuntimeException($msg, 0, $e);
			}
		}

		$this->log('Chunk none');
		return [];
	}

	/**
	 * @param array<int,array<string,mixed>> $rawChunks
	 * @return AgentEmbeddingChunk[]
	 */
	protected function buildEmbeddingChunks(
		AgentContentItem $item,
		AgentParsedContent $parsed,
		array $rawChunks,
		string $collectionKey,
		string $hash
	): array {
		$baseMeta = is_array($item->metadata) ? $item->metadata : [];
		$parsedMeta = is_array($parsed->metadata ?? null) ? $parsed->metadata : [];
		if ($parsedMeta) {
			$baseMeta = array_merge($baseMeta, $parsedMeta);
		}

		$normalized = [];

		foreach ($rawChunks as $raw) {
			$text = trim((string)($raw['text'] ?? ''));
			if ($text === '') {
				continue;
			}

			$chunkMeta = $raw['meta'] ?? null;
			$normalized[] = [
				'text' => $text,
				'meta' => is_array($chunkMeta) ? $chunkMeta : []
			];
		}

		$numChunks = count($normalized);
		if ($numChunks === 0) {
			$this->log('Chunks built=0');
			return [];
		}

		$out = [];
		$chunkIndex = 0;

		foreach ($normalized as $n) {
			$meta = $baseMeta;

			if (!empty($n['meta'])) {
				$meta = array_merge($meta, $n['meta']);
			}

			$meta['num_chunks'] = $numChunks;

			$out[] = new AgentEmbeddingChunk(
				collectionKey: $collectionKey,
				chunkIndex: $chunkIndex,
				text: $n['text'],
				hash: $hash,
				metadata: $meta,
				vector: []
			);

			$chunkIndex++;
		}

		$this->log('Chunks built=' . count($out));

		return $out;
	}

	/**
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

		$this->log('Embed texts=' . count($texts));

		try {
			$embeddings = $embedder->embed($texts);
		} catch (\Throwable $e) {
			$stats['num_embed_errors']++;
			$this->log('Embed ERROR ' . $e->getMessage());
			return;
		}

		$this->log('Embed vectors=' . (is_array($embeddings) ? count($embeddings) : 0));

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
	 * @param AgentEmbeddingChunk[] $chunks
	 */
	protected function stepStoreChunks(IAgentVectorStore $store, array $chunks, array &$stats): int {
		$stored = 0;

		foreach ($chunks as $chunk) {
			if (trim($chunk->text) === '') {
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
				$this->log('Store ERROR ' . $e->getMessage());
			}
		}

		$this->log('Store stored=' . $stored . ' errors=' . (int)$stats['num_store_errors']);

		return $stored;
	}

	protected function safeAck(IAgentContentExtractor $ext, AgentContentItem $item, array $resultMeta, array &$stats): void {
		try {
			$ext->ack($item, $resultMeta);
		} catch (\Throwable $e) {
			$stats['num_ack_errors']++;
			$this->log('ACK ERROR ' . $e->getMessage());
		}
	}

	protected function safeFail(IAgentContentExtractor $ext, AgentContentItem $item, string $msg, bool $retryHint, array &$stats): void {
		try {
			$ext->fail($item, $msg, $retryHint);
		} catch (\Throwable $e) {
			$stats['num_fail_errors']++;
			$this->log('FAIL ERROR ' . $e->getMessage());
		}
	}

	protected function log(string $msg): void {
		if ($this->logger) {
			$this->logger->log('AiEmbeddingNode', '[' . $this->getName() . '|' . $this->getId() . '] ' . $msg);
		}

		if ($this->debugEnabled) {
			echo '- ' . $msg . "\n";
		}
	}

	private function safeJson($value): string {
		try {
			$json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
			return is_string($json) ? $json : '(json-encode-failed)';
		} catch (\Throwable) {
			return '(json-encode-error)';
		}
	}
}
