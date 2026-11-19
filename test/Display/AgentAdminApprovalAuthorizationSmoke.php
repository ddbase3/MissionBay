<?php declare(strict_types=1);

/** Real HTTP authorization boundary, real suspension and real resume. No AI or remote tools. */

use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\AssistantFoundation\Dto\AiToolCall;
use Base3\AssistantFoundation\Dto\AgentSuspensionScope;
use Base3\AssistantRuntime\Service\StateStoreAgentSuspensionRepository;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationDecision;
use Base3\CoreFoundation\Usermanager\User;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Display\AgentAdminDisplay;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\Service\AgentActionReviewService;
use Base3\MissionBay\Orchestrator\Service\AgentActionResumeService;
use Base3\MissionBay\Orchestrator\Service\AgentCapabilitySelectionGuardService;
use Base3\MissionBay\Orchestrator\Service\AgentToolContractValidationService;
use Base3\MissionBay\Orchestrator\Policy\StaticAgentActionPolicyResolver;
use Base3\MissionBay\Orchestrator\Stage\AgentActionPolicyStage;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use Base3\MissionBay\Policy\MutationApprovalAgentActionPolicy;
use Base3\MissionBay\Test\Display\Fixture\AgentAdminHitlAgentConfigurations;
use Base3\MissionBay\Test\Display\Fixture\AgentAdminHitlAuthorization;
use Base3\MissionBay\Test\Display\Fixture\AgentAdminHitlConfigForm;
use Base3\MissionBay\Test\Display\Fixture\AgentAdminHitlRealResumeExecution;
use Base3\MissionBay\Test\Display\Fixture\AgentAdminHitlRequest;
use Base3\MissionBay\Test\Display\Fixture\AgentAdminHitlTranslation;
use Base3\MissionBay\Test\Display\Fixture\AgentAdminHitlUsermanager;
use Base3\MissionBay\Test\Orchestrator\ApprovalMemoryStateStore;
use Base3\MissionBay\Test\Orchestrator\ApprovalMutationTool;

$root = dirname(__DIR__, 4);
foreach (['DIR_ROOT' => $root . '/', 'DIR_PLUGIN' => $root . '/plugin/', 'DIR_TEST' => $root . '/test/'] as $name => $value) {
	if (!defined($name)) define($name, $value);
}
require_once DIR_ROOT . 'bootstrap/Autoloader.php';
Base3\Bootstrap\Autoloader::register();

function verifyAdminAuth(bool $condition, string $message): void {
	if (!$condition) throw new RuntimeException($message);
}

$store = new ApprovalMemoryStateStore();
$repository = new StateStoreAgentSuspensionRepository($store);
$fingerprint = new AgentActionFingerprint();
$configurations = new AgentAdminHitlAgentConfigurations();
$authorization = new AgentAdminHitlAuthorization();
$request = new AgentAdminHitlRequest();
$users = new AgentAdminHitlUsermanager();
$users->actor = User::fromArray(['id' => 42]);
$tool = new ApprovalMutationTool();
$toolDefinition = $tool->getToolDefinitions()[0];
$channel = 'agent-admin:jarvis';
$conversationId = 'manual:' . hash('sha256', 'test-xrm' . "\0" . '42');
$subject = 'agent-admin:test-xrm:user:42';
$scopeId = AgentSuspensionScope::forConversation($channel, $conversationId, $subject);
$context = new AgentContext(null, [
	'agent_id' => 'jarvis',
	'conversation_channel_id' => $channel,
	'conversation_id' => $conversationId,
	'agent_suspension_subject' => $subject,
	AgentToolLoopContextKeys::TOOL_DEFINITIONS => [$toolDefinition],
	AgentToolLoopContextKeys::TOOLS => [$tool],
	AgentToolLoopContextKeys::PHASE => AgentToolLoopContextKeys::PHASE_TOOLS,
	AgentToolLoopContextKeys::PENDING_TOOL_CALLS => [new AiToolCall('write-1', 'update_record', ['id' => 17, 'title' => 'Approved title'])]
]);
$policy = new MutationApprovalAgentActionPolicy($configurations, $fingerprint, $store);
$stage = new AgentActionPolicyStage(
	new StaticAgentActionPolicyResolver([$policy]),
	$fingerprint,
	new AgentActionReviewService($fingerprint, $repository),
	new AgentToolContractValidationService(),
	new AgentCapabilitySelectionGuardService(),
	'action-policy',
	'action-policy',
	['mutation-approval-actions']
);
$pendingPatch = $stage->process($context)->getPatch();
verifyAdminAuth(($pendingPatch[AgentToolLoopContextKeys::EXECUTION_STATUS] ?? '') === AgentExecutionStatus::AWAITING_APPROVAL, 'Real action-policy stage did not suspend.');
$handle = $pendingPatch[AgentToolLoopContextKeys::RESUME_HANDLE] ?? '';
$pending = $repository->findPending($scopeId);
verifyAdminAuth($pending !== null && $pending->getResumeHandle() === $handle, 'Real suspension was not scoped to the admin actor.');
$interactionId = $pending->getInteractionRequests()[0]->getId();
$execution = new AgentAdminHitlRealResumeExecution(new AgentActionResumeService($fingerprint, $repository, null, null, null, $store), $context);
$display = (new ReflectionClass(AgentAdminDisplay::class))->newInstanceWithoutConstructor();
foreach ([
	'request' => $request,
	'agentConfigurations' => $configurations,
	'agentConfigFormService' => new AgentAdminHitlConfigForm(),
	'agentExecutionService' => $execution,
	'suspensionRepository' => $repository,
	'usermanager' => $users,
	'settingsAuthorization' => $authorization,
	'translation' => new AgentAdminHitlTranslation(),
	'policyOptions' => []
] as $property => $value) {
	(new ReflectionProperty(AgentAdminDisplay::class, $property))->setValue($display, $value);
}

