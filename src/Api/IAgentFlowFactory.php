<?php declare(strict_types=1);

namespace MissionBay\Api;

use MissionBay\Api\IAgentContext;

interface IAgentFlowFactory {

	/**
	 * Erzeugt einen neuen AgentFlow aus einer Array-Definition.
	 */
	public function createFromArray(string $type, array $data, IAgentContext $context): IAgentFlow;

	/**
	 * Erzeugt einen leeren AgentFlow.
	 */
	public function createEmpty(string $type, ?IAgentContext $context = null): IAgentFlow;
}

