<?php declare(strict_types=1);

/** Dependency-free AgentAdmin run and resume boundary smoke, no real tools. */

use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentExecutionResult;
use Base3\AssistantFoundation\Dto\AgentExecutionStatus;
use Base3\AssistantFoundation\Dto\AgentInteractionRequest;
use Base3\AssistantFoundation\Dto\AgentResult;
use Base3\AssistantFoundation\Dto\AgentState;
use Base3\AssistantFoundation\Dto\AgentSuspensionScope;
use Base3\AssistantFoundation\Dto\AgentSuspensionState;
use Base3\CoreFoundation\Usermanager\User;
use Base3\MissionBay\Display\AgentAdminDisplay;
use Base3\MissionBay\Test\Display\Fixture\AgentAdminHitlAgentConfigurations;
use Base3\MissionBay\Test\Display\Fixture\AgentAdminHitlAuthorization;
use Base3\MissionBay\Test\Display\Fixture\AgentAdminHitlConfigForm;
use Base3\MissionBay\Test\Display\Fixture\AgentAdminHitlExecution;
use Base3\MissionBay\Test\Display\Fixture\AgentAdminHitlSuspensionRepository;
use Base3\MissionBay\Test\Display\Fixture\AgentAdminHitlTranslation;
use Base3\MissionBay\Test\Display\Fixture\AgentAdminHitlUsermanager;

$root = dirname(__DIR__, 4);
foreach (['DIR_ROOT' => $root . '/', 'DIR_PLUGIN' => $root . '/plugin/', 'DIR_TEST' => $root . '/test/'] as $name => $value) {
	if (!defined($name)) define($name, $value);
}
require_once DIR_ROOT . 'bootstrap/Autoloader.php';
Base3\Bootstrap\Autoloader::register();

function checkAdmin(bool $condition, string $message): void {
	if (!$condition) throw new RuntimeException($message);
}

function adminDisplay(AgentAdminHitlExecution $execution, AgentAdminHitlSuspensionRepository $repository, AgentAdminHitlUsermanager $users): AgentAdminDisplay {
	$display = (new ReflectionClass(AgentAdminDisplay::class))->newInstanceWithoutConstructor();
	foreach ([
		'agentConfigurations' => new AgentAdminHitlAgentConfigurations(),
		'agentConfigFormService' => new AgentAdminHitlConfigForm(),
		'agentExecutionService' => $execution,
		'suspensionRepository' => $repository,
		'usermanager' => $users,
		'settingsAuthorization' => new AgentAdminHitlAuthorization(),
		'translation' => new AgentAdminHitlTranslation(),
		'policyOptions' => []
	] as $property => $value) {
		(new ReflectionProperty(AgentAdminDisplay::class, $property))->setValue($display, $value);
	}
	return $display;
}
function runAdmin(AgentAdminDisplay $display, array $payload): array {
	return (new ReflectionMethod(AgentAdminDisplay::class, 'buildRunResponse'))->invoke($display, 'jarvis', $payload);
}

$users = new AgentAdminHitlUsermanager();
$users->actor = User::fromArray(['id' => 42]);
$repository = new AgentAdminHitlSuspensionRepository();
$execution = new AgentAdminHitlExecution();
$display = adminDisplay($execution, $repository, $users);
$ordinary = runAdmin($display, ['user_prompt' => 'Original message']);
checkAdmin($ordinary['ok'] && $ordinary['message_text'] === 'Admin reply' && $execution->calls === 1, 'Ordinary admin execution regressed.');
$context = $execution->lastRequest->getContext();
checkAdmin($context['conversation_channel_id'] === 'agent-admin:jarvis' && str_starts_with($context['conversation_id'], 'manual:') && $context['agent_suspension_subject'] === 'agent-admin:test-xrm:user:42', 'Admin did not bind a server-owned scope.');

$action = new AgentAction('call-1', AgentAction::TYPE_TOOL_CALL, 'remote_action', ['repository' => 'test']);
$review = new AgentInteractionRequest('review-1', AgentInteractionRequest::KIND_APPROVAL, $action, 'fingerprint', 'Tool approval', 'Approve?', ['repository' => 'test'], 'high', ['mutation_commit_snapshot' => ['secret' => 'internal-only']]);
$pending = new AgentSuspensionState(true, AgentExecutionStatus::AWAITING_APPROVAL, [$review], 'one-time-handle');
$repository->pending = $pending;
$repository->scope = AgentSuspensionScope::forConversation($context['conversation_channel_id'], $context['conversation_id'], $context['agent_suspension_subject']);
$execution->calls = 0;
$held = runAdmin($display, ['user_prompt' => 'Try again']);
checkAdmin($held['ok'] && $held['action'] === 'interaction_required' && $held['flow_error'] === '' && $held['resume_handle'] === 'one-time-handle' && $execution->calls === 0, 'Existing admin suspension was rerun or treated as an error.');
checkAdmin($held['agent_result'] === null && $held['output'] === [] && $held['interaction_requests'][0]['metadata'] === [] &&
	!str_contains(json_encode($held), 'internal-only'), 'Stored internal review metadata was disclosed by AgentAdmin.');

