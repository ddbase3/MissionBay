<?php declare(strict_types=1);

namespace MissionBay\Test\Resource\AgentTool\Agent;

use AssistantFoundation\Api\IAgentEventSink;
use AssistantFoundation\Api\IAgentExecutionService;
use AssistantFoundation\Dto\AgentExecutionRequest;
use AssistantFoundation\Dto\AgentExecutionResult;

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
