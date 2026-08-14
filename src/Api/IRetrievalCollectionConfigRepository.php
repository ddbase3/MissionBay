<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 **********************************************************************/

namespace MissionBay\Api;

/**
 * Stores logical retrieval collection keys and their physical backend names.
 */
interface IRetrievalCollectionConfigRepository {

	/**
	 * @return array<string,array{backend_collection:string}>
	 */
	public function getCollections(): array;

	public function hasCollection(string $collectionKey): bool;

	public function getBackendCollectionName(string $collectionKey): string;

	public function saveCollection(string $collectionKey, string $backendCollection): void;

	public function removeCollection(string $collectionKey): void;
}
