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

namespace Base3\MissionBay\Api;

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