$execution->result = new AgentExecutionResult([], [], new AgentResult(AgentExecutionStatus::AWAITING_APPROVAL, AgentState::empty()->withSuspension($pending)));
$repository->pending = null;
$waiting = runAdmin($display, ['user_prompt' => 'New prompt']);
checkAdmin($waiting['ok'] && $waiting['status'] === AgentExecutionStatus::AWAITING_APPROVAL && $waiting['interaction_requests'][0]['id'] === 'review-1', 'Typed suspension was not surfaced.');

$clarification = new AgentInteractionRequest('review-input', AgentInteractionRequest::KIND_CLARIFICATION, $action, 'input-fingerprint', 'More details', 'Provide input');
$inputState = new AgentSuspensionState(true, AgentExecutionStatus::AWAITING_INPUT, [$clarification], 'input-handle');
$execution->result = new AgentExecutionResult([], [], new AgentResult(AgentExecutionStatus::AWAITING_INPUT, AgentState::empty()->withSuspension($inputState)));
$inputWaiting = runAdmin($display, ['user_prompt' => 'Needs input']);
checkAdmin($inputWaiting['ok'] && $inputWaiting['status'] === AgentExecutionStatus::AWAITING_INPUT && $inputWaiting['interaction_requests'][0]['kind'] === 'clarification', 'AgentAdmin rejected a clarification suspension.');
$execution->result = null;
$repository->pending = $inputState;
$submitted = runAdmin($display, ['resume' => ['resume_handle' => 'input-handle', 'responses' => [['request_id' => 'review-input', 'decision' => 'submit', 'input' => ['item' => 'changed']]]]]);
checkAdmin($submitted['ok'] && $execution->lastRequest->getInputs()['resume']['responses'][0]['input']['item'] === 'changed', 'AgentAdmin did not forward submitted input.');

$execution->result = null;
$repository->pending = $pending;
$approved = runAdmin($display, ['resume' => ['resume_handle' => 'one-time-handle', 'responses' => [['request_id' => 'review-1', 'decision' => 'approve']]]]);
checkAdmin($approved['ok'] && $approved['message_text'] === 'Admin reply' && $execution->lastRequest->getInputs()['prompt'] === '', 'Approval did not continue the old run.');
checkAdmin($execution->lastRequest->getInputs()['resume']['responses'][0]['decision'] === 'approve', 'Approval did not use AgentResume.');
$rejected = runAdmin($display, ['resume' => ['resume_handle' => 'one-time-handle', 'responses' => [['request_id' => 'review-1', 'decision' => 'deny']]]]);
checkAdmin($rejected['ok'] && $execution->lastRequest->getInputs()['resume']['responses'][0]['decision'] === 'deny', 'Denial did not reach AgentResume.');

$execution->calls = 0;
foreach ([
	[['resume' => ['resume_handle' => 'wrong', 'responses' => [['request_id' => 'review-1', 'decision' => 'approve']]]], 'hitl_resume_unavailable'],
	[['resume' => ['resume_handle' => 'one-time-handle', 'responses' => [['request_id' => 'review-1', 'decision' => 'invalid']]]], 'hitl_resume_invalid'],
	[['resume' => ['resume_handle' => 'one-time-handle']], 'hitl_response_required_server']
] as [$payload, $code]) {
	$denied = runAdmin($display, $payload);
	checkAdmin(!$denied['ok'] && $denied['code'] === $code && $execution->calls === 0, 'Invalid admin resume reached execution: ' . $code);
}
$users->actor = User::fromArray(['id' => 13]);
$crossActor = runAdmin($display, ['resume' => ['resume_handle' => 'one-time-handle', 'responses' => [['request_id' => 'review-1', 'decision' => 'approve']]]]);
checkAdmin(!$crossActor['ok'] && $crossActor['code'] === 'hitl_resume_unavailable' && $execution->calls === 0, 'Different admin resumed a pending action.');
$users->actor = null;
$unauth = runAdmin($display, ['user_prompt' => 'Run']);
checkAdmin(!$unauth['ok'] && $execution->calls === 0, 'Unauthenticated admin ran the agent.');

echo "AgentAdmin HITL execution and scope: OK\n";
