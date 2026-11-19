<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Job\Fixture;

use Base3\AssistantFoundation\Api\IAgentEventSink;
use Base3\AssistantFoundation\Api\IAgentExecutionService;
use Base3\AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\AssistantFoundation\Dto\AgentExecutionResult;
use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\AssistantFoundation\Dto\AgentResult;
use Base3\AssistantFoundation\Dto\AgentState;
use Base3\AssistantFoundation\Dto\AgentSuspensionState;
use Base3\MissionBay\Test\Display\Fixture\AgentAdminHitlSuspensionRepository;

final class ScheduledHitlTestExecution implements IAgentExecutionService {

	public array $statuses = [];
	public int $calls = 0;
	public ?AgentExecutionRequest $lastRequest = null;

	public function __construct(private readonly AgentAdminHitlSuspensionRepository $repository) {}

	public static function getName(): string { return 'scheduledhitltestexecution'; }

	public function execute(AgentExecutionRequest $request, ?IAgentEventSink $eventSink = null): AgentExecutionResult {
		$this->calls++;
		$this->lastRequest = $request;
		$status = array_shift($this->statuses) ?? AgentExecutionStatus::COMPLETED;
		if (AgentExecutionStatus::isSuspended($status)) {
			$this->repository->pending = new AgentSuspensionState(true, $status, [], 'job-resume-handle');
		} else {
			$this->repository->pending = null;
		}
		return new AgentExecutionResult([], [], new AgentResult($status, AgentState::empty()));
	}
}
