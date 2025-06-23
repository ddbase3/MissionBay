<?php declare(strict_types=1);

namespace MissionBay\Agent;

use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Api\IAgentNodeFactory;

class AgentFlowFactory implements IAgentFlowFactory {

	public function __construct(private readonly IAgentNodeFactory $agentnodefactory) {}

	public function createFromArray(array $data): AgentFlow {
		return (new AgentFlow($this->agentnodefactory))->fromArray($data);
	}

	public function createEmpty(): AgentFlow {
		return new AgentFlow($this->agentnodefactory);
	}
}

