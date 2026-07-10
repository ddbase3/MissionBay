<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MissionBay for BASE3 Framework.
 *
 * MissionBay extends the BASE3 framework with a modular runtime
 * foundation for agent flows, reusable nodes, and dockable resources.
 * It provides declarative execution for AI-driven workflows.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de/v/missionbay
 * https://github.com/ddbase3/MissionBay
 **********************************************************************/

namespace MissionBay\Agent;

use Base3\Api\IClassMap;
use MissionBay\Api\IAgentFlow;
use MissionBay\Api\IAgentFlowFactory;
use MissionBay\Api\IAgentNodeFactory;
use AssistantFoundation\Api\IAgentContext;

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

