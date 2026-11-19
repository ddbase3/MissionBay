<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Orchestrator\Fixture;

use Base3\AssistantFoundation\Api\IAgentEventSink;
use Base3\AssistantFoundation\Api\IAgentExecutionService;
use Base3\AssistantFoundation\Dto\AgentExecutionRequest;
use Base3\AssistantFoundation\Dto\AgentExecutionResult;
use Base3\AssistantFoundation\Dto\AgentResume;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Orchestrator\AgentToolOrchestrator;
use Base3\MissionBay\Orchestrator\Service\AgentActionResumeService;
use Base3\MissionBay\Test\Orchestrator\ApprovalQueueChatModel;

/** Runs real MissionBay child pipelines behind the existing execution contract in tests. */
final class AgentDelegationExecutionService implements IAgentExecutionService {

	/** @var array<string,array<string,mixed>> */
	private array $agents = [];
	/** @var array<string,int> */
	public array $calls = [];
	/** @var array<string,int> */
	public array $resumes = [];

	public static function getName(): string {
		return 'agentdelegationexecutionservice';
	}

	/** @param array<int,mixed> $tools @param array<int,array<string,mixed>> $modelToolCalls */
	public function registerAgent(
		string $id,
		AgentToolOrchestrator $orchestrator,
		AgentActionResumeService $resumeService,
		array $tools,
		array $modelToolCalls,
		array $followupToolCalls = []
	): void {
		$this->agents[$id] = [
			'orchestrator' => $orchestrator,
			'resume_service' => $resumeService,
			'tools' => $tools,
			'model_tool_calls' => $modelToolCalls,
			'followup_tool_calls' => $followupToolCalls
		];
	}

	public function execute(AgentExecutionRequest $request, ?IAgentEventSink $eventSink = null): AgentExecutionResult {
		$id = (string)($request->getContext()['agent_id'] ?? '');
		$agent = $this->agents[$id] ?? null;
		if (!is_array($agent)) {
			throw new \RuntimeException('Unregistered test sub-agent: ' . $id);
		}
		$this->calls[$id] = ($this->calls[$id] ?? 0) + 1;
		$inputs = $request->getInputs();
		$prepared = null;
		if (is_array($inputs['resume'] ?? null)) {
			$this->resumes[$id] = ($this->resumes[$id] ?? 0) + 1;
			$prepared = $agent['resume_service']->prepare(AgentResume::fromArray($inputs['resume']));
		}
		$modelResponse = $prepared === null
			? $this->toolCallResponse($agent['model_tool_calls'])
			: (($this->resumes[$id] ?? 0) === 1 && $agent['followup_tool_calls'] !== []
				? $this->toolCallResponse($agent['followup_tool_calls'])
				: $this->terminalResponse('Agent ' . $id . ' completed.'));
		$tools = $agent['tools'];
		$definitions = [];
		foreach ($tools as $tool) {
			foreach ($tool->getToolDefinitions() as $definition) {
				$definitions[] = $definition;
			}
		}
		$result = $agent['orchestrator']->run(
			new ApprovalQueueChatModel([$modelResponse, $this->terminalResponse('Agent ' . $id . ' completed.')]),
			[['role' => 'user', 'content' => (string)($inputs['user_prompt'] ?? 'Delegated test')]],
			$definitions,
			$tools,
			new AgentContext(vars: $request->getContext()),
			resume: $prepared
		);
		$node = [
			'status' => $result->getExecutionStatus(),
			'interaction_requests' => array_map(static fn($value) => $value->toArray(), $result->getInteractionRequests()),
			'resume_handle' => $result->getResumeHandle()
		];
		if ($result->isSuspended()) {
			$node['message'] = null;
			$node['expires_at'] = $result->getAgentResult()?->getState()->getSuspension()?->getExpiresAt() ?? '';
		} else {
			$node['message'] = ['id' => 'msg-' . $id, 'content' => $result->getFinalOutputContent()];
		}
		return new AgentExecutionResult(['assistant' => $node], [], $result->getAgentResult());
	}

	/** @param array<int,array<string,mixed>> $calls @return array<string,mixed> */
	private function toolCallResponse(array $calls): array {
		$toolCalls = [];
		foreach ($calls as $call) {
			$toolCalls[] = [
				'id' => $call['id'],
				'function' => [
					'name' => $call['name'],
					'arguments' => json_encode($call['arguments'], JSON_THROW_ON_ERROR)
				]
			];
		}
		return ['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => $toolCalls]]]];
	}

	/** @return array<string,mixed> */
	private function terminalResponse(string $content): array {
		return ['choices' => [['message' => ['role' => 'assistant', 'content' => $content]]]];
	}
}
