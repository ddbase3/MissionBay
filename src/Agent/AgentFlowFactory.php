<?php declare(strict_types=1);

namespace MissionBay\Agent;

use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Api\IAgentNodeFactory;
use MissionBay\Api\IAgentContext;

class AgentFlowFactory implements IAgentFlowFactory {

	public function __construct(private readonly IAgentNodeFactory $agentnodefactory) {}

	public function createFromArray(array $data, IAgentContext $context): AgentFlow {
		$flow = new AgentFlow($this->agentnodefactory);
		$flow->setContext($context);
		return $flow->fromArray($data);
	}

	public function createEmpty(?IAgentContext $context = null): AgentFlow {
		$flow = new AgentFlow($this->agentnodefactory);
		if ($flow) $flow->setContext($context);
		return $flow;
	}
}

