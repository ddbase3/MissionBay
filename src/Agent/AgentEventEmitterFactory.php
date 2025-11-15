<?php declare(strict_types=1);

namespace MissionBay\Agent;

use Base3\Api\IClassMap;
use MissionBay\Api\IAgentEventEmitter;
use MissionBay\Api\IAgentEventEmitterFactory;

class AgentEventEmitterFactory implements IAgentEventEmitterFactory {

	public function __construct(private readonly IClassMap $classmap) {}

	public function createEventEmitter(string $type): ?IAgentEventEmitter {
                $eventEmitter = $this->classmap->getInstanceByInterfaceName(IAgentEventEmitter::class, $type);
                return $node instanceof IAgentEventEmitter ? $eventEmitter : null;
	}
}
