<?php declare(strict_types=1);

namespace Base3\MissionBay\Test\Orchestrator\Fixture;

use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\AssistantFoundation\Dto\AgentToolCacheConfig;
use Base3\AssistantFoundation\Dto\AgentToolCacheRule;
use Base3\AssistantFoundation\Dto\AgentInteractionResponse;
use Base3\AssistantFoundation\Dto\AgentSuspensionState;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\MissionBay\Exception\AgentSubAgentSuspendedException;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use Base3\AssistantFoundation\Dto\AgentResume;
use Base3\AssistantFoundation\Exception\AgentSuspensionRepositoryException;
use Base3\AssistantRuntime\Service\StateStoreAgentSuspensionRepository;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Cache\AgentToolCacheKeyBuilder;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\AgentToolOrchestrator;
use Base3\MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use Base3\MissionBay\Orchestrator\Service\AgentActionResumeService;
use Base3\MissionBay\Orchestrator\Service\AgentActionReviewService;
use Base3\MissionBay\Orchestrator\Service\AgentCapabilitySelectionGuardService;
use Base3\MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use Base3\MissionBay\Orchestrator\Service\AgentToolResultCacheService;
use Base3\MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use Base3\MissionBay\Orchestrator\Stage\AgentModelDecisionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolExecutionStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolObservationStage;
use Base3\MissionBay\Policy\AllowAllAgentActionPolicy;
use Base3\MissionBay\Policy\MutationApprovalAgentActionPolicy;
use Base3\MissionBay\Resource\AgentTool\Agent\RunAvailableAgentTool;
use Base3\MissionBay\Resource\AgentTool\Agent\RunConfiguredAgentTool;
use Base3\MissionBay\Test\Orchestrator\ApprovalMemoryStateStore;
use Base3\MissionBay\Test\Orchestrator\ApprovalMutationTool;
use Base3\MissionBay\Test\Orchestrator\ApprovalQueueChatModel;
use Base3\MissionBay\Test\Orchestrator\ApprovalSilentEventManager;
use Base3\MissionBay\Test\Orchestrator\AgentToolCacheCoverageRecordingCache;
use Base3\MissionBay\Test\Resource\AgentTool\Agent\RunConfiguredAgentTestConfigResolver;

/** Real A to B to C pipeline using the configured and catalog sub-agent tools. */
final class AgentDelegationHitlScenario {

	public static function run(): void {
		self::verifyDecision(AgentInteractionResponse::DECISION_APPROVE, 1);
		self::verifyDecision(AgentInteractionResponse::DECISION_DENY, 0);
		self::verifySequentialApprovals();
		self::verifyExpiredChildCannotBePublished();
		self::verifyDelegatedResumeBypassesToolCache();
		self::verifyChangedParentActionPolicyBlocksResume();
	}

	private static function check(bool $condition, string $message): void {
		if (!$condition) {
			throw new \RuntimeException($message);
		}
	}

	/** @return array{0:AgentToolOrchestrator,1:AgentActionResumeService,2:StateStoreAgentSuspensionRepository} */
	private static function makePipeline(bool $mutating = false): array {
		$repo = new StateStoreAgentSuspensionRepository(new ApprovalMemoryStateStore());
		$fingerprint = new AgentActionFingerprint();
		$events = new ApprovalSilentEventManager();
		$review = new AgentActionReviewService($fingerprint, $repo, 900, eventManager: $events);
		$resume = new AgentActionResumeService($fingerprint, $repo, $events);
		$policies = $mutating
			? [new MutationApprovalAgentActionPolicy(new \Base3\MissionBay\Test\Policy\Fixture\PolicyTestAgentConfigurations(), new \Base3\MissionBay\Orchestrator\AgentActionFingerprint(), new \Base3\MissionBay\Test\Orchestrator\ApprovalMemoryStateStore()), new AllowAllAgentActionPolicy()]
			: [new AllowAllAgentActionPolicy()];
		$policyIds = $mutating ? ['mutation-approval-actions', 'allow-all-actions'] : ['allow-all-actions'];
		$orchestrator = new AgentToolOrchestrator(null, null, [
			new AgentModelDecisionStage(),
			new AgentActionPolicyStage(
				new StaticAgentActionPolicyResolver($policies),
				$fingerprint,
				$review,
				new AgentToolContractValidationService(),
				new AgentCapabilitySelectionGuardService(),
				'action-policy',
				'action-policy',
				$policyIds
			),
			new AgentToolExecutionStage($events, $review),
			new AgentToolObservationStage()
		], $resume);
		return [$orchestrator, $resume, $repo];
	}

