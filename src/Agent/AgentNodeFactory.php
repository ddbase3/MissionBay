<?php declare(strict_types=1);

namespace MissionBay\Agent;

use MissionBay\Api\IAgentNodeFactory;
use MissionBay\Api\IAgentNode;
use Base3\Api\IClassMap;

class AgentNodeFactory implements IAgentNodeFactory {

	public function __construct(private readonly IClassMap $classmap) {}

	public function createNode(string $type): ?IAgentNode {
		$node = $this->classmap->getInstanceByInterfaceName(IAgentNode::class, $type);
		return $node instanceof IAgentNode ? $node : null;
	}
}

