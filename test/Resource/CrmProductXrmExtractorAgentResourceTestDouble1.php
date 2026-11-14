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

namespace Base3\MissionBay\Test\Resource;

use PHPUnit\Framework\TestCase;
use Base3\MissionBay\Resource\CrmProductXrmExtractorAgentResource;
use Base3\MissionBay\Api\IAgentContentExtractor;
use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\MissionBay\Dto\AgentContentItem;
use Base3\ResourceFoundation\Api\IEntityDataService;

final class CrmProductXrmExtractorAgentResourceTestDouble1 implements IEntityDataService {

			private array $entries;
			private array $lastOptionsRef;

			public function __construct(array $entries, array &$lastOptionsRef) {
				$this->entries = $entries;
				$this->lastOptionsRef = &$lastOptionsRef;
			}

			public function getEntries(array $options = []): array {
				$this->lastOptionsRef = $options;
				return $this->entries;
			}

			public function getEntry(int|string $id, array $options = []): ?array {
				return null;
			}

			public function createEntry(array $data): int|string {
				return 0;
			}

			public function updateEntry(int|string $id, array $patch): int|string {
				return 0;
			}

			public function deleteEntry(int|string $id): bool {
				return false;
			}
		}