	/** @param array<int,array<string,mixed>> $calls @return array<string,mixed> */
	private static function toolResponse(array $calls): array {
		$items = [];
		foreach ($calls as $call) {
			$items[] = [
				'id' => $call['id'],
				'function' => ['name' => $call['name'], 'arguments' => json_encode($call['arguments'], JSON_THROW_ON_ERROR)]
			];
		}
		return ['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => $items]]]];
	}

	/** @return array<string,mixed> */
	private static function terminal(): array {
		return ['choices' => [['message' => ['role' => 'assistant', 'content' => 'Top-level completed.']]]];
	}

	/** @return array{AgentDelegationExecutionService,ApprovalMutationTool,AgentDelegationCountTool,AgentToolOrchestrator,AgentActionResumeService,StateStoreAgentSuspensionRepository,array,array} */
	private static function nestedHierarchy(bool $twoApprovals = false): array {
		$execution = new AgentDelegationExecutionService();
		$configuration = new AgentDelegationAgentConfigurations();
		$resolver = new RunConfiguredAgentTestConfigResolver();
		$mutation = new ApprovalMutationTool();
		[$c, $cResume] = self::makePipeline(true);
		$followup = $twoApprovals ? [[
			'id' => 'call-c-second', 'name' => 'update_record', 'arguments' => ['id' => 43, 'title' => 'Second nested mutation']
		]] : [];
		$execution->registerAgent('agent-c', $c, $cResume, [$mutation], [[
			'id' => 'call-c-leaf', 'name' => 'update_record', 'arguments' => ['id' => 42, 'title' => 'Nested mutation']
		]], $followup);
		$toC = new RunConfiguredAgentTool($resolver, $configuration, $execution, 'delegate-c');
		$toC->setConfig(['agent_id' => 'agent-c', 'tool_name' => 'delegate_c']);
		[$b, $bResume] = self::makePipeline();
		$execution->registerAgent('agent-b', $b, $bResume, [$toC], [[
			'id' => 'call-b-delegate', 'name' => 'delegate_c', 'arguments' => ['user_prompt' => 'Update record 42']
		]]);

		$catalog = new RunAvailableAgentTool($resolver, $configuration, $execution, 'delegate-b-catalog');
		$catalog->setConfig(['tool_prefix' => 'agent_catalog']);
		$counter = new AgentDelegationCountTool();
		[$a, $aResume, $aRepository] = self::makePipeline();
		$tools = [$counter, $catalog];
		$definitions = array_merge($counter->getToolDefinitions(), $catalog->getToolDefinitions());
		return [$execution, $mutation, $counter, $a, $aResume, $aRepository, $tools, $definitions];
	}

	private static function verifyDecision(string $decision, int $expectedMutations): void {
		[$execution, $mutation, $counter, $a, $aResume, $aRepository, $tools, $definitions] = self::nestedHierarchy();
		$first = $a->run(
			new ApprovalQueueChatModel([self::toolResponse([
				['id' => 'call-a-count', 'name' => 'count_once', 'arguments' => []],
				['id' => 'call-a-delegate', 'name' => 'agent_catalog_run', 'arguments' => ['agent_id' => 'agent-b', 'user_prompt' => 'Ask C to update record']]
			])]),
			[['role' => 'user', 'content' => 'Perform nested test']],
			$definitions,
			$tools,
			new AgentContext()
		);
		self::check($first->isSuspended(), 'A did not suspend when C requested approval: ' . $first->getFailureCode() . ' / ' . $first->getFailureMessage());
		self::check($counter->calls === 1, 'An earlier A action did not execute before delegated suspension.');
		self::check($mutation->getCallCount() === 0, 'The leaf mutated data before approval.');
		self::check(count($first->getInteractionRequests()) === 1, 'The outer suspension lost the leaf interaction.');
		self::check($execution->calls === ['agent-b' => 1, 'agent-c' => 1], 'Sub-agent invocation count is incorrect.');

		$handle = $first->getResumeHandle();
		$claim = $aRepository->claim($handle);
		$state = $claim->getSuspension()->getState();
		$delegation = $state['delegated_resume'] ?? [];
		self::check(($delegation['call_id'] ?? '') === 'call-a-delegate', 'Outer suspension lost its waiting call.');
		self::check(($state['pending_tool_calls'][0]['id'] ?? '') === 'call-a-delegate', 'Completed tool was not excluded from pending batch.');
		self::check(count($state['tool_results'] ?? []) === 1, 'Completed tool result was not retained.');
		self::check($handle !== ($delegation['child_resume_handle'] ?? null), 'The parent exposed a child handle.');
		$outside = json_encode([
			$first->getInteractionRequests()[0]->toArray(),
			$first->getAgentResult()?->getState()->getSuspension()?->toArray()
		], JSON_THROW_ON_ERROR);
		self::check(!str_contains($outside, (string)$delegation['child_resume_handle']), 'Internal child handle leaked into public agent result.');
		$blockedSecondClaim = false;
		try {
			$aRepository->claim($handle);
		} catch (AgentSuspensionRepositoryException) {
			$blockedSecondClaim = true;
		}
		self::check($blockedSecondClaim, 'Simultaneous claims were accepted.');
		$aRepository->release($claim);

		$request = $first->getInteractionRequests()[0];
		$badPrepared = $aResume->prepare(new AgentResume($handle, [
			new AgentInteractionResponse('unknown-interaction-id', $decision)
		]));
		$invalid = $a->run(
			new ApprovalQueueChatModel([self::terminal()]),
			[],
			$definitions,
			$tools,
			new AgentContext(),
			resume: $badPrepared
		);
		self::check($invalid->hasFailure(), 'Unknown delegated interaction ID was not rejected.');
		self::check($counter->calls === 1 && $mutation->getCallCount() === 0, 'Unknown response caused a tool side effect.');
		$prepared = $aResume->prepare(new AgentResume($handle, [
			new AgentInteractionResponse($request->getId(), $decision)
		]));
		$result = $a->run(
			new ApprovalQueueChatModel([self::terminal()]),
			[],
			$definitions,
			$tools,
			new AgentContext(),
			resume: $prepared
		);
		self::check($result->isCompleted(), 'A did not complete after delegated decision: ' . $result->getFailureCode() . ' / ' . $result->getFailureMessage());
		self::check($mutation->getCallCount() === $expectedMutations, 'The leaf executed despite denial or failed to run after approval.');
		self::check($counter->calls === 1, 'Previously completed parent tool was repeated during resume.');
		self::check(($execution->resumes['agent-b'] ?? 0) === 1 && ($execution->resumes['agent-c'] ?? 0) === 1, 'Both child levels were not resumed exactly once.');

		$blockedReplay = false;
		try {
			$aResume->prepare(new AgentResume($handle, [new AgentInteractionResponse($request->getId(), $decision)]));
		} catch (AgentSuspensionRepositoryException) {
			$blockedReplay = true;
		}
		self::check($blockedReplay, 'The consumed parent handle could be replayed.');
	}
	private static function verifySequentialApprovals(): void {
		[$execution, $mutation, $counter, $a, $aResume, $aRepository, $tools, $definitions] = self::nestedHierarchy(true);
		$first = $a->run(
			new ApprovalQueueChatModel([self::toolResponse([
				['id' => 'call-a-count', 'name' => 'count_once', 'arguments' => []],
				['id' => 'call-a-delegate', 'name' => 'agent_catalog_run', 'arguments' => ['agent_id' => 'agent-b']]
			])]),
			[['role' => 'user', 'content' => 'Two nested operations']],
			$definitions,
			$tools,
			new AgentContext()
		);
		self::check($first->isSuspended(), 'First nested approval was not requested.');
		$firstRequest = $first->getInteractionRequests()[0];
		$firstPrepared = $aResume->prepare(new AgentResume($first->getResumeHandle(), [
			new AgentInteractionResponse($firstRequest->getId(), AgentInteractionResponse::DECISION_APPROVE)
		]));
		$second = $a->run(
			new ApprovalQueueChatModel([self::terminal()]),
			[],
			$definitions,
			$tools,
			new AgentContext(),
			resume: $firstPrepared
		);
		self::check($second->isSuspended(), 'The second nested tool request did not suspend A again: ' . $second->getFailureCode() . ' / ' . $second->getFailureMessage());
		self::check($second->getResumeHandle() !== $first->getResumeHandle(), 'Second approval reused a consumed parent handle.');
		self::check($mutation->getCallCount() === 1, 'First nested mutation was not executed exactly once.');
		self::check($counter->calls === 1, 'Parent work repeated during the first resumed approval.');
		$secondRequest = $second->getInteractionRequests()[0];
		self::check($secondRequest->getAction()->getId() === 'call-c-second', 'The second review does not refer to the second child action.');
		$finalPrepared = $aResume->prepare(new AgentResume($second->getResumeHandle(), [
			new AgentInteractionResponse($secondRequest->getId(), AgentInteractionResponse::DECISION_APPROVE)
		]));
		$final = $a->run(
			new ApprovalQueueChatModel([self::terminal()]),
			[],
			$definitions,
			$tools,
			new AgentContext(),
			resume: $finalPrepared
		);
		self::check($final->isCompleted(), 'Sequential nested approvals did not complete: ' . $final->getFailureCode() . ' / ' . $final->getFailureMessage());
		self::check($mutation->getCallCount() === 2 && $counter->calls === 1, 'A completed operation was repeated after the second approval.');
		self::check(($execution->resumes['agent-b'] ?? 0) === 2 && ($execution->resumes['agent-c'] ?? 0) === 2, 'Nested continuation depth was not retained across both suspensions.');
	}


	private static function verifyDelegatedResumeBypassesToolCache(): void {
		[$execution, $mutation, $counter, $agent, $resume, $repository, $tools] = self::nestedHierarchy();
		$cache = new AgentToolCacheCoverageRecordingCache();
		$call = new AiToolCall('call-a-delegate', 'agent_catalog_run', ['agent_id' => 'agent-b']);
		$context = new AgentContext(vars: [
			AgentToolLoopContextKeys::TOOL_CACHE_CONFIG => new AgentToolCacheConfig(
				true,
				AgentToolCacheConfig::SCOPE_GLOBAL,
				'',
				'nested-agent',
				262144,
				[new AgentToolCacheRule('agent_catalog_run', 60)]
			),
			AgentToolLoopContextKeys::PENDING_TOOL_CALLS => [$call],
			AgentToolLoopContextKeys::TOOLS => $tools,
			AgentToolLoopContextKeys::ITERATION => 1,
			AgentToolLoopContextKeys::DELEGATED_RESUME => [
				'call_id' => 'call-a-delegate',
				'tool_name' => 'agent_catalog_run',
				'resume' => new AgentResume('only-test', [])
			]
		]);
		$service = new AgentToolResultCacheService($cache, new ApprovalSilentEventManager(), new AgentToolCacheKeyBuilder());
		$result = $service->process($context, AgentToolResultCacheService::CHECKPOINT_LOOKUP);
		self::check($cache->readKeys === [], 'Resuming a delegated tool unexpectedly read the tool result cache.');
		self::check(count($result->getPatch()[AgentToolLoopContextKeys::PENDING_TOOL_CALLS] ?? []) === 1, 'A cached result replaced the pending delegated call.');
		self::check(($result->getMetadata()['tool_cache']['bypassed'] ?? 0) === 1, 'Delegated cache bypass was not recorded.');
	}

	private static function verifyChangedParentActionPolicyBlocksResume(): void {
		[$agent, $resume, $repo] = self::makePipeline();
		$fingerprint = new AgentActionFingerprint();
		$review = new AgentActionReviewService($fingerprint, $repo);
		$call = new AiToolCall('call-a-delegate', 'count_once', []);
		$tool = new AgentDelegationCountTool();
		$definitions = $tool->getToolDefinitions();
		$definitions[0]['function']['annotations'] = ['mutation' => true];
		$stage = new AgentActionPolicyStage(
			new StaticAgentActionPolicyResolver([new MutationApprovalAgentActionPolicy(new \Base3\MissionBay\Test\Policy\Fixture\PolicyTestAgentConfigurations(), new \Base3\MissionBay\Orchestrator\AgentActionFingerprint(), new \Base3\MissionBay\Test\Orchestrator\ApprovalMemoryStateStore())]),
			$fingerprint,
			$review,
			new AgentToolContractValidationService(),
			new AgentCapabilitySelectionGuardService(),
			policyIds: ['mutation-approval-actions']
		);
		$context = new AgentContext(vars: [
			AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_TOOLS,
			AgentToolLoopContextKeys::PENDING_TOOL_CALLS => [$call],
			AgentToolLoopContextKeys::TOOLS => [$tool],
			AgentToolLoopContextKeys::TOOL_DEFINITIONS => $definitions,
			AgentToolLoopContextKeys::DELEGATED_RESUME => [
				'call_id' => $call->getId(),
				'tool_name' => $call->getName(),
				'resume' => new AgentResume('only-test', [])
			]
		]);
		$result = $stage->process($context);
		self::check(
			($result->getPatch()[AgentToolLoopContextKeys::FAILURE_CODE] ?? '') === 'delegated_resume_policy_changed',
			'Changing parent action policy did not block a delegated resume.'
		);
		self::check($tool->calls === 0, 'Tool executed after a new parent action policy required approval.');
		$context->setVar(AgentToolLoopContextKeys::DELEGATED_RESUME, [
			'call_id' => 'different-call',
			'tool_name' => $call->getName(),
			'resume' => new AgentResume('only-test', [])
		]);
		$definitions[0]['function']['annotations'] = ['readOnlyHint' => true];
		$context->setVar(AgentToolLoopContextKeys::TOOL_DEFINITIONS, $definitions);
		$result = $stage->process($context);
		self::check(
			($result->getPatch()[AgentToolLoopContextKeys::FAILURE_CODE] ?? '') === 'delegated_resume_not_authorized',
			'A delegation mismatch was not rejected at the parent action policy stage.'
		);
	}

	private static function verifyExpiredChildCannotBePublished(): void {
		[$agent, $resume, $repository] = self::makePipeline();
		$fingerprint = new AgentActionFingerprint();
		$review = new AgentActionReviewService($fingerprint, $repository);
		$action = new AgentAction('leaf-expired', AgentAction::TYPE_TOOL_CALL, 'update_record', ['id' => 7]);
		$request = new \Base3\AssistantFoundation\Dto\AgentInteractionRequest(
			'expired-review',
			\Base3\AssistantFoundation\Dto\AgentInteractionRequest::KIND_APPROVAL,
			$action,
			$fingerprint->create($action),
			'Approval expired',
			'Confirm this test action?'
		);
		$child = new AgentSuspensionState(true, AgentExecutionStatus::AWAITING_APPROVAL, [$request], 'child-expired-handle');
		$stage = $review->suspendDelegation(
			new AgentContext(),
			new AgentSubAgentSuspendedException($child, gmdate('c', time() - 30)),
			new AiToolCall('call-parent-expired', 'delegate_c', []),
			[]
		);
		self::check(
			($stage->getPatch()[AgentToolLoopContextKeys::FAILURE_CODE] ?? '') === 'delegated_suspension_expired',
			'An already expired child suspension was offered for parent approval.'
		);
	}

}
