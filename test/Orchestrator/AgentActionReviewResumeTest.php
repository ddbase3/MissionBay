<?php declare(strict_types=1);

namespace MissionBay\Test\Orchestrator;

use AssistantFoundation\Api\IAgentContext;
use AssistantFoundation\Api\IAiChatModel;
use AssistantFoundation\Dto\AiChatResult;
use AssistantFoundation\Dto\AgentAction;
use AssistantFoundation\Dto\AgentActionReview;
use AssistantFoundation\Dto\AgentExecutionStatus;
use AssistantFoundation\Dto\AgentMutationCommitDecision;
use AssistantFoundation\Dto\AgentMutationCommitSnapshot;
use AssistantFoundation\Dto\AgentInteractionResponse;
use AssistantFoundation\Dto\AgentResume;
use AssistantFoundation\Dto\AgentSuspensionClaim;
use AssistantFoundation\Exception\AgentSuspensionRepositoryException;
use Base3\Event\Api\IEventManager;
use Base3\State\Api\IStateStore;
use MissionBay\Api\IAgentMutationGuardedTool;
use MissionBay\Api\IAgentTool;
use MissionBay\ChatModel\NormalizedChatModelTrait;
use MissionBay\Context\AgentContext;
use MissionBay\Event\MissionBayAgentActionAuditEvent;
use MissionBay\Orchestrator\AgentActionFingerprint;
use MissionBay\Orchestrator\AgentToolOrchestrator;
use MissionBay\Dto\Orchestrator\AgentModelDecisionConfig;
use MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use MissionBay\Orchestrator\Service\AgentActionResumeService;
use MissionBay\Orchestrator\Service\AgentActionReviewService;
use MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use MissionBay\Orchestrator\Service\AgentCapabilitySelectionGuardService;
use MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use AssistantRuntime\Service\StateStoreAgentSuspensionRepository;
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
		$this->assertSame('Update record', $request->getTitle());
		$this->assertSame(['Record' => '42', 'New title' => 'Reviewed title'], $request->getSummary());
		$this->assertArrayNotHasKey(
			AgentMutationCommitGuardService::TOOL_CALL_METADATA_SNAPSHOT,
			$request->getMetadata()
		);
		$resume = new AgentResume($firstResult->getResumeHandle(), [
			new AgentInteractionResponse($request->getId(), AgentInteractionResponse::DECISION_APPROVE)
		]);
		$prepared = $resumeService->prepare($resume);
		$this->assertArrayHasKey(
			AgentMutationCommitGuardService::TOOL_CALL_METADATA_SNAPSHOT,
			$prepared->getSuspension()->getRequests()[0]->getMetadata()
		);
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

	public function testNativeMutationToolCallSuppressesModelConfirmationTextBeforeStructuredApproval(): void {
		$tool = new ApprovalMutationTool();
		[$orchestrator] = $this->createHarness();
		$result = $orchestrator->run(
			new ApprovalQueueChatModel([
				$this->toolCallResponse(
					'call-native-approval',
					'update_record',
					['id' => 42, 'title' => 'Native approval'],
					'Ich ändere den Datensatz. Möchtest du fortfahren?'
				)
			]),
			[['role' => 'user', 'content' => 'Update record 42.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext(),
			modelDecisionConfig: AgentModelDecisionConfig::native()
		);

		$this->assertTrue($result->isSuspended());
		$this->assertSame(0, $tool->getCallCount());
		$this->assertSame('', $result->getFinalOutputContent());
		$this->assertCount(1, $result->getInteractionRequests());
		$this->assertSame('', $result->getMessages()[1]['content']);
		$this->assertArrayHasKey('tool_calls', $result->getMessages()[1]);
	}



	public function testNativeMutationResumeBuffersTerminalAnswerAfterSingleApprovedExecution(): void {
		$tool = new ApprovalMutationTool();
		[$orchestrator, $resumeService] = $this->createHarness();
		$firstResult = $orchestrator->run(
			new ApprovalQueueChatModel([
				$this->toolCallResponse(
					'call-native-live-approval',
					'update_record',
					['id' => 42, 'title' => 'Native live approval']
				)
			]),
			[['role' => 'user', 'content' => 'Update record 42.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext(),
			modelDecisionConfig: AgentModelDecisionConfig::native()
		);
		$request = $firstResult->getInteractionRequests()[0];
		$prepared = $resumeService->prepare(new AgentResume($firstResult->getResumeHandle(), [
			new AgentInteractionResponse($request->getId(), AgentInteractionResponse::DECISION_APPROVE)
		]));
		$events = [];

		$secondResult = $orchestrator->run(
			new ApprovalQueueChatModel([[
				'choices' => [[
					'message' => [
						'role' => 'assistant',
						'content' => 'Record 42 was updated.'
					]
				]]
			]]),
			[],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext(),
			eventCallback: static function(string $event, array $payload) use (&$events): void {
				$events[] = [$event, $payload];
			},
			resume: $prepared,
			modelDecisionConfig: AgentModelDecisionConfig::native()
		);

		$this->assertTrue($secondResult->isCompleted());
		$this->assertSame(1, $tool->getCallCount());
		$this->assertSame('Record 42 was updated.', $secondResult->getFinalOutputContent());
		$this->assertFalse($secondResult->isFinalOutputStreamed());
		$tokenEvents = array_values(array_filter(
			$events,
			static fn(array $event): bool => ($event[0] ?? '') === 'token'
		));
		$this->assertSame([], $tokenEvents);
		$resolvedEvents = array_values(array_filter(
			$events,
			static fn(array $event): bool => ($event[0] ?? '') === 'agent.interaction.resolved'
		));
		$this->assertCount(1, $resolvedEvents);
		$this->assertSame('resolved', $resolvedEvents[0][1]['lifecycle'] ?? null);
		$this->assertSame('approved', $resolvedEvents[0][1]['resolution']['outcome'] ?? null);
		$this->assertNotSame('', $resolvedEvents[0][1]['id'] ?? '');
	}

	public function testNaturalLanguageApprovalExecutesMutation(): void {
		$tool = new ApprovalMutationTool();
		[$orchestrator, $resumeService] = $this->createHarness();
		$firstResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->toolCallResponse('call-natural-approve', 'update_record', ['id' => 42, 'title' => 'Natural approval'])]),
			[['role' => 'user', 'content' => 'Update record 42.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);
		$request = $firstResult->getInteractionRequests()[0];
		$resume = new AgentResume($firstResult->getResumeHandle(), [], 'jo hau rein');

		$result = $orchestrator->run(
			new ApprovalQueueChatModel([
				$this->interactionResolutionResponse('resolved', [[
					'request_id' => $request->getId(),
					'decision' => AgentInteractionResponse::DECISION_APPROVE,
					'input' => []
				]]),
				$this->terminalResponse()
			]),
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

		$this->assertTrue($result->isCompleted());
		$this->assertSame(1, $tool->getCallCount());
		$this->assertSame(['id' => 42, 'title' => 'Natural approval'], $tool->getLastArguments());
	}


	public function testNaturalLanguageRevisionReentersApprovalBeforeExecution(): void {
		$tool = new ApprovalMutationTool();
		[$orchestrator, $resumeService] = $this->createHarness();
		$firstResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->toolCallResponse('call-natural-revise', 'update_record', ['id' => 42, 'title' => 'Original title'])]),
			[['role' => 'user', 'content' => 'Update record 42.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);
		$firstRequest = $firstResult->getInteractionRequests()[0];

		$revisedResult = $orchestrator->run(
			new ApprovalQueueChatModel([
				$this->interactionResolutionResponse('resolved', [[
					'request_id' => $firstRequest->getId(),
					'decision' => AgentInteractionResponse::DECISION_SUBMIT,
					'input' => ['id' => 42, 'title' => 'Changed title']
				]])
			]),
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
			$resumeService->prepare(new AgentResume(
				$firstResult->getResumeHandle(),
				[],
				'Ändere bitte den Titel auf Changed title.'
			))
		);

		$this->assertTrue($revisedResult->isSuspended());
		$this->assertSame(AgentExecutionStatus::AWAITING_APPROVAL, $revisedResult->getExecutionStatus());
		$this->assertNotSame($firstResult->getResumeHandle(), $revisedResult->getResumeHandle());
		$this->assertSame(0, $tool->getCallCount());
		$this->assertSame(
			['Record' => '42', 'New title' => 'Changed title'],
			$revisedResult->getInteractionRequests()[0]->getSummary()
		);

		$revisedRequest = $revisedResult->getInteractionRequests()[0];
		$finalResult = $orchestrator->run(
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
			$resumeService->prepare(new AgentResume($revisedResult->getResumeHandle(), [
				new AgentInteractionResponse($revisedRequest->getId(), AgentInteractionResponse::DECISION_APPROVE)
			]))
		);

		$this->assertTrue($finalResult->isCompleted());
		$this->assertSame(1, $tool->getCallCount());
		$this->assertSame(['id' => 42, 'title' => 'Changed title'], $tool->getLastArguments());
	}

	public function testNaturalLanguageDenialDoesNotExecuteMutation(): void {
		$tool = new ApprovalMutationTool();
		[$orchestrator, $resumeService] = $this->createHarness();
		$firstResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->toolCallResponse('call-natural-deny', 'update_record', ['id' => 42, 'title' => 'Denied'])]),
			[['role' => 'user', 'content' => 'Update record 42.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);
		$request = $firstResult->getInteractionRequests()[0];

		$result = $orchestrator->run(
			new ApprovalQueueChatModel([
				$this->interactionResolutionResponse('resolved', [[
					'request_id' => $request->getId(),
					'decision' => AgentInteractionResponse::DECISION_DENY,
					'input' => []
				]]),
				$this->terminalResponse()
			]),
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
			$resumeService->prepare(new AgentResume($firstResult->getResumeHandle(), [], 'nein, lass das'))
		);

		$this->assertTrue($result->isCompleted());
		$this->assertSame(0, $tool->getCallCount());
	}

	public function testUnclearNaturalLanguageResponseKeepsSameHandleReusable(): void {
		$tool = new ApprovalMutationTool();
		[$orchestrator, $resumeService] = $this->createHarness();
		$firstResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->toolCallResponse('call-natural-retry', 'update_record', ['id' => 42, 'title' => 'Retry'])]),
			[['role' => 'user', 'content' => 'Update record 42.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);
		$request = $firstResult->getInteractionRequests()[0];
		$handle = $firstResult->getResumeHandle();

		$unclearResult = $orchestrator->run(
			new ApprovalQueueChatModel([
				$this->interactionResolutionResponse('unclear', [], 'The reply does not contain a clear decision.')
			]),
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
			$resumeService->prepare(new AgentResume($handle, [], 'was genau?'))
		);

		$this->assertTrue($unclearResult->isSuspended());
		$this->assertSame($handle, $unclearResult->getResumeHandle());
		$this->assertSame($request->getId(), $unclearResult->getInteractionRequests()[0]->getId());
		$this->assertSame(0, $tool->getCallCount());

		$approvedResult = $orchestrator->run(
			new ApprovalQueueChatModel([
				$this->interactionResolutionResponse('resolved', [[
					'request_id' => $request->getId(),
					'decision' => AgentInteractionResponse::DECISION_APPROVE,
					'input' => []
				]]),
				$this->terminalResponse()
			]),
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
			$resumeService->prepare(new AgentResume($handle, [], 'ja, mach das'))
		);

		$this->assertTrue($approvedResult->isCompleted());
		$this->assertSame(1, $tool->getCallCount());
	}

	public function testInternalMutationExplicitlyRunsWithoutApprovalOrCommitGuard(): void {
		$tool = new InternalMutationTool();
		[$orchestrator] = $this->createHarness();
		$result = $orchestrator->run(
			new ApprovalQueueChatModel([
				$this->toolCallResponse('call-internal', 'internal_write', ['value' => 'Fu ruft tut']),
				$this->terminalResponse()
			]),
			[['role' => 'user', 'content' => 'Store internal agent knowledge.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);

		$this->assertTrue($result->isCompleted());
		$this->assertFalse($result->isSuspended());
		$this->assertSame(1, $tool->getCallCount());
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


	public function testResourceVersionChangeAfterApprovalBlocksMutation(): void {
		$tool = new ApprovalMutationTool();
		[$orchestrator, $resumeService] = $this->createHarness();
		$firstResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->toolCallResponse('call-stale', 'update_record', ['id' => 42, 'title' => 'Stale'])]),
			[['role' => 'user', 'content' => 'Update record 42.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);
		$tool->setVersion(2);
		$request = $firstResult->getInteractionRequests()[0];
		$resume = new AgentResume($firstResult->getResumeHandle(), [
			new AgentInteractionResponse($request->getId(), AgentInteractionResponse::DECISION_APPROVE)
		]);

		$result = $orchestrator->run(
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

		$this->assertTrue($result->isCompleted());
		$this->assertSame(0, $tool->getCallCount());
		$this->assertStringContainsString('mutation_stale', json_encode($result->getMessages(), JSON_THROW_ON_ERROR));
	}

	public function testAuthorizationLossAfterApprovalBlocksMutation(): void {
		$tool = new ApprovalMutationTool();
		[$orchestrator, $resumeService] = $this->createHarness();
		$firstResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->toolCallResponse('call-auth', 'update_record', ['id' => 42, 'title' => 'Denied'])]),
			[['role' => 'user', 'content' => 'Update record 42.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);
		$tool->setAuthorized(false);
		$request = $firstResult->getInteractionRequests()[0];
		$resume = new AgentResume($firstResult->getResumeHandle(), [
			new AgentInteractionResponse($request->getId(), AgentInteractionResponse::DECISION_APPROVE)
		]);

		$result = $orchestrator->run(
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

		$this->assertTrue($result->isCompleted());
		$this->assertSame(0, $tool->getCallCount());
		$this->assertStringContainsString('mutation_unauthorized', json_encode($result->getMessages(), JSON_THROW_ON_ERROR));
	}


	public function testRequiredCommitGuardFailsClosedWhenToolDoesNotImplementContract(): void {
		$tool = new ApprovalLegacyMutationTool(true);
		[$orchestrator] = $this->createHarness();
		$result = $orchestrator->run(
			new ApprovalQueueChatModel([$this->toolCallResponse('call-unguarded', 'legacy_update_record', ['id' => 42])]),
			[['role' => 'user', 'content' => 'Update record 42.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);

		$this->assertSame('mutation_commit_snapshot_failed', $result->getFailureCode());
		$this->assertSame(0, $tool->getCallCount());
	}

	public function testExpectedMutationPreflightRejectionBecomesStructuredToolFailure(): void {
		$tool = new ApprovalPreflightRejectingMutationTool();
		[$orchestrator, , , $events] = $this->createHarness();
		$result = $orchestrator->run(
			new ApprovalQueueChatModel([
				$this->toolCallResponse('call-preflight', 'update_missing_course', ['ref_id' => 404]),
				$this->terminalResponse()
			]),
			[['role' => 'user', 'content' => 'Update course 404.']],
			$tool->getToolDefinitions(),
			[$tool],
			new AgentContext()
		);

		$this->assertTrue($result->isCompleted());
		$this->assertFalse($result->isSuspended());
		$this->assertSame('', $result->getFailureCode());
		$this->assertSame(0, $tool->getCallCount());
		$this->assertSame([], $result->getInteractionRequests());
		$this->assertStringContainsString(
			'mutation_preflight_rejected',
			json_encode($result->getMessages(), JSON_THROW_ON_ERROR)
		);
		$this->assertStringContainsString(
			'No active course exists for ref_id 404.',
			json_encode($result->getMessages(), JSON_THROW_ON_ERROR)
		);
		$this->assertSame(
			[MissionBayAgentActionAuditEvent::TYPE_PREFLIGHT_REJECTED],
			array_map(
				static fn(MissionBayAgentActionAuditEvent $event): string => $event->getType(),
				$events->getAuditEvents()
			)
		);
	}

	public function testLegacyMutationCanExplicitlyOptOutOfCommitGuard(): void {
		$tool = new ApprovalLegacyMutationTool(false);
		[$orchestrator, $resumeService] = $this->createHarness();
		$firstResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->toolCallResponse('call-optout', 'legacy_update_record', ['id' => 42])]),
			[['role' => 'user', 'content' => 'Update record 42.']],
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

		$this->assertSame(1, $tool->getCallCount());
	}

	public function testTypedAuditEventsCoverApprovalAndCommit(): void {
		$tool = new ApprovalMutationTool();
		[$orchestrator, $resumeService, $repository, $events] = $this->createHarness();
		$firstResult = $orchestrator->run(
			new ApprovalQueueChatModel([$this->toolCallResponse('call-audit', 'update_record', ['id' => 42, 'title' => 'Audit'])]),
			[['role' => 'user', 'content' => 'Update record 42.']],
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

		$types = array_map(
			static fn(MissionBayAgentActionAuditEvent $event): string => $event->getType(),
			$events->getAuditEvents()
		);
		$this->assertSame([
			MissionBayAgentActionAuditEvent::TYPE_APPROVAL_REQUESTED,
			MissionBayAgentActionAuditEvent::TYPE_APPROVAL_GRANTED,
			MissionBayAgentActionAuditEvent::TYPE_COMMIT_ALLOWED,
			MissionBayAgentActionAuditEvent::TYPE_COMMIT_SUCCEEDED
		], $types);
	}

	/** @return array{0:AgentToolOrchestrator,1:AgentActionResumeService,2:StateStoreAgentSuspensionRepository,3:ApprovalSilentEventManager} */
	private function createHarness(): array {
		$repository = new StateStoreAgentSuspensionRepository(new ApprovalMemoryStateStore());
		$fingerprint = new AgentActionFingerprint();
		$events = new ApprovalSilentEventManager();
		$commitGuard = new AgentMutationCommitGuardService($fingerprint, $events);
		$resumeService = new AgentActionResumeService($fingerprint, $repository, $events);
		$reviewService = new AgentActionReviewService($fingerprint, $repository, 900, $commitGuard, $events);
		$stages = [
			new AgentModelDecisionStage(),
			new AgentActionPolicyStage(
				new StaticAgentActionPolicyResolver([
					new MutationApprovalAgentActionPolicy(),
					new AllowAllAgentActionPolicy()
				]),
				$fingerprint,
				$reviewService,
				new AgentToolContractValidationService(),
				new AgentCapabilitySelectionGuardService(),
				'action-policy',
				'action-policy',
				['mutation-approval-actions', 'allow-all-actions']
			),
			new AgentToolExecutionStage($events, mutationCommitGuardService: $commitGuard),
			new AgentToolObservationStage()
		];

		return [new AgentToolOrchestrator(null, null, $stages, $resumeService), $resumeService, $repository, $events];
	}

	/** @param array<string,mixed> $arguments @return array<string,mixed> */
	private function toolCallResponse(string $id, string $name, array $arguments, ?string $content = null): array {
		return [
			'choices' => [[
				'message' => [
					'role' => 'assistant',
					'content' => $content,
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


	/**
	 * @param array<int,array<string,mixed>> $responses
	 * @return array<string,mixed>
	 */
	private function interactionResolutionResponse(string $status, array $responses, string $reason = ''): array {
		return [
			'choices' => [[
				'message' => [
					'role' => 'assistant',
					'content' => json_encode([
						'status' => $status,
						'reason' => $reason,
						'responses' => $responses
					], JSON_THROW_ON_ERROR)
				]
			]]
		];
	}

	/** @return array<string,mixed> */
	private function terminalResponse(): array {
		return ['choices' => [['message' => ['role' => 'assistant', 'content' => 'TOOL_PHASE_COMPLETE']]]];
	}
}

require_once __DIR__ . '/InternalMutationTool.php';
require_once __DIR__ . '/ApprovalMutationTool.php';
require_once __DIR__ . '/ApprovalPreflightRejectingMutationTool.php';
require_once __DIR__ . '/ApprovalLegacyMutationTool.php';
require_once __DIR__ . '/ApprovalQueueChatModel.php';
require_once __DIR__ . '/ApprovalSilentEventManager.php';
require_once __DIR__ . '/ApprovalMemoryStateStore.php';
