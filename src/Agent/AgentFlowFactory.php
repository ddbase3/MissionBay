<?php declare(strict_types=1);

namespace MissionBay\Agent;

use Base3\Api\IClassMap;
use MissionBay\Api\IAgentFlow;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Api\IAgentNodeFactory;
use MissionBay\Api\IAgentContext;

class AgentFlowFactory implements IAgentFlowFactory {

        public function __construct(
                private readonly IClassMap $classmap,
                private readonly IAgentNodeFactory $agentnodefactory
	) {}

        private function instantiateFlow(string $type, ?IAgentContext $context): IAgentFlow {
                $flow = $this->classmap->getInstanceByInterfaceName(IAgentFlow::class, $type);

                if (!$flow instanceof IAgentFlow) {
                        throw new \RuntimeException("Flow type '$type' could not be instantiated or is invalid");
                }

                if ($context) {
                        $flow->setContext($context);
                }

                return $flow;
        }

        public function createFromArray(string $type, array $data, IAgentContext $context): IAgentFlow {
                return $this->instantiateFlow($type, $context)->fromArray($data);
        }

        public function createEmpty(string $type, ?IAgentContext $context = null): IAgentFlow {
                return $this->instantiateFlow($type, $context);
        }
}

