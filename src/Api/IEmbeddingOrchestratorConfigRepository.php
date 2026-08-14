<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Api;

/**
 * Stores the active embedding-orchestrator composition.
 */
interface IEmbeddingOrchestratorConfigRepository {

	/**
	 * @return array{embedding_preset:string,vector_store_preset:string,collection_key:string}
	 */
	public function getConfig(): array;

	public function saveConfig(string $embeddingPreset, string $vectorStorePreset, string $collectionKey): void;
}
