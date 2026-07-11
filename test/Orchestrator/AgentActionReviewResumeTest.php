<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentInteractionResponse;
use AssistantFoundation\Dto\AgentResume;
use AssistantFoundation\Dto\AgentSuspensionClaim;
use AssistantFoundation\Exception\AgentSuspensionRepositoryException;
use Base3\Event\Api\IEventManager;
use Base3\State\Api\IStateStore;
use MissionBay\Api\IAgentTool;
use MissionBay\ChatModel\NormalizedChatModelTrait;
use MissionBay\Context\AgentContext;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\AgentToolOrchestrator;
use MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use MissionBay\Orchestrator\Service\AgentActionResumeService;
use MissionBay\Orchestrator\Service\AgentActionReviewService;
use MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use MissionBay\Orchestrator\Suspension\StateStoreAgentSuspensionRepository;
use MissionBay\Policy\AllowAllAgentActionPolicy;
use MissionBay\Policy\MutationApprovalAgentActionPolicy;
use PHPUnit\Framework\TestCase;

final class AgentActionReviewResumeTest extends TestCase {

	public function testMutatingToolUsesOpaqueHandleAndExecutesOnlyAfterApproval(): void {
		$tool = new ApprovalMutationTool();
		[$orchestrator, $resumeService] = $this->createHarness();
		$firstResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->toolCallResponse('call-1', 'update_record', ['id' => 42, 'title' => 'Reviewed title'])]),
			[['role' => 'user', 'content' => 'Update record 42.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);

		$this->assertTrue($firstResult->isSuspended());
		$this->assertSame(AgentExecutionStatus::AWAITING_APPROVAL, $firstResult->getExecutionStatus());
		$this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $firstResult->getResumeHandle());
		$this->assertSame(0, $tool->getCallCount());

		$request = $firstResult->getInteractionRequests()[0];
		$resume = new AgentResume($firstResult->getResumeHandle(), [
			new AgentInteractionResponse($request->getId(), AgentInteractionResponse::DECISION_APPROVE)
		]);
		$prepared = $resumeService->prepare($resume);
		$secondResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->terminalResponse()]),
			[],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext(),
			null,
			10,
			'',
			null,
			null,
			null,
			null,
			$prepared
		);

		$this->assertTrue($secondResult->isCompleted());
		$this->assertSame(1, $tool->getCallCount());
		$this->assertSame(['id' => 42, 'title' => 'Reviewed title'], $tool->getLastArguments());
	}

	public function testConsumedResumeHandleIsRejectedAsReplay(): void {
		$tool = new ApprovalMutationTool();
		[$orchestrator, $resumeService] = $this->createHarness();
		$firstResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->toolCallResponse('call-replay', 'update_record', ['id' => 9, 'title' => 'Once'])]),
			[['role' => 'user', 'content' => 'Update once.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);
		$request = $firstResult->getInteractionRequests()[0];
		$resume = new AgentResume($firstResult->getResumeHandle(), [
			new AgentInteractionResponse($request->getId(), AgentInteractionResponse::DECISION_APPROVE)
		]);

		$orchestrator->run(
			new ApprovalQueueChatModel([$this->terminalResponse()]),
			[],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext(),
			null,
			10,
			'',
			null,
			null,
			null,
			null,
			$resumeService->prepare($resume)
		);

		try {
			$resumeService->prepare($resume);
			$this->fail('Expected replay rejection.');
		} catch (AgentSuspensionRepositoryException $e) {
			$this->assertSame(AgentSuspensionRepositoryException::REASON_ALREADY_CONSUMED, $e->getReason());
		}
	}

	public function testForeignClaimCannotReleaseCurrentResumeLease(): void {
		$tool = new ApprovalMutationTool();
		[$orchestrator, $resumeService, $repository] = $this->createHarness();
		$firstResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->toolCallResponse('call-owned', 'update_record', ['id' => 11, 'title' => 'Owned'])]),
			[['role' => 'user', 'content' => 'Update it.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);
		$request = $firstResult->getInteractionRequests()[0];
		$resume = new AgentResume($firstResult->getResumeHandle(), [
			new AgentInteractionResponse($request->getId(), AgentInteractionResponse::DECISION_APPROVE)
		]);
		$prepared = $resumeService->prepare($resume);

		$repository->release(new AgentSuspensionClaim(
			$prepared->getResumeHandle(),
			str_repeat('x', 43),
			$prepared->getSuspension()
		));

		try {
			$resumeService->prepare($resume);
			$this->fail('Expected active claim ownership to remain intact.');
		} catch (AgentSuspensionRepositoryException $e) {
			$this->assertSame(AgentSuspensionRepositoryException::REASON_ALREADY_CLAIMED, $e->getReason());
		}

		$repository->release($prepared->getClaim());
	}

	public function testInvalidResponseReleasesClaimForCorrectedRetry(): void {
		$tool = new ApprovalMutationTool();
		[$orchestrator, $resumeService] = $this->createHarness();
		$firstResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->toolCallResponse('call-retry', 'update_record', ['id' => 7, 'title' => 'Retry'])]),
			[['role' => 'user', 'content' => 'Update it.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);

		$invalid = new AgentResume($firstResult->getResumeHandle(), []);
		$invalidResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->terminalResponse()]),
			[],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext(),
			null,
			10,
			'',
			null,
			null,
			null,
			null,
			$resumeService->prepare($invalid)
		);
		$this->assertSame('missing_agent_resume_response', $invalidResult->getFailureCode());
		$this->assertSame(0, $tool->getCallCount());

		$request = $firstResult->getInteractionRequests()[0];
		$corrected = new AgentResume($firstResult->getResumeHandle(), [
			new AgentInteractionResponse($request->getId(), AgentInteractionResponse::DECISION_DENY)
		]);
		$this->assertSame($firstResult->getResumeHandle(), $resumeService->prepare($corrected)->getResumeHandle());
	}

	/** @return array{0:AgentToolOrchestrator,1:AgentActionResumeService,2:StateStoreAgentSuspensionRepository} */
	private function createHarness(): array {
		$repository = new StateStoreAgentSuspensionRepository(new ApprovalMemoryStateStore());
		$fingerprint = new AgentActionFingerprint();
		$resumeService = new AgentActionResumeService($fingerprint, $repository);
		$reviewService = new AgentActionReviewService($fingerprint, $repository, 900);
		$stages = [
			new AgentModelDecisionStage(),
			new AgentActionPolicyStage(
				new StaticAgentActionPolicyResolver([
					new MutationApprovalAgentActionPolicy(),
					new AllowAllAgentActionPolicy()
				]),
				$fingerprint,
				'action-policy',
				'action-policy',
				['mutation-approval-actions', 'allow-all-actions'],
				$reviewService
			),
			new AgentToolExecutionStage(new ApprovalSilentEventManager()),
			new AgentToolObservationStage()
		];

		return [new AgentToolOrchestrator(null, null, $stages, $resumeService), $resumeService, $repository];
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

final class ApprovalMemoryStateStore implements IStateStore {
	/** @var array<string,array{value:mixed,expires_at:?int}> */
	private array $values = [];

	public function get(string $key, mixed $default = null): mixed {
		if (!$this->has($key)) {
			return $default;
		}
		return $this->values[$key]['value'];
	}

	public function has(string $key): bool {
		if (!isset($this->values[$key])) {
			return false;
		}
		$expiresAt = $this->values[$key]['expires_at'];
		if ($expiresAt !== null && $expiresAt <= time()) {
			unset($this->values[$key]);
			return false;
		}
		return true;
	}

	public function set(string $key, mixed $value, ?int $ttlSeconds = null): void {
		$this->values[$key] = [
			'value' => $value,
			'expires_at' => $ttlSeconds === null ? null : time() + $ttlSeconds
		];
	}

	public function delete(string $key): bool {
		$exists = $this->has($key);
		unset($this->values[$key]);
		return $exists;
	}

	public function setIfNotExists(string $key, mixed $value, ?int $ttlSeconds = null): bool {
		if ($this->has($key)) {
			return false;
		}
		$this->set($key, $value, $ttlSeconds);
		return true;
	}

	public function listKeys(string $prefix): array {
		return array_values(array_filter(array_keys($this->values), static fn(string $key): bool => str_starts_with($key, $prefix)));
	}

	public function flush(): void {}
}
