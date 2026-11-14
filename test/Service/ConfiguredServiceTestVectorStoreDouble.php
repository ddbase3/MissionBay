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

namespace Base3\MissionBay\Test\Service;

use Base3\AssistantFoundation\Api\IRetrievalCollectionDefinition;
use Base3\MissionBay\VectorStore\AbstractQdrantVectorStoreService;

final class ConfiguredServiceTestVectorStoreDouble extends AbstractQdrantVectorStoreService {

	/** @param array<string,mixed> $result */
	public function __construct(IRetrievalCollectionDefinition $definition, private readonly array $result) {
		parent::__construct($definition);
	}

	public static function getName(): string {
		return 'configuredservicetestvectorstoredouble';
	}

	/** @param array<string,mixed> $config @return array<string,mixed> */
	public function test(array $config): array {
		return $this->result;
	}

	protected function buildUrl(string $path): string {
		return 'https://example.invalid' . $path;
	}

	protected function buildHeaders(): array {
		return [];
	}
}
