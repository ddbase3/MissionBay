<?php declare(strict_types=1);

namespace MissionBay\Test\Resource;

use PHPUnit\Framework\TestCase;
use MissionBay\Resource\ProductXrmExtractorAgentResource;
use AssistantFoundation\Api\IAgentContext;
use MissionBay\Dto\AgentContentItem;
use ResourceFoundation\Api\IEntityDataService;

final class ProductXrmExtractorAgentResourceTestDouble2 extends ProductXrmExtractorAgentResource {
			public function mapEntriesToItemsPublic(array $entries): array {
				return $this->mapEntriesToItems($entries);
			}
		}
