<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentInteractionResponse;
use AssistantFoundation\Dto\AgentResume;
use AssistantFoundation\Dto\AgentSuspension;
use Base3\Event\Api\IEventManager;
use MissionBay\Api\IAgentTool;
use MissionBay\ChatModel\NormalizedChatModelTrait;
use MissionBay\Context\AgentContext;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\AgentToolOrchestrator;
use MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use MissionBay\Orchestrator\Stage\AgentActionResumeStage;
use MissionBay\Orchestrator\Stage\AgentActionReviewStage;
use MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use MissionBay\Policy\AllowAllAgentActionPolicy;
use MissionBay\Policy\MutationApprovalAgentActionPolicy;
use PHPUnit\Framework\TestCase;

final class AgentActionReviewResumeTest extends TestCase {

	public function testMutatingToolIsSuspendedAndExecutesOnlyAfterExplicitApproval(): void {
		$tool = new ApprovalMutationTool();
		$orchestrator = new AgentToolOrchestrator(null, null, $this->createStages());
		$firstResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->toolCallResponse('call-1', 'update_record', ['id' => 42, 'title' => 'Reviewed title'])]),
			[['role' => 'user', 'content' => 'Update record 42.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);

		$this->assertTrue($firstResult->isSuspended());
		$this->assertTrue($firstResult->isAwaitingApproval());
		$this->assertSame(AgentExecutionStatus::AWAITING_APPROVAL, $firstResult->getExecutionStatus());
		$this->assertCount(1, $firstResult->getInteractionRequests());
		$this->assertSame([
			'tool' => 'update_record',
			'input' => ['id' => 42, 'title' => 'Reviewed title']
		], $firstResult->getInteractionRequests()[0]->getSummary());
		$this->assertSame(0, $tool->getCallCount());
		$this->assertSame([], $firstResult->getToolCalls());

		$suspension = $firstResult->getSuspension();
		$this->assertNotNull($suspension);
		$request = $firstResult->getInteractionRequests()[0];
		$resume = new AgentResume($suspension, [
			new AgentInteractionResponse($request->getId(), AgentInteractionResponse::DECISION_APPROVE)
		]);
		$secondResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->terminalResponse()]),
			[['role' => 'user', 'content' => 'Approved.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext(),
			null,
			8,
			'',
			null,
			null,
			null,
			null,
			$resume
		);

		$this->assertTrue($secondResult->isCompleted());
		$this->assertFalse($secondResult->isSuspended());
		$this->assertSame(AgentExecutionStatus::COMPLETED, $secondResult->getExecutionStatus());
		$this->assertSame(1, $tool->getCallCount());
		$this->assertSame(['id' => 42, 'title' => 'Reviewed title'], $tool->getLastArguments());
		$this->assertCount(1, $secondResult->getToolCalls());
	}

	public function testExplicitDenialBecomesToolObservationWithoutExecutingMutation(): void {
		$tool = new ApprovalMutationTool();
		$orchestrator = new AgentToolOrchestrator(null, null, $this->createStages());
		$firstResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->toolCallResponse('call-deny', 'update_record', ['id' => 5, 'title' => 'Do not write'])]),
			[['role' => 'user', 'content' => 'Update it.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);
		$request = $firstResult->getInteractionRequests()[0];
		$resume = new AgentResume($firstResult->getSuspension(), [
			new AgentInteractionResponse($request->getId(), AgentInteractionResponse::DECISION_DENY, [], 'Keep the current data.')
		]);

		$secondResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->terminalResponse()]),
			[['role' => 'user', 'content' => 'No, cancel it.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext(),
			null,
			8,
			'',
			null,
			null,
			null,
			null,
			$resume
		);

		$this->assertTrue($secondResult->isCompleted());
		$this->assertSame(0, $tool->getCallCount());
		$this->assertSame([], $secondResult->getToolCalls());
		$toolMessages = array_values(array_filter(
			$secondResult->getMessages(),
			static fn(array $message): bool => ($message['role'] ?? null) === 'tool'
		));
		$this->assertCount(1, $toolMessages);
		$this->assertStringContainsString('Keep the current data.', $toolMessages[0]['content']);
	}

	public function testApprovalIsConsumedAfterOnePolicyPass(): void {
		$tool = new ApprovalMutationTool();
		$orchestrator = new AgentToolOrchestrator(null, null, $this->createStages());
		$firstResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->toolCallResponse('call-once', 'update_record', ['id' => 9, 'title' => 'Once'])]),
			[['role' => 'user', 'content' => 'Update it once.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);
		$request = $firstResult->getInteractionRequests()[0];
		$resume = new AgentResume($firstResult->getSuspension(), [
			new AgentInteractionResponse($request->getId(), AgentInteractionResponse::DECISION_APPROVE)
		]);

		$secondResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->toolCallResponse('call-once', 'update_record', ['id' => 9, 'title' => 'Once'])]),
			[],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext(),
			null,
			8,
			'',
			null,
			null,
			null,
			null,
			$resume
		);

		$this->assertTrue($secondResult->isSuspended());
		$this->assertSame(AgentExecutionStatus::AWAITING_APPROVAL, $secondResult->getExecutionStatus());
		$this->assertSame(1, $tool->getCallCount());
	}

	public function testTamperedReviewedActionFailsBeforeToolExecution(): void {
		$tool = new ApprovalMutationTool();
		$orchestrator = new AgentToolOrchestrator(null, null, $this->createStages());
		$firstResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->toolCallResponse('call-tamper', 'update_record', ['id' => 5, 'title' => 'Original'])]),
			[['role' => 'user', 'content' => 'Update it.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);

		$payload = $firstResult->getSuspension()->toArray();
		$payload['requests'][0]['action']['input']['title'] = 'Tampered';
		$tampered = AgentSuspension::fromArray($payload);
		$request = $tampered->getRequests()[0];
		$resume = new AgentResume($tampered, [
			new AgentInteractionResponse($request->getId(), AgentInteractionResponse::DECISION_APPROVE)
		]);
		$result = $orchestrator->run(
			new ApprovalQueueChatModel([$this->terminalResponse()]),
			[['role' => 'user', 'content' => 'Approved.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext(),
			null,
			8,
			'',
			null,
			null,
			null,
			null,
			$resume
		);

		$this->assertSame('invalid_agent_resume_snapshot', $result->getFailureCode());
		$this->assertSame(0, $tool->getCallCount());
	}

	/** @return array<int,\AssistantFoundation\Api\IAgentStage> */
	private function createStages(): array {
		$fingerprint = new AgentActionFingerprint();
		return [
			new AgentActionResumeStage($fingerprint),
			new AgentModelDecisionStage(),
			new AgentActionPolicyStage(
				new StaticAgentActionPolicyResolver([
					new MutationApprovalAgentActionPolicy(),
					new AllowAllAgentActionPolicy()
				]),
				$fingerprint,
				'action-policy',
				'action-policy',
				['mutation-approval-actions', 'allow-all-actions']
			),
			new AgentActionReviewStage($fingerprint),
			new AgentToolExecutionStage(new ApprovalSilentEventManager()),
			new AgentToolObservationStage()
		];
	}

	/** @param array<string,mixed> $arguments @return array<string,mixed> */
	private function toolCallResponse(string $id, string $name, array $arguments): array {
		return [
			'choices' => [[
				'message' => [
					'role' => 'assistant',
					'content' => null,
					'tool_calls' => [[
						'id' => $id,
						'function' => [
							'name' => $name,
							'arguments' => json_encode($arguments, JSON_THROW_ON_ERROR)
						]
					]]
				]
			]]
		];
	}

	/** @return array<string,mixed> */
	private function terminalResponse(): array {
		return ['choices' => [['message' => ['role' => 'assistant', 'content' => 'TOOL_PHASE_COMPLETE']]]];
	}
}

final class ApprovalMutationTool implements IAgentTool {
	private int $callCount = 0;
	/** @var array<string,mixed> */
	private array $lastArguments = [];
	public static function getName(): string { return 'approvalmutationtool'; }
	public function getToolDefinitions(): array {
		return [[
			'type' => 'function',
			'label' => 'Update record',
			'annotations' => ['readOnlyHint' => false],
			'function' => [
				'name' => 'update_record',
				'description' => 'Updates one record.',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'id' => ['type' => 'integer'],
						'title' => ['type' => 'string']
					]
				]
			]
		]];
	}
	public function callTool(string $name, array $arguments, IAgentContext $context): mixed {
		$this->callCount++;
		$this->lastArguments = $arguments;
		return ['updated' => true, 'record' => $arguments];
	}
	public function getCallCount(): int { return $this->callCount; }
	/** @return array<string,mixed> */
	public function getLastArguments(): array { return $this->lastArguments; }
}

final class ApprovalQueueChatModel implements IAiChatModel {
	use NormalizedChatModelTrait;
	/** @var array<int,mixed> */
	private array $responses;
	/** @param array<int,mixed> $responses */
	public function __construct(array $responses) { $this->responses = $responses; }
	public function chat(array $messages): string { return ''; }
	public function raw(array $messages, array $tools = []): mixed {
		if ($this->responses === []) {
			throw new \RuntimeException('No queued model response available.');
		}
		return array_shift($this->responses);
	}
	public function stream(array $messages, array $tools, callable $onData, callable $onMeta = null): void {}
	public function setOptions(array $options): void {}
	public function getOptions(): array { return []; }
}

final class ApprovalSilentEventManager implements IEventManager {
	public function on(string $event, callable $listener, int $priority = 0): void {}
	public function once(string $event, callable $listener, int $priority = 0): void {}
	public function off(string $event, callable $listener): void {}
	public function fire(object|string $event, ...$args): array { return []; }
}
