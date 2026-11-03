<?php declare(strict_types=1);

namespace MissionBay\Resource\Test;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\CrmProductXrmExtractorAgentResource;
use MissionBay\Api\IAgentContentExtractor;
use AssistantFoundation\Api\IAgentContext;
use MissionBay\Dto\AgentContentItem;
use ResourceFoundation\Api\IEntityDataService;

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
