<?php declare(strict_types=1);

namespace MissionBay\Agent;

use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Api\IAgentResource;
use Base3\Api\IClassMap;

class AgentResourceFactory implements IAgentResourceFactory {

	public function __construct(private readonly IClassMap $classmap) {}

	public function createResource(string $type): ?IAgentResource {
		$resource = $this->classmap->getInstanceByInterfaceName(IAgentResource::class, $type);
		return $resource instanceof IAgentResource ? $resource : null;
	}
}

