<?php declare(strict_types=1);

use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentActionDecision;
use Base3\AssistantFoundation\Dto\AgentInteractionResponse;
use Base3\AssistantFoundation\Dto\AgentResume;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\AssistantRuntime\Service\StateStoreAgentSuspensionRepository;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\Service\AgentActionResumeService;
use Base3\MissionBay\Orchestrator\Service\AgentActionReviewService;
use Base3\MissionBay\Orchestrator\Service\AgentMutationCommitGuardService;
use Base3\MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use Base3\MissionBay\Orchestrator\Service\AgentCapabilitySelectionGuardService;
use Base3\MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use Base3\MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use Base3\MissionBay\Test\Orchestrator\ApprovalMutationTool;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use Base3\MissionBay\Policy\MutationApprovalAgentActionPolicy;
use Base3\MissionBay\Test\Orchestrator\ApprovalMemoryStateStore;
use Base3\MissionBay\Test\Policy\Fixture\PolicyTestAgentConfigurations;

$root = dirname(__DIR__, 4);
foreach (['DIR_ROOT' => $root . '/', 'DIR_PLUGIN' => $root . '/plugin/', 'DIR_TEST' => $root . '/test/'] as $name => $value) {
	if (!defined($name)) define($name, $value);
}
require_once DIR_ROOT . 'bootstrap/Autoloader.php';
Base3\Bootstrap\Autoloader::register();

function verifyChatApproval(bool $valid, string $reason): void {
	if (!$valid) throw new RuntimeException($reason);
}

$state = new ApprovalMemoryStateStore();
$fingerprint = new AgentActionFingerprint();
$repo = new StateStoreAgentSuspensionRepository($state);
$policy = new MutationApprovalAgentActionPolicy(new PolicyTestAgentConfigurations(), $fingerprint, $state);
$review = new AgentActionReviewService($fingerprint, $repo);
$resumeService = new AgentActionResumeService($fingerprint, $repo, null, null, null, $state);
$definition = [
	'type' => 'function',
	'function' => ['name' => 'remote__write', 'parameters' => ['type' => 'object']],
	'annotations' => ['destructiveHint' => true, 'commitGuardRequired' => false]
];
$action = new AgentAction('chat-action-1', AgentAction::TYPE_TOOL_CALL, 'remote__write', ['record' => 17]);
$context = new AgentContext(null, [
	'agent_id' => 'agent-a',
	'conversation_channel_id' => 'chatbot:shared',
	'conversation_id' => 'conversation-one',
	'agent_suspension_subject' => 'user:one',
	AgentToolLoopContextKeys::TOOL_DEFINITIONS => [$definition]
]);
$first = $policy->evaluate($action, $context);
verifyChatApproval($first->getDecision() === AgentActionDecision::DECISION_REQUIRE_APPROVAL, 'Initial action unexpectedly allowed.');

function makeChatApproval(AgentContext $context, AgentAction $action, AgentActionDecision $decision, string $scope = 'conversation'): array {
	global $review, $resumeService;
	$metadata = array_merge(['policy_implementation' => MutationApprovalAgentActionPolicy::getName()], $decision->getMetadata());
	$decision = new AgentActionDecision($action->getId(), $decision->getDecision(), $decision->getReason(), $metadata);
	$call = new AiToolCall($action->getId(), $action->getName(), $action->getInput());
	$context->setVar(AgentToolLoopContextKeys::ACTION_REVIEW_CANDIDATES, [[
		'action' => $action, 'decision' => $decision, 'tool_call' => $call
	]]);
	$patch = $review->review($context)->getPatch();
	verifyChatApproval(($patch[AgentToolLoopContextKeys::EXECUTION_STATUS] ?? '') === 'awaiting_approval', 'Review did not suspend.');
	$request = $patch[AgentToolLoopContextKeys::INTERACTION_REQUESTS][0];
	$responses = [new AgentInteractionResponse(
		$request->getId(), AgentInteractionResponse::DECISION_APPROVE, [], '',
		$scope === '' ? [] : ['approval_scope' => $scope]
	)];
	$resume = new AgentResume($patch[AgentToolLoopContextKeys::RESUME_HANDLE], $responses);
	$context->setVar(AgentToolLoopContextKeys::RESUME, $resumeService->prepare($resume));
	return [$resumeService->resume($context)->getPatch(), $request, $resume];
}

[$after] = makeChatApproval($context, $action, $first);
verifyChatApproval(count($after[AgentToolLoopContextKeys::PENDING_TOOL_CALLS] ?? []) === 1, 'Explicit approval did not continue tool call.');
$otherArguments = new AgentAction('chat-action-2', AgentAction::TYPE_TOOL_CALL, 'remote__write', ['record' => 999]);
verifyChatApproval($policy->evaluate($otherArguments, $context)->isAllowed(), 'Chat approval did not cover later arguments.');

