<?php declare(strict_types=1);

namespace MissionBay\Agent;

use Base3\Api\IClassMap;
use MissionBay\Api\IAgentFlow;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Api\IAgentNodeFactory;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentEventEmitter;

class AgentFlowFactory implements IAgentFlowFactory {

	public function __construct(
		private readonly IClassMap $classmap,
		private readonly IAgentNodeFactory $agentnodefactory
	) {}

	private function instantiateFlow(
		string $type,
		?IAgentContext $context,
		?IAgentEventEmitter $eventEmitter
	): IAgentFlow {

		$flow = $this->classmap->getInstanceByInterfaceName(IAgentFlow::class, $type);

		if (!$flow instanceof IAgentFlow) {
			throw new \RuntimeException("Flow type '$type' could not be instantiated or is invalid");
		}

		if ($context) $flow->setContext($context);
		if ($eventEmitter) $flow->setEventEmitter($eventEmitter);

		return $flow;
	}

	public function createFromArray(
		string $type,
		array $data,
		IAgentContext $context,
		?IAgentEventEmitter $eventEmitter = null
	): IAgentFlow {
		return $this->instantiateFlow($type, $context, $eventEmitter)
				->fromArray($data);
	}

	public function createEmpty(
		string $type,
		?IAgentContext $context = null,
		?IAgentEventEmitter $eventEmitter = null
	): IAgentFlow {
		return $this->instantiateFlow($type, $context, $eventEmitter);
	}
}