$request->body = [
	'mode' => 'run',
	'id' => 'jarvis',
	'resume' => ['resume_handle' => $handle, 'responses' => [[
		'request_id' => $interactionId,
		'decision' => 'approve',
		'metadata' => ['approval_scope' => 'agent']
	]]]
];
$before = $configurations->settings;
foreach ([AuthorizationDecision::deny(), AuthorizationDecision::indeterminate()] as $denial) {
	$authorization->decision = $denial;
	verifyAdminAuth($display->getOutput('json') === '', 'Non-allow feature decision must block durable approval.');
	verifyAdminAuth($execution->calls === 0 && $configurations->settings === $before, 'Denied actor modified approval or resumed the run.');
}
verifyAdminAuth(count($authorization->requests) === 2, 'The admin endpoint skipped the authoritative feature check.');
foreach ($authorization->requests as $permissionRequest) {
	verifyAdminAuth($permissionRequest->authority === 'test-xrm'
		&& $permissionRequest->permission->scope === 'feature'
		&& $permissionRequest->permission->permission === 'agentadminsettingstab', 'Admin uses a different permission from agent creation/configuration.');
}
$authorization->decision = AuthorizationDecision::allow();
$validResume = $request->body;
$request->body['resume']['responses'][] = $request->body['resume']['responses'][0];
$duplicate = json_decode($display->getOutput('json'), true);
verifyAdminAuth(is_array($duplicate) && ($duplicate['ok'] ?? true) === false
	&& $execution->calls === 1 && $configurations->settings === $before,
	'Duplicate explicit responses created a grant through the real resume service.');
verifyAdminAuth($repository->findPending($scopeId) !== null, 'Rejected duplicate response consumed the pending approval.');
$request->body = $validResume;
$request->body['resume']['responses'][0]['request_id'] = 'not-the-pending-request';
$foreignRequest = json_decode($display->getOutput('json'), true);
verifyAdminAuth(is_array($foreignRequest) && ($foreignRequest['ok'] ?? true) === false
	&& $execution->calls === 1 && $configurations->settings === $before,
	'Foreign request identifier created a durable grant.');
verifyAdminAuth($repository->findPending($scopeId) !== null, 'Rejected foreign request consumed the pending approval.');
$request->body = $validResume;
$reply = json_decode($display->getOutput('json'), true);
verifyAdminAuth(is_array($reply) && ($reply['ok'] ?? false) === true, 'Authorized admin could not resume original tool action.');
verifyAdminAuth($execution->failureCode === '' && $execution->calls === 2, 'Real resume rejected the admin-approved tool scope.');
verifyAdminAuth(!isset($execution->lastRequest?->getInputs()['resume']['responses'][0]['metadata']['approval_scope']), 'Administrative grant scope leaked into real runtime resume.');
$grant = $configurations->settings['tool_approval_grants']['update_record'] ?? null;
verifyAdminAuth(is_array($grant) && ($grant['approved_by'] ?? '') === 'test-xrm:user:42', 'Authorized actor durable consent was not recorded.');
$laterAction = new AgentAction('write-2', AgentAction::TYPE_TOOL_CALL, 'update_record', ['id' => 999, 'title' => 'Different']);
verifyAdminAuth($policy->evaluate($laterAction, $context)->isAllowed(), 'A later tool call did not reuse durable agent grant.');
verifyAdminAuth($repository->findPending($scopeId) === null, 'Consumed review was still pending.');
$reply = json_decode($display->getOutput('json'), true);
verifyAdminAuth(is_array($reply) && ($reply['ok'] ?? false) === false && $execution->calls === 2, 'The consumed handle could be replayed.');

$request->body = ['mode' => 'revoke_approval', 'id' => 'jarvis', 'tool_name' => 'update_record'];
$authorization->decision = AuthorizationDecision::deny();
verifyAdminAuth($display->getOutput('json') === '' && isset($configurations->settings['tool_approval_grants']['update_record']), 'Unauthorized actor revoked durable consent.');
$authorization->decision = AuthorizationDecision::allow();
$revoke = json_decode($display->getOutput('json'), true);
verifyAdminAuth(is_array($revoke) && ($revoke['ok'] ?? false) === true, 'Authorized agent editor could not revoke the grant.');
verifyAdminAuth(!$policy->evaluate($laterAction, $context)->isAllowed(), 'Revoked durable grant still authorized the tool.');

$request->body = ['mode' => 'create', 'id' => 'jarvis'];
$authorization->decision = AuthorizationDecision::deny();
verifyAdminAuth($display->getOutput('json') === '', 'Agent creation was not protected by the same feature.');

// A failed runtime result cannot mint a durable agent approval.
$authoritative = (new ReflectionMethod(AgentAdminDisplay::class, 'validatePersistentApprovals'));
$good = \Base3\AssistantFoundation\Dto\AgentResume::fromArray([
	'resume_handle' => $handle,
	'responses' => [['request_id' => $interactionId, 'decision' => 'approve', 'metadata' => ['approval_scope' => 'agent']]]
]);
verifyAdminAuth($authoritative->invoke($display, $good, $pending) === ['update_record' => $fingerprint->createToolDefinitionFingerprint($toolDefinition)], 'Live review fingerprint was not validated.');

echo "Admin feature authorization, durable approval, real resume, policy reuse, replay and revocation: OK\n";
