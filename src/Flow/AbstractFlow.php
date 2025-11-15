<?php declare(strict_types=1);

namespace MissionBay\Flow;

use MissionBay\Api\IAgentContext;
use MissionBay\Api\IAgentEventEmitter;
use MissionBay\Api\IAgentFlow;
use MissionBay\Api\IAgentNode;
use MissionBay\Api\IAgentNodeFactory;
use MissionBay\Api\IAgentResourceFactory;
use MissionBay\Agent\AgentNodePort;

abstract class AbstractFlow implements IAgentFlow {

        protected array $nodes = [];
        protected array $resources = [];
	protected bool $allowReentrant = false;
	protected ?IAgentContext $context = null;
	protected ?IAgentEventEmitter $eventEmitter = null;

	public function __construct(
		protected readonly IAgentNodeFactory $agentnodefactory,
		protected readonly IAgentResourceFactory $agentresourcefactory
	) {}

        public function setContext(IAgentContext $context): void {
                $this->context = $context;
        }

	public function setEventEmitter(?IAgentEventEmitter $emitter): void {
		$this->eventEmitter = $emitter;
	}

	public function emitEvent(array $event): void {
		if (!$this->eventEmitter) return;
		$this->eventEmitter->emitEvent($event);
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
