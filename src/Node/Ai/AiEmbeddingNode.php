<?php declare(strict_types=1);

namespace MissionBay\Node\Ai;

use Base3\Logger\Api\ILogger;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentResource;
use MissionBay\Api\IAgentVectorStore;
use MissionBay\Api\IAgentContentExtractor;
use MissionBay\Api\IAgentContentParser;
use MissionBay\Api\IAgentChunker;
use AssistantFoundation\Api\IAiEmbeddingModel;
use MissionBay\Agent\AgentNodeDock;
use MissionBay\Agent\AgentNodePort;
use MissionBay\Node\AbstractAgentNode;

/**
 * AiEmbeddingNode
 *
 * Full embedding pipeline:
 * - Extract content
 * - Duplicate detection (via hash + vector store)
 * - Parse content
 * - Chunk parsed text
 * - Embedding of chunks (batch)
 * - Persist vectors to vector store
 * - Optional logging
 */
class AiEmbeddingNode extends AbstractAgentNode {

	protected ?ILogger $logger = null;

	public static function getName(): string {
		return 'aiembeddingnode';
	}

	public function getDescription(): string {
		return 'Runs extraction → parsing → chunking → embedding → vector DB persist. Supports duplicate detection and priority-based parser/chunker selection.';
	}

	public function getInputDefinitions(): array {
		return [
			new AgentNodePort(
				name: 'source_id',
				description: 'Optional source identifier for metadata (e.g. document id).',
				type: 'string',
				default: null,
				required: false
			),
			new AgentNodePort(
				name: 'mode',
				description: 'Duplicate handling mode: skip | update',
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
				description: 'Detailed processing statistics.',
				type: 'array',
				default: [],
				required: false
			),
			new AgentNodePort(
				name: 'error',
				description: 'Error message if processing failed.',
				type: 'string',
				default: null,
				required: false
			)
		];
	}

