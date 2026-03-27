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

namespace MissionBay\Flow;

use MissionBay\Api\IAgentFlow;
use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentNode;
use MissionBay\Api\IAgentNodeFactory;
use MissionBay\Api\IAgentResourceFactory;

abstract class AbstractFlow implements IAgentFlow {

        protected array $nodes = [];
        protected array $resources = [];
	protected bool $allowReentrant = false;
	protected ?IAgentContext $context = null;

	public function __construct(
		protected readonly IAgentNodeFactory $agentnodefactory,
		protected readonly IAgentResourceFactory $agentresourcefactory
	) {}

        public function setContext(IAgentContext $context): void {
                $this->context = $context;
        }

        public function addNode(IAgentNode $node): void {
                $this->nodes[$node->getId()] = $node;
        }

        public function getNodes(): array {
                return $this->nodes;
        }

	protected function normalizePortDefs(array $defs): array {
		$ports = [];
		foreach ($defs as $def) {
			if ($def instanceof AgentNodePort) {
				$ports[] = $def;
			} elseif (is_string($def)) {
				$ports[] = new AgentNodePort(name: $def);
			}
		}
		return $ports;
	}

	abstract public function fromArray(array $data): self;
        abstract public function run(array $inputs): array;
}