$otherConversation = new AgentContext(null, [
	'agent_id' => 'agent-a',
	'conversation_channel_id' => 'chatbot:shared',
	'conversation_id' => 'conversation-two',
	'agent_suspension_subject' => 'user:one',
	AgentToolLoopContextKeys::TOOL_DEFINITIONS => [$definition]
]);
verifyChatApproval(!$policy->evaluate($otherArguments, $otherConversation)->isAllowed(), 'Chat approval leaked to another conversation.');
$otherUser = new AgentContext(null, [
	'agent_id' => 'agent-a',
	'conversation_channel_id' => 'chatbot:shared',
	'conversation_id' => 'conversation-one',
	'agent_suspension_subject' => 'user:two',
	AgentToolLoopContextKeys::TOOL_DEFINITIONS => [$definition]
]);
verifyChatApproval(!$policy->evaluate($otherArguments, $otherUser)->isAllowed(), 'Chat approval leaked to another user.');
$changed = $definition;
$changed['annotations']['openWorldHint'] = true;
$context->setVar(AgentToolLoopContextKeys::TOOL_DEFINITIONS, [$changed]);
verifyChatApproval(!$policy->evaluate($otherArguments, $context)->isAllowed(), 'Changed tool definition bypassed review.');
$context->setVar(AgentToolLoopContextKeys::TOOL_DEFINITIONS, [$definition]);
$child = new AgentContext(null, [
	'agent_id' => 'agent-b',
	'conversation_channel_id' => 'sub-agent:agent-b',
	'approval_conversation_scope' => $fingerprint->conversationApprovalScope($context),
	AgentToolLoopContextKeys::TOOL_DEFINITIONS => [$definition]
]);
verifyChatApproval($policy->evaluate($otherArguments, $child)->isAllowed(), 'Nested agent did not inherit the verified chat scope.');

$context->setVar('conversation_id', 'not-current');
$deniedAction = new AgentAction('chat-action-denied', AgentAction::TYPE_TOOL_CALL, 'remote__write', ['record' => 42]);
$deniedDecision = $policy->evaluate($deniedAction, $context);
verifyChatApproval(!$deniedDecision->isAllowed(), 'Other chat unexpectedly preapproved.');
$failed = false;
try {
	makeChatApproval($context, $deniedAction, $deniedDecision, 'invalid');
} catch (Throwable $e) {
	$failed = true;
}
verifyChatApproval($failed || !$policy->evaluate($deniedAction, $context)->isAllowed(), 'Malformed approval scope was persisted.');
verifyChatApproval(!$policy->evaluate($deniedAction, $context)->isAllowed(), 'Rejected scope granted access.');

// Reusable consent never bypasses a native tool's current-state commit guard.
$guardedTool = new ApprovalMutationTool();
$guardedDefinitions = $guardedTool->getToolDefinitions();
$guardedContext = new AgentContext(null, [
	'agent_id' => 'guarded-agent',
	'conversation_channel_id' => 'chatbot:shared',
	'conversation_id' => 'guarded-conversation',
	'agent_suspension_subject' => 'user:one',
	AgentToolLoopContextKeys::TOOL_DEFINITIONS => $guardedDefinitions,
	AgentToolLoopContextKeys::TOOLS => [$guardedTool],
	AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_TOOLS,
	AgentToolLoopContextKeys::PENDING_TOOL_CALLS => [new AiToolCall('guarded-call', 'update_record', ['id' => 3, 'title' => 'New'])]
]);
$guardedKey = $fingerprint->conversationApprovalKey($fingerprint->conversationApprovalScope($guardedContext), 'update_record');
$state->set($guardedKey, [
	'scope' => 'conversation', 'tool' => 'update_record',
	'fingerprint' => $fingerprint->createToolDefinitionFingerprint($guardedDefinitions[0]),
	'approved_at' => gmdate('c')
]);
$guard = new AgentMutationCommitGuardService($fingerprint);
$stage = new AgentActionPolicyStage(
	new StaticAgentActionPolicyResolver([$policy]),
	$fingerprint,
	new AgentActionReviewService($fingerprint, $repo, 900, $guard),
	new AgentToolContractValidationService(),
	new AgentCapabilitySelectionGuardService(),
	'action-policy',
	'action-policy',
	['mutation-approval-actions']
);
$guarded = $stage->process($guardedContext)->getPatch();
$approvedCalls = $guarded[AgentToolLoopContextKeys::PENDING_TOOL_CALLS] ?? [];
verifyChatApproval(count($approvedCalls) === 1, 'Guarded tool did not proceed with previously approved definition.');
verifyChatApproval($guard->validate($approvedCalls[0], $guardedContext)->isAllowed(), 'Fresh exact-action commit snapshot was not valid.');
$guardedTool->setAuthorized(false);
verifyChatApproval(!$guard->validate($approvedCalls[0], $guardedContext)->isAllowed(), 'Revoked native tool authorization was bypassed.');
$guardedTool->setAuthorized(true);
$guardedTool->setVersion(100);
verifyChatApproval(!$guard->validate($approvedCalls[0], $guardedContext)->isAllowed(), 'Stale resource snapshot was bypassed.');

echo "Chat-scoped tool approval, isolation, inherited scope and guarded execution: OK\n";