	public function getDockDefinitions(): array {
		return [
			new AgentNodeDock(
				name: 'contentextractor',
				description: 'One or more extractors returning raw content.',
				interface: IAgentContentExtractor::class,
				maxConnections: 99,
				required: true
			),
			new AgentNodeDock(
				name: 'parser',
				description: 'Parsers for raw content, selected by priority.',
				interface: IAgentContentParser::class,
				maxConnections: 99,
				required: true
			),
			new AgentNodeDock(
				name: 'chunker',
				description: 'Chunkers for parsed content, selected by priority.',
				interface: IAgentChunker::class,
				maxConnections: 99,
				required: true
			),
			new AgentNodeDock(
				name: 'embedder',
				description: 'Embedding model for converting chunks to vectors.',
				interface: IAiEmbeddingModel::class,
				maxConnections: 1,
				required: true
			),
			new AgentNodeDock(
				name: 'vectordb',
				description: 'Vector store for storing embeddings.',
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

		/** @var IAgentContentExtractor[] $extractors */
		$extractors = $resources['contentextractor'] ?? [];

		/** @var IAgentContentParser[] $parsers */
		$parsers = $resources['parser'] ?? [];
		usort($parsers, fn($a, $b) => $a->getPriority() <=> $b->getPriority());

		/** @var IAgentChunker[] $chunkers */
		$chunkers = $resources['chunker'] ?? [];
		usort($chunkers, fn($a, $b) => $a->getPriority() <=> $b->getPriority());

		/** @var IAiEmbeddingModel $embedder */
		$embedder = $resources['embedder'][0] ?? null;

		/** @var IAgentVectorStore $vectorStore */
		$vectorStore = $resources['vectordb'][0] ?? null;

		if (!$embedder || !$vectorStore) {
			return ['error' => "Missing embedder or vector store."];
		}

		$mode = $inputs['mode'] ?? 'skip';
		$sourceId = $inputs['source_id'] ?? null;

		$stats = [
			'num_extractors' => count($extractors),
			'num_raw_items' => 0,
			'num_skipped_duplicates' => 0,
			'num_parsed' => 0,
			'num_chunks' => 0,
			'num_vectors' => 0
		];

		$rawItems = $this->stepExtract($extractors, $context, $stats);
		$results = [];

		foreach ($rawItems as $item) {
			$hash = $this->makeHash($item);

			if ($vectorStore->existsByHash($hash) && $mode === 'skip') {
				$this->log("Duplicate skipped: $hash");
				$stats['num_skipped_duplicates']++;
				continue;
			}

			$parsed = $this->stepParse($parsers, $item, $stats);
			if ($parsed === null) continue;

			$chunks = $this->stepChunk($chunkers, $parsed, $stats);
			if (empty($chunks)) continue;

			$vectors = $this->stepEmbed($embedder, $chunks, $stats);

			$this->stepStore($vectorStore, $chunks, $vectors, $hash, $sourceId);
		}

		return [
			'stats' => $stats
		];
	}

	// ---------------------------------------------------------
	// Steps
	// ---------------------------------------------------------

	private function stepExtract(array $extractors, IAgentContext $ctx, array &$stats): array {
		$out = [];

		foreach ($extractors as $ext) {
			try {
				$list = $ext->extract($ctx);
				$stats['num_raw_items'] += count($list);
				$out = array_merge($out, $list);
			} catch (\Throwable $e) {
				$this->log("Extractor failed: " . $e->getMessage());
			}
		}
		return $out;
	}

	private function stepParse(array $parsers, mixed $content, array &$stats): ?array {
		foreach ($parsers as $parser) {
			if ($parser->supports($content)) {
				try {
					$parsed = $parser->parse($content);
					$stats['num_parsed']++;
					return $parsed;
				} catch (\Throwable $e) {
					$this->log("Parser failed: " . $e->getMessage());
					return null;
				}
			}
		}
		$this->log("No parser supports this content.");
		return null;
	}

	private function stepChunk(array $chunkers, array $parsed, array &$stats): array {
		foreach ($chunkers as $chunker) {
			if ($chunker->supports($parsed)) {
				try {
					$chunks = $chunker->chunk($parsed);
					$stats['num_chunks'] += count($chunks);
					return $chunks;
				} catch (\Throwable $e) {
					$this->log("Chunker failed: " . $e->getMessage());
					return [];
				}
			}
		}
		$this->log("No chunker supports parsed content.");
		return [];
	}

	private function stepEmbed(IAiEmbeddingModel $embedder, array $chunks, array &$stats): array {
		$texts = array_map(fn($c) => $c['text'] ?? '', $chunks);

		try {
			$vectors = $embedder->embed($texts);
			$stats['num_vectors'] += count($vectors);
			return $vectors;
		} catch (\Throwable $e) {
			$this->log("Embedding failed: " . $e->getMessage());
			return [];
		}
	}

	private function stepStore(
		IAgentVectorStore $store,
		array $chunks,
		array $vectors,
		string $hash,
		?string $sourceId
	): void {
		foreach ($chunks as $i => $chunk) {
			$id = $chunk['id'] ?? uniqid('chunk_', true);
			$vector = $vectors[$i] ?? [];

			$meta = [
				'hash' => $hash,
				'source_id' => $sourceId,
				'chunk_index' => $i,
				'metadata' => $chunk['meta'] ?? []
			];

			try {
				$store->upsert($id, $vector, $meta);
			} catch (\Throwable $e) {
				$this->log("Vector store upsert failed: " . $e->getMessage());
			}
		}
	}

	// ---------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------

	private function makeHash(mixed $content): string {
		return hash('sha256', json_encode($content));
	}

	protected function log(string $msg): void {
		if (!$this->logger) return;
		$full = '[' . $this->getName() . '|' . $this->getId() . '] ' . $msg;
		$this->logger->log('AiEmbeddingNode', $full);
	}
}
