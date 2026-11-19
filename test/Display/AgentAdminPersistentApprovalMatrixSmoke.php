<?php declare(strict_types=1);

/** Deterministic negative matrix for the existing AgentAdmin grant boundary. */

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
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
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

function verifyApprovalMatrix(bool $valid, string $message): void {
	if (!$valid) throw new RuntimeException($message);
}

$configurations = new AgentAdminHitlAgentConfigurations();
$users = new AgentAdminHitlUsermanager();
$users->actor = User::fromArray(['id' => 42]);
$repository = new AgentAdminHitlSuspensionRepository();
$repository->scope = AgentSuspensionScope::forConversation(
	'agent-admin:jarvis', 'manual:' . hash('sha256', "test-xrm\0" . '42'), 'agent-admin:test-xrm:user:42'
);
$execution = new AgentAdminHitlExecution();
$execution->result = new AgentExecutionResult(
	['assistant' => ['message' => ['content' => 'Approved.']]], [],
	new AgentResult(AgentExecutionStatus::COMPLETED, AgentState::empty())
);
$display = (new ReflectionClass(AgentAdminDisplay::class))->newInstanceWithoutConstructor();
foreach ([
	'agentConfigurations' => $configurations,
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

$fingerprint = (new AgentActionFingerprint())->createToolDefinitionFingerprint([
	'type' => 'function', 'function' => ['name' => 'remote__write', 'parameters' => ['type' => 'object']],
	'annotations' => ['destructiveHint' => true]
]);
$action = new AgentAction('call-1', AgentAction::TYPE_TOOL_CALL, 'remote__write', ['value' => 5]);
$reviewData = ['decision' => ['decision' => 'require_approval', 'metadata' => [
	'policy_id' => 'mutation-approval-actions',
	'policy_implementation' => 'mutationapprovalagentactionpolicy',
	'tool_definition_fingerprint' => $fingerprint
]]];
$approveRequest = new AgentInteractionRequest(
	'approval-1', AgentInteractionRequest::KIND_APPROVAL, $action, 'action-fingerprint',
	'Review tool', 'Review the requested action', [], 'high', $reviewData
);
$repository->pending = new AgentSuspensionState(true, AgentExecutionStatus::AWAITING_APPROVAL, [$approveRequest], 'handle-one');
$run = new ReflectionMethod(AgentAdminDisplay::class, 'buildRunResponse');
$valid = ['resume' => ['resume_handle' => 'handle-one', 'responses' => [[
	'request_id' => 'approval-1', 'decision' => 'approve', 'metadata' => ['approval_scope' => 'agent']
]]]];

$invalid = [
	'unsupported scope' => static function(array $p): array { $p['resume']['responses'][0]['metadata']['approval_scope'] = 'system'; return $p; },
	'chat scope in admin grant' => static function(array $p): array { $p['resume']['responses'][0]['metadata']['approval_scope'] = 'conversation'; return $p; },
	'denied durable grant' => static function(array $p): array { $p['resume']['responses'][0]['decision'] = 'deny'; return $p; },
	'submitted durable grant' => static function(array $p): array { $p['resume']['responses'][0]['decision'] = 'submit'; return $p; },
	'foreign interaction id' => static function(array $p): array { $p['resume']['responses'][0]['request_id'] = 'approval-foreign'; return $p; },
	'foreign handle' => static function(array $p): array { $p['resume']['resume_handle'] = 'handle-foreign'; return $p; },
	'empty approval response' => static function(array $p): array { $p['resume']['responses'] = []; return $p; },
	'unknown decision' => static function(array $p): array { $p['resume']['responses'][0]['decision'] = 'automatic'; return $p; },
	'missing handle' => static function(array $p): array { $p['resume']['resume_handle'] = ''; return $p; },
	'invalid response structure' => static function(array $p): array { $p['resume']['responses'] = ['invalid']; return $p; }
];
foreach ($invalid as $reason => $mutate) {
	$execution->calls = 0;
	$before = $configurations->settings;
	$result = $run->invoke($display, 'jarvis', $mutate($valid));
	verifyApprovalMatrix(($result['ok'] ?? true) === false && $execution->calls === 0
		&& $configurations->settings === $before, 'Invalid agent grant crossed the execution boundary: ' . $reason);
}

$invalidReview = [
	'no policy identity' => ['decision' => ['decision' => 'require_approval', 'metadata' => ['tool_definition_fingerprint' => $fingerprint]]],
	'wrong policy' => ['decision' => ['decision' => 'require_approval', 'metadata' => ['policy_id' => 'allow-all-agent-actions', 'tool_definition_fingerprint' => $fingerprint]]],
	'wrong decision' => ['decision' => ['decision' => 'allow', 'metadata' => ['policy_id' => 'mutation-approval-actions', 'tool_definition_fingerprint' => $fingerprint]]],
	'no fingerprint' => ['decision' => ['decision' => 'require_approval', 'metadata' => ['policy_id' => 'mutation-approval-actions']]],
	'bad fingerprint' => ['decision' => ['decision' => 'require_approval', 'metadata' => ['policy_id' => 'mutation-approval-actions', 'tool_definition_fingerprint' => 'not-a-sha256']]]
];
foreach ($invalidReview as $reason => $metadata) {
	$execution->calls = 0;
	$before = $configurations->settings;
	$bad = new AgentInteractionRequest(
		'approval-1', AgentInteractionRequest::KIND_APPROVAL, $action, 'action-fingerprint',
		'Review tool', 'Review the requested action', [], 'high', $metadata
	);
	$repository->pending = new AgentSuspensionState(true, AgentExecutionStatus::AWAITING_APPROVAL, [$bad], 'handle-one');
	$result = $run->invoke($display, 'jarvis', $valid);
	verifyApprovalMatrix(($result['ok'] ?? true) === false && $execution->calls === 0
		&& $configurations->settings === $before, 'Invalid server review became a durable grant: ' . $reason);
}

$inputRequest = new AgentInteractionRequest(
	'approval-1', AgentInteractionRequest::KIND_CLARIFICATION, $action, 'action-fingerprint',
	'Input needed', 'Supply additional input', [], 'medium', $reviewData
);
$repository->pending = new AgentSuspensionState(true, AgentExecutionStatus::AWAITING_INPUT, [$inputRequest], 'handle-one');
$execution->calls = 0;
$result = $run->invoke($display, 'jarvis', $valid);
verifyApprovalMatrix(($result['ok'] ?? true) === false && $execution->calls === 0, 'Input request was granted permanent approval.');

$repository->pending = new AgentSuspensionState(true, AgentExecutionStatus::AWAITING_APPROVAL, [$approveRequest], 'handle-one');
$execution->result = new AgentExecutionResult([], [], new AgentResult(AgentExecutionStatus::FAILED, AgentState::empty()));
$configurations->settings['tool_approval_grants'] = [];
$result = $run->invoke($display, 'jarvis', $valid);
verifyApprovalMatrix(($result['ok'] ?? true) === false && $execution->calls === 1
	&& $configurations->settings['tool_approval_grants'] === [], 'Runtime failure wrote a durable grant.');
$execution->result = new AgentExecutionResult([], [], new AgentResult(AgentExecutionStatus::PARTIAL, AgentState::empty()));
$result = $run->invoke($display, 'jarvis', $valid);
verifyApprovalMatrix(($result['ok'] ?? true) === false && $configurations->settings['tool_approval_grants'] === [], 'Partial execution wrote a durable grant.');

$execution->result = new AgentExecutionResult(
	['assistant' => ['message' => ['content' => 'Resumed.']]], [], new AgentResult(AgentExecutionStatus::COMPLETED, AgentState::empty())
);
$result = $run->invoke($display, 'jarvis', ['resume' => ['resume_handle' => 'handle-one', 'responses' => [[
	'request_id' => 'approval-1', 'decision' => 'approve', 'metadata' => []
]]]]);
verifyApprovalMatrix(($result['ok'] ?? false) === true && $configurations->settings['tool_approval_grants'] === [], 'One-time approval incorrectly created a persistent grant.');

$secondAction = new AgentAction('call-2', AgentAction::TYPE_TOOL_CALL, 'remote__read', ['value' => 15]);
$secondRequest = new AgentInteractionRequest(
	'approval-2', AgentInteractionRequest::KIND_APPROVAL, $secondAction, 'action-fingerprint-2',
	'Review second tool', 'Review the second action', [], 'medium', $reviewData
);
$repository->pending = new AgentSuspensionState(true, AgentExecutionStatus::AWAITING_APPROVAL, [$approveRequest, $secondRequest], 'handle-one');
$execution->calls = 0;
$result = $run->invoke($display, 'jarvis', ['resume' => ['resume_handle' => 'handle-one', 'responses' => [
	['request_id' => 'approval-1', 'decision' => 'approve', 'metadata' => ['approval_scope' => 'agent']],
	['request_id' => 'approval-2', 'decision' => 'deny', 'metadata' => []]
]]]);
verifyApprovalMatrix(($result['ok'] ?? false) === true
	&& count($configurations->settings['tool_approval_grants']) === 1
	&& isset($configurations->settings['tool_approval_grants']['remote__write'])
	&& !isset($configurations->settings['tool_approval_grants']['remote__read']), 'A denied second action received a durable grant.');

$users->actor = User::fromArray(['id' => 13]);
$execution->calls = 0;
$result = $run->invoke($display, 'jarvis', $valid);
verifyApprovalMatrix(($result['ok'] ?? true) === false && $execution->calls === 0, 'Another actor inherited the original admin review.');

echo 'AgentAdmin deterministic approval matrix: ' . (count($invalid) + count($invalidReview) + 7) . " cases OK\n";
