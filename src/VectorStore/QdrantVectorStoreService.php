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

namespace MissionBay\VectorStore;

final class QdrantVectorStoreService extends AbstractQdrantVectorStoreService {

	public static function getName(): string {
		return 'qdrantvectorstoreservice';
	}

	protected function buildUrl(string $path): string {
		$path = '/' . ltrim(trim($path), '/');

		return $this->getBaseUrl() . $path;
	}

	protected function buildHeaders(): array {
		$authHeaderName = $this->getStringOption('auth_header_name', 'api-key');

		return [
			'Content-Type: application/json',
			$authHeaderName . ': ' . $this->getAuthSecret()
		];
	}
}
