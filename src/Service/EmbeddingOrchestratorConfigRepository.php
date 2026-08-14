<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Service;

use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IEmbeddingOrchestratorConfigRepository;

final class EmbeddingOrchestratorConfigRepository implements IEmbeddingOrchestratorConfigRepository {

	private const GROUP = 'embedding-orchestrator';
	private const NAME = 'default';

	public function __construct(private readonly ISettingsStore $settingsStore) {}

	public function getConfig(): array {
		$settings = $this->settingsStore->get(self::GROUP, self::NAME, []);

		return [
			'embedding_preset' => $this->normalizeKey((string)($settings['embedding_preset'] ?? '')),
			'vector_store_preset' => $this->normalizeKey((string)($settings['vector_store_preset'] ?? '')),
			'collection_key' => $this->normalizeKey((string)($settings['collection_key'] ?? ''))
		];
	}

	public function saveConfig(string $embeddingPreset, string $vectorStorePreset, string $collectionKey): void {
		$embeddingPreset = $this->normalizeKey($embeddingPreset);
		$vectorStorePreset = $this->normalizeKey($vectorStorePreset);
		$collectionKey = $this->normalizeKey($collectionKey);

		if($embeddingPreset === '' || $vectorStorePreset === '' || $collectionKey === '') {
			throw new \InvalidArgumentException('Embedding preset, vector-store preset and collection key are required.');
		}

		$this->settingsStore->set(self::GROUP, self::NAME, [
			'embedding_preset' => $embeddingPreset,
			'vector_store_preset' => $vectorStorePreset,
			'collection_key' => $collectionKey
		]);
		$this->settingsStore->save();
	}

	private function normalizeKey(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}
}
