<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Service;

use Base3\Settings\Api\ISettingsStore;
use MissionBay\Api\IRetrievalCollectionConfigRepository;

final class RetrievalCollectionConfigRepository implements IRetrievalCollectionConfigRepository {

	private const GROUP = 'retrieval-collection';

	public function __construct(private readonly ISettingsStore $settingsStore) {}

	public function getCollections(): array {
		$records = $this->settingsStore->getGroup(self::GROUP);
		$result = [];

		foreach($records as $collectionKey => $settings) {
			if(!is_string($collectionKey) || !is_array($settings)) {
				continue;
			}

			$collectionKey = $this->normalizeKey($collectionKey);
			$backendCollection = trim((string)($settings['backend_collection'] ?? ''));

			if($collectionKey === '' || $backendCollection === '') {
				continue;
			}

			$result[$collectionKey] = [
				'backend_collection' => $backendCollection
			];
		}

		ksort($result, SORT_NATURAL | SORT_FLAG_CASE);
		return $result;
	}

	public function hasCollection(string $collectionKey): bool {
		$collectionKey = $this->normalizeKey($collectionKey);
		return $collectionKey !== '' && isset($this->getCollections()[$collectionKey]);
	}

	public function getBackendCollectionName(string $collectionKey): string {
		$collectionKey = $this->normalizeKey($collectionKey);
		$collections = $this->getCollections();
		$backendCollection = trim((string)($collections[$collectionKey]['backend_collection'] ?? ''));

		if($collectionKey === '' || $backendCollection === '') {
			throw new \InvalidArgumentException('Unknown retrieval collection key: ' . $collectionKey);
		}

		return $backendCollection;
	}

	public function saveCollection(string $collectionKey, string $backendCollection): void {
		$collectionKey = $this->normalizeKey($collectionKey);
		$backendCollection = trim($backendCollection);

		if($collectionKey === '') {
			throw new \InvalidArgumentException('Collection key must not be empty.');
		}
		if($backendCollection === '') {
			throw new \InvalidArgumentException('Backend collection name must not be empty.');
		}

		foreach($this->getCollections() as $existingKey => $settings) {
			if($existingKey === $collectionKey) {
				continue;
			}
			if((string)($settings['backend_collection'] ?? '') === $backendCollection) {
				throw new \InvalidArgumentException('Backend collection is already assigned to key: ' . $existingKey);
			}
		}

		$this->settingsStore->set(self::GROUP, $collectionKey, [
			'backend_collection' => $backendCollection
		]);
		$this->settingsStore->save();
	}

	public function removeCollection(string $collectionKey): void {
		$collectionKey = $this->normalizeKey($collectionKey);
		if($collectionKey === '') {
			return;
		}

		$this->settingsStore->remove(self::GROUP, $collectionKey);
		$this->settingsStore->save();
	}

	private function normalizeKey(string $value): string {
		$value = strtolower(trim($value));
		return preg_replace('/[^a-z0-9._-]+/', '', $value) ?? '';
	}
}
