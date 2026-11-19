<?php declare(strict_types=1);

use Base3\AssistantFoundation\Dto\AgentAction;
use Base3\AssistantFoundation\Dto\AgentActionDecision;
use Base3\MissionBay\Context\AgentContext;
use Base3\MissionBay\Orchestrator\AgentActionFingerprint;
use Base3\MissionBay\Orchestrator\Stage\AgentToolLoopContextKeys;
use Base3\MissionBay\Policy\MutationApprovalAgentActionPolicy;
use Base3\MissionBay\Test\Policy\Fixture\PolicyTestAgentConfigurations;

$root = dirname(__DIR__, 4);
foreach (['DIR_ROOT' => $root . '/', 'DIR_PLUGIN' => $root . '/plugin/', 'DIR_TEST' => $root . '/test/'] as $name => $value) {
	if (!defined($name)) define($name, $value);
}
require_once DIR_ROOT . 'bootstrap/Autoloader.php';
Base3\Bootstrap\Autoloader::register();

function checkGrant(bool $valid, string $message): void {
	if (!$valid) throw new RuntimeException($message);
}

$configurations = new PolicyTestAgentConfigurations();
$fp = new AgentActionFingerprint();
$policy = new MutationApprovalAgentActionPolicy($configurations, $fp, new \Base3\MissionBay\Test\Orchestrator\ApprovalMemoryStateStore());
$definition = ['type' => 'function', 'function' => ['name' => 'external_mcp__write', 'parameters' => ['type' => 'object'], 'description' => 'Write entry'], 'annotations' => ['destructiveHint' => true]];
$action = new AgentAction('call-1', AgentAction::TYPE_TOOL_CALL, 'external_mcp__write', ['entry' => 1]);
$context = new AgentContext();
$context->setVar('agent_id', 'test-agent');
$context->setVar(AgentToolLoopContextKeys::TOOL_DEFINITIONS, [$definition]);
$first = $policy->evaluate($action, $context);
checkGrant($first->getDecision() === AgentActionDecision::DECISION_REQUIRE_APPROVAL, 'Unapproved mutating tool was allowed.');
checkGrant(($first->getMetadata()['tool_definition_fingerprint'] ?? '') === $fp->createToolDefinitionFingerprint($definition), 'Approval fingerprint does not match authoritative tool.');

$configurations->agents['test-agent'] = ['tool_approval_grants' => [
	'external_mcp__write' => [
		'scope' => 'agent',
		'fingerprint' => $fp->createToolDefinitionFingerprint($definition),
		'approved_by' => 'host:user:123',
		'approved_at' => '2026-11-19T00:00:00+00:00'
	]
]];
checkGrant($policy->evaluate($action, $context)->isAllowed(), 'Durable approval was not honored for same tool definition.');
$anotherAction = new AgentAction('call-2', AgentAction::TYPE_TOOL_CALL, 'external_mcp__write', ['entry' => 987]);
checkGrant($policy->evaluate($anotherAction, $context)->isAllowed(), 'Durable approval did not apply to later arguments.');
$context->setVar('agent_id', 'other-agent');
checkGrant($policy->evaluate($action, $context)->getDecision() === AgentActionDecision::DECISION_REQUIRE_APPROVAL, 'Agent-specific grant leaked to a different agent.');
$context->setVar('agent_id', 'test-agent');
$changed = $definition;
$changed['annotations']['readOnlyHint'] = false;
$context->setVar(AgentToolLoopContextKeys::TOOL_DEFINITIONS, [$changed]);
checkGrant($policy->evaluate($action, $context)->getDecision() === AgentActionDecision::DECISION_REQUIRE_APPROVAL, 'Changed tool safety definition reused an old grant.');
$context->setVar(AgentToolLoopContextKeys::TOOL_DEFINITIONS, [$definition]);
$changedTarget = $definition;
$changedTarget['approvalTargetFingerprint'] = hash('sha256', 'https://new-mcp-endpoint.example.invalid');
$context->setVar(AgentToolLoopContextKeys::TOOL_DEFINITIONS, [$changedTarget]);
checkGrant($policy->evaluate($action, $context)->getDecision() === AgentActionDecision::DECISION_REQUIRE_APPROVAL, 'Changed MCP endpoint reused the prior grant.');
$context->setVar(AgentToolLoopContextKeys::TOOL_DEFINITIONS, [$definition]);
unset($configurations->agents['test-agent']['tool_approval_grants']['external_mcp__write']);
checkGrant($policy->evaluate($action, $context)->getDecision() === AgentActionDecision::DECISION_REQUIRE_APPROVAL, 'Revocation did not restore approval requirement.');

echo "Agent-scoped action policy approval and revocation: OK\n";
