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

namespace Base3\MissionBay\Flow;

use Base3\MissionBay\Api\IAgentConfigValueResolver;
use Base3\AssistantFoundation\Api\IAgentContext;
use Base3\MissionBay\Api\IAgentFlow;
use Base3\MissionBay\Api\IAgentNode;
use Base3\MissionBay\Api\IAgentNodeFactory;
use Base3\MissionBay\Api\IAgentResourceFactory;
use Base3\MissionBay\Agent\AgentNodePort;

abstract class AbstractFlow implements IAgentFlow {

	protected array $nodes = [];
	protected array $resources = [];
	protected bool $allowReentrant = false;
	protected ?IAgentContext $context = null;

	public function __construct(
		protected readonly IAgentNodeFactory $agentnodefactory,
		protected readonly IAgentResourceFactory $agentresourcefactory,
		protected readonly IAgentConfigValueResolver $configvalueresolver
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

	protected function applyNodeConfigInputs(IAgentNode $node, array &$inputs): void {
		foreach ($node->getConfig() as $key => $value) {
			if (array_key_exists($key, $inputs)) {
				continue;
			}

			if ($this->isInheritedConfigValue($value)) {
				continue;
			}

			$inputs[$key] = $this->configvalueresolver->resolveValue($value);
		}
	}

	private function isInheritedConfigValue(mixed $value): bool {
		if (!is_array($value)) {
			return false;
		}

		$mode = (string)($value['mode'] ?? 'inherit');

		return $mode === 'inherit';
	}

	abstract public function fromArray(array $data): self;
	abstract public function run(array $inputs): array;
}
