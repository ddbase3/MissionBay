<?php declare(strict_types=1);

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

function checkAdminGrant(bool $ok, string $message): void {
	if (!$ok) throw new RuntimeException($message);
}

$configurations = new AgentAdminHitlAgentConfigurations();
$users = new AgentAdminHitlUsermanager();
$users->actor = User::fromArray(['id' => 42]);
$repo = new AgentAdminHitlSuspensionRepository();
$execution = new AgentAdminHitlExecution();
$execution->result = new AgentExecutionResult(['assistant' => ['message' => ['content' => 'Approved.']]], [], new AgentResult(AgentExecutionStatus::COMPLETED, AgentState::empty()));
$display = (new ReflectionClass(AgentAdminDisplay::class))->newInstanceWithoutConstructor();
foreach ([
	'agentConfigurations' => $configurations,
	'agentConfigFormService' => new AgentAdminHitlConfigForm(),
	'agentExecutionService' => $execution,
	'suspensionRepository' => $repo,
	'usermanager' => $users,
	'settingsAuthorization' => new AgentAdminHitlAuthorization(),
	'translation' => new AgentAdminHitlTranslation(),
	'policyOptions' => []
] as $property => $value) {
	(new ReflectionProperty(AgentAdminDisplay::class, $property))->setValue($display, $value);
}
$channel = 'agent-admin:jarvis';
$conversation = 'manual:' . hash('sha256', 'test-xrm' . "\0" . '42');
$repo->scope = AgentSuspensionScope::forConversation($channel, $conversation, 'agent-admin:test-xrm:user:42');
$toolDefinition = ['function' => ['name' => 'external_write', 'parameters' => ['type' => 'object']], 'destructiveHint' => true];
$fingerprint = (new AgentActionFingerprint())->createToolDefinitionFingerprint($toolDefinition);
$action = new AgentAction('tool-1', AgentAction::TYPE_TOOL_CALL, 'external_write', ['id' => 1]);
$review = new AgentInteractionRequest('request-1', AgentInteractionRequest::KIND_APPROVAL, $action, 'action-fingerprint', 'Approve operation', 'Approved?', [], 'high', [
	'decision' => ['decision' => 'require_approval', 'metadata' => [
		'policy_id' => 'mutation-approval-actions',
		'tool_definition_fingerprint' => $fingerprint
	]]
]);
$repo->pending = new AgentSuspensionState(true, AgentExecutionStatus::AWAITING_APPROVAL, [$review], 'manual-handle');
$approve = ['resume' => ['resume_handle' => 'manual-handle', 'responses' => [[
	'request_id' => 'request-1', 'decision' => 'approve', 'metadata' => ['approval_scope' => 'agent']
]]]];
$before = $configurations->settings;
$run = (new ReflectionMethod(AgentAdminDisplay::class, 'buildRunResponse'))->invoke($display, 'jarvis', $approve);
checkAdminGrant($run['ok'] && $execution->calls === 1, 'Explicit approval did not resume the original action.');
$runtimeResume = $execution->lastRequest?->getInputs()['resume'] ?? null;
checkAdminGrant(is_array($runtimeResume) && ($runtimeResume['resume_handle'] ?? '') === 'manual-handle', 'The original resume handle was lost.');
checkAdminGrant(($runtimeResume['responses'][0]['decision'] ?? '') === 'approve', 'The explicit tool approval was not forwarded.');
checkAdminGrant(!array_key_exists('approval_scope', $runtimeResume['responses'][0]['metadata'] ?? []), 'Admin-only permanent approval metadata leaked into runtime and triggers Unknown tool approval scope.');
$grant = $configurations->settings['tool_approval_grants']['external_write'] ?? null;
checkAdminGrant(is_array($grant) && $grant['fingerprint'] === $fingerprint && $grant['approved_by'] === 'test-xrm:user:42', 'Authoritative persistent grant not saved.');
$revoke = (new ReflectionMethod(AgentAdminDisplay::class, 'buildRevokeApprovalResponse'))->invoke($display, 'jarvis', ['tool_name' => 'external_write']);
checkAdminGrant($revoke['ok'] && ($configurations->settings['tool_approval_grants'] ?? []) === [], 'Revocation did not remove the durable grant.');
$execution->calls = 0;
$fake = $approve;
$fake['resume']['responses'][0]['metadata']['approval_scope'] = 'system';
$denied = (new ReflectionMethod(AgentAdminDisplay::class, 'buildRunResponse'))->invoke($display, 'jarvis', $fake);
checkAdminGrant(!$denied['ok'] && $execution->calls === 0, 'Unsupported approval scope was executed.');
$fake = $approve;
$fake['resume']['responses'][0]['decision'] = 'deny';
$denied = (new ReflectionMethod(AgentAdminDisplay::class, 'buildRunResponse'))->invoke($display, 'jarvis', $fake);
checkAdminGrant(!$denied['ok'] && $execution->calls === 0, 'Persistent grant was accepted for denial.');
$fake = $approve;
$fake['resume']['resume_handle'] = 'fake-handle';
$denied = (new ReflectionMethod(AgentAdminDisplay::class, 'buildRunResponse'))->invoke($display, 'jarvis', $fake);
checkAdminGrant(!$denied['ok'] && $execution->calls === 0, 'Persistent grant accepted with foreign handle.');
$users->actor = User::fromArray(['id' => 13]);
$denied = (new ReflectionMethod(AgentAdminDisplay::class, 'buildRunResponse'))->invoke($display, 'jarvis', $approve);
checkAdminGrant(!$denied['ok'] && $execution->calls === 0, 'Another actor could use the original handle.');

echo "AgentAdmin persistent approval, denial and revocation: OK\n";
