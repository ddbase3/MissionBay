<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Display\Fixture;

use Base3\AssistantFoundation\Api\IAgentEventSink;
use Base3\AssistantFoundation\Api\IAgentExecutionService;
use Base3\AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\AssistantFoundation\Dto\AgentExecutionResult;

final class AgentAdminHitlExecution implements IAgentExecutionService {
	public ?AgentExecutionRequest $lastRequest = null;
	public int $calls = 0;
	public ?AgentExecutionResult $result = null;
	public static function getName(): string { return 'agentadminhitlexecution'; }
	public function execute(AgentExecutionRequest $request, ?IAgentEventSink $eventSink = null): AgentExecutionResult {
		$this->calls++;
		$this->lastRequest = $request;
		return $this->result ?? new AgentExecutionResult(['assistant' => ['message' => ['content' => 'Admin reply']]]);
	}
}
