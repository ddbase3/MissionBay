<?php declare(strict_types=1);

namespace MissionBay\Api;

use MissionBay\Agent\AgentFlow;

interface IAgentFlowFactory {

	/**
	 * Erzeugt einen neuen AgentFlow aus einer Array-Definition.
	 */
	public function createFromArray(array $data): AgentFlow;

	/**
	 * Erzeugt einen leeren AgentFlow.
	 */
	public function createEmpty(): AgentFlow;
}

