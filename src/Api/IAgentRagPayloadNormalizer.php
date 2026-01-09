<?php declare(strict_types=1);

namespace MissionBay\Api;

use MissionBay\Dto\AgentEmbeddingChunk;

/**
 * IAgentRagPayloadNormalizer
 *
 * NEW contract (as agreed): multi-collection schema owner + strict validator.
 *
 * Why this exists:
 * - We must support multiple collections with different payload structures (text vs video vs audio, etc.).
 * - Routing is NOT done via metadata hacks.
 *   The extractor sets the explicit `collectionKey` on AgentContentItem, which is carried into AgentEmbeddingChunk.
 * - The VectorStore stays "dumb": it calls Normalizer to validate/build payload and then writes to the correct collection.
 *
 * Important design rules (no interpretation freedom):
 * - collectionKey is mandatory and is the ONLY routing fact.
 * - Required lifecycle keys (e.g. content_uuid) MUST be validated strictly per collection.
 * - Workflow/queue control fields MUST NOT be persisted in payload.
 *
 * Produced/used by:
 * - Used by VectorStore implementation(s) (e.g. QdrantVectorStoreAgentResource)
 * - Consumes AgentEmbeddingChunk (the unified DTO for store-stage operations)
 */
interface IAgentRagPayloadNormalizer {

        /**
         * Returns the list of supported collection keys.
         *
         * These are logical keys (stable identifiers), not necessarily the physical backend names.
         *
         * @return string[] e.g. ["xrm", "lm", "scorm", "video_v1"]
         */
        public function getCollectionKeys(): array;

        /**
         * Maps a logical collectionKey to the physical backend collection name.
         *
         * Example:
         * - collectionKey "lm" -> backend "ilias_lm_v1"
         *
         * Must throw if collectionKey is unknown.
         */
        public function getBackendCollectionName(string $collectionKey): string;

        /**
         * Returns embedding vector size for the given collectionKey.
         *
         * Must throw if collectionKey is unknown.
         */
        public function getVectorSize(string $collectionKey): int;

        /**
         * Returns the distance function for the given collectionKey.
         *
         * Typical Qdrant values:
         * - "Cosine"
         * - "Dot"
         * - "Euclid"
         *
         * Must throw if collectionKey is unknown.
         */
        public function getDistance(string $collectionKey): string;

        /**
         * Returns the payload schema for the given collectionKey.
         *
         * This schema is intended for optional payload index creation in the VectorStore.
         *
         * Must throw if collectionKey is unknown.
         *
         * @return array<string,mixed> e.g. ["hash" => ["type"=>"keyword"], ...]
         */
        public function getSchema(string $collectionKey): array;

        /**
         * Validates that the chunk is safe and complete for storage in its target collection.
         *
         * Must throw if:
         * - collectionKey is empty/unknown
         * - required lifecycle keys are missing (e.g. content_uuid)
         * - chunkIndex is invalid
         * - text is empty (for text collections) OR required structural fields are missing (for non-text collections)
         *
         * No best-effort. No silent fallback. No guessing.
         */
        public function validate(AgentEmbeddingChunk $chunk): void;

        /**
         * Builds the final payload for storage for the chunk's target collection.
         *
         * Must:
         * - call validate() internally or enforce the same rules
         * - include lifecycle keys needed for delete/replace
         * - include a stable per-chunk token (if used), deterministic for (hash + chunkIndex)
         * - exclude workflow control fields (job_id, attempts, locks, claim tokens, etc.)
         *
         * @return array<string,mixed>
         */
        public function buildPayload(AgentEmbeddingChunk $chunk): array;
}
