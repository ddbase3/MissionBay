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

namespace Base3\MissionBay\Test\Resource\AgentTool\Agent;

use Base3\AssistantFoundation\Api\IAgentEventSink;
use Base3\AssistantFoundation\Api\IAgentExecutionService;
use Base3\AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\AssistantFoundation\Dto\AgentExecutionResult;

final class RunConfiguredAgentTestExecutionService implements IAgentExecutionService {

	/** @var array<int,AgentExecutionRequest> */
	public array $requests = [];
	/** @var array<string,mixed> */
	public array $output = ['assistant' => ['message' => ['id' => 'msg-1', 'content' => 'Completed']]];
	public bool $throwOnExecute = false;

	public static function getName(): string {
		return 'runconfiguredagenttestexecutionservice';
	}

	public function execute(AgentExecutionRequest $request, ?IAgentEventSink $eventSink = null): AgentExecutionResult {
		$this->requests[] = $request;
		if ($this->throwOnExecute) {
			throw new \RuntimeException('Execution unavailable');
		}
		return new AgentExecutionResult($this->output);
	}
}
