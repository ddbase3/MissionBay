<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Display\Fixture;

use Base3\AssistantFoundation\Api\IAgentEventSink;
use Base3\AssistantFoundation\Api\IAgentExecutionService;
use Base3\AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\AssistantFoundation\Dto\AgentExecutionResult;
use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\AssistantFoundation\Dto\AgentResult;
use Base3\AssistantFoundation\Dto\AgentResume;
use Base3\AssistantFoundation\Dto\AgentState;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Orchestrator\Service\AgentActionResumeService;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;

/** Executes the real MissionBay resume boundary without an LLM or a tool. */
final class AgentAdminHitlRealResumeExecution implements IAgentExecutionService {

	public int $calls = 0;
	public ?AgentExecutionRequest $lastRequest = null;
	public string $failureCode = '';

	public function __construct(
		private readonly AgentActionResumeService $resumeService,
		private readonly AgentContext $context
	) {}

	public static function getName(): string {
		return 'agentadminhitlrealresumeexecution';
	}

	public function execute(AgentExecutionRequest $request, ?IAgentEventSink $eventSink = null): AgentExecutionResult {
		$this->calls++;
		$this->lastRequest = $request;
		$resume = AgentResume::fromArray($request->getInputs()['resume'] ?? []);
		$this->context->setVar(AgentToolLoopContextKeys::RESUME, $this->resumeService->prepare($resume));
		$patch = $this->resumeService->resume($this->context)->getPatch();
		$this->failureCode = (string)($patch[AgentToolLoopContextKeys::FAILURE_CODE] ?? '');
		$status = $this->failureCode !== '' || ($patch[AgentToolLoopContextKeys::EXECUTION_STATUS] ?? '') === AgentExecutionStatus::FAILED
			? AgentExecutionStatus::FAILED : AgentExecutionStatus::COMPLETED;
		return new AgentExecutionResult(
			['assistant' => ['message' => ['content' => $status === AgentExecutionStatus::COMPLETED ? 'Resumed.' : '']]],
			[],
			new AgentResult($status, AgentState::empty())
		);
	}
}
