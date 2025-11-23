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
use MissionBay\Node\AbstractAgentNode;

class AiEmbeddingNode extends AbstractAgentNode {

        protected ?ILogger $logger = null;

        public static function getName(): string {
                return 'aiembeddingnode';
        }

        public function getDescription(): string {
                return 'Extraction → parsing → chunking → embedding → vector store. DTO-based pipeline.';
        }

        public function getInputDefinitions(): array {
                return [
                        new AgentNodePort(
                                name: 'source_id',
                                description: 'Optional source identifier.',
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
                                description: 'Extractors producing AgentContentItem.',
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
                $parsers    = $resources['parser'] ?? [];
                $chunkers   = $resources['chunker'] ?? [];

                usort($parsers, fn($a, $b) => $a->getPriority() <=> $b->getPriority());
                usort($chunkers, fn($a, $b) => $a->getPriority() <=> $b->getPriority());

                /** @var IAiEmbeddingModel|null $embedder */
                $embedder = $resources['embedder'][0] ?? null;

                /** @var IAgentVectorStore|null $vectorStore */
                $vectorStore = $resources['vectordb'][0] ?? null;

                if (!$embedder || !$vectorStore) {
                        return ['error' => 'Missing embedder or vector store.'];
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

                /** @var AgentContentItem[] $rawItems */
                $rawItems = $this->stepExtract($extractors, $context, $stats);

                foreach ($rawItems as $item) {

                        $hash = $item->hash;

                        // Duplicate detection
                        if ($vectorStore->existsByHash($hash) && $mode === 'skip') {
                                $this->log("Duplicate skipped: $hash");
                                $stats['num_skipped_duplicates']++;
                                continue;
                        }

                        // PARSE
                        $parsed = $this->stepParse($parsers, $item, $stats);
                        if (!$parsed) continue;

                        // CHUNK
                        $chunks = $this->stepChunk($chunkers, $parsed, $stats);
                        if (!$chunks) continue;

                        // EMBED
                        $vectors = $this->stepEmbed($embedder, $chunks, $stats);

                        // STORE
                        $this->stepStore($vectorStore, $chunks, $vectors, $hash, $sourceId);
                }

                $this->log("Embedding done: " . $stats['num_chunks'] . " Chunks.");

                return ['stats' => $stats];
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
                                $this->log('Extractor failed: ' . $e->getMessage());
                        }
                }

                return $out;
        }

        private function stepParse(array $parsers, AgentContentItem $item, array &$stats): ?AgentParsedContent {
                foreach ($parsers as $parser) {
                        if ($parser->supports($item)) {
                                try {
                                        $parsed = $parser->parse($item);
                                        $stats['num_parsed']++;
                                        return $parsed;
                                } catch (\Throwable $e) {
                                        $this->log('Parser failed: ' . $e->getMessage());
                                        return null;
                                }
                        }
                }

                $this->log('No parser supports this content.');
                return null;
        }

        private function stepChunk(array $chunkers, AgentParsedContent $parsed, array &$stats): array {
                foreach ($chunkers as $chunker) {
                        if ($chunker->supports($parsed)) {
                                try {
                                        $chunks = $chunker->chunk($parsed);
                                        $stats['num_chunks'] += count($chunks);
                                        return $chunks;
                                } catch (\Throwable $e) {
                                        $this->log('Chunker failed: ' . $e->getMessage());
                                        return [];
                                }
                        }
                }

                $this->log('No chunker supports parsed content.');
                return [];
        }

        private function stepEmbed(IAiEmbeddingModel $embedder, array $chunks, array &$stats): array {
                $vectors = [];

                foreach ($chunks as $i => $chunk) {

                        $text = (string)($chunk['text'] ?? '');

                        try {
                                $result = $embedder->embed([$text]);
                                $vectors[$i] = $result[0] ?? [];

                                if (!empty($vectors[$i])) {
                                        $stats['num_vectors']++;
                                }

                        } catch (\Throwable $e) {
                                $this->log("Embedding failed (chunk $i): " . $e->getMessage());
                                $vectors[$i] = [];
                        }
                }

                return $vectors;
        }

        private function stepStore(
                IAgentVectorStore $store,
                array $chunks,
                array $vectors,
                string $hash,
                ?string $sourceId
        ): void {
                foreach ($chunks as $i => $chunk) {

                        $id   = $chunk['id'] ?? uniqid('chunk_', true);
                        $text = (string)($chunk['text'] ?? '');

                        // flatten payload
                        $meta = [
                                'hash'        => $hash,
                                'source_id'   => $sourceId,
                                'chunk_index' => $i,
                                'text'        => $text
                        ];

                        // flatten additional metadata (filename, page, etc.)
                        foreach (($chunk['meta'] ?? []) as $k => $v) {
                                $meta[$k] = $v;
                        }

                        try {
                                $store->upsert($id, $vectors[$i] ?? [], $text, $meta);
                        } catch (\Throwable $e) {
                                $this->log('Vector store upsert failed: ' . $e->getMessage());
                        }
                }
        }

        protected function log(string $msg): void {
                if ($this->logger) {
                        $this->logger->log('AiEmbeddingNode', '['.$this->getName().'|'.$this->getId().'] '.$msg);
                }
        }
}
