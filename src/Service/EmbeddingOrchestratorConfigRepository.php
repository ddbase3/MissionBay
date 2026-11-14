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

namespace Base3\MissionBay\Service;

use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MissionBay\Api\IEmbeddingOrchestratorConfigRepository;

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
