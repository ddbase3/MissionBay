<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\ProductXrmExtractorAgentResource;
use AssistantFoundation\Api\IAgentContext;
use MissionBay\Dto\AgentContentItem;
use ResourceFoundation\Api\IEntityDataService;

final class ProductXrmExtractorAgentResourceTestDouble3 extends ProductXrmExtractorAgentResource {
			private array $entries;

			public function __construct(IEntityDataService $entityDataService, ?string $id, array $entries) {
				parent::__construct($entityDataService, $id);
				$this->entries = $entries;
			}

			protected function loadEntries(): array {
				return $this->entries;
			}
		}
