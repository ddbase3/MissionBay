# Calling configured agents with attached files

## Why this contract matters

`IAgentExecutionService::execute()` runs an agent from a supplied `AgentExecutionRequest`. Its
Foundation DTO carries three independent arrays, `agentConfiguration`, `inputs` and `context`.
The DTO itself does **not** infer a file owner from the settings or attach documents. MissionBay
uses the trusted execution context to resolve the file owner of the configured LLM resource.

The Agent Admin editor stores an agent's attachments under the managed storage owner
`agent/<agent_id>/collection`. In `ConfiguredChatModelAgentResource::prepareFileReferences()`,
MissionBay uses the *pair* `agent_id` and `agent_config_group = agent` to select that owner.
Both are required when consuming a configured agent from another plugin.

**Important:** an `agent_id` without `agent_config_group = agent` causes MissionBay to clear
file references and return from file preparation. The model can still produce a normal answer,
so a successful execution alone does **not** prove that it received the agent's attachments.
This is an intentional owner-isolation boundary, not a missing feature of the Foundation DTO.

## Correct call from a game, service, display or job

Use the effective configured agent obtained through `IAgentConfigurationService`. Inject the
Foundation services via DI. Authorize the invoking operation at its existing server boundary.

```php
<?php declare(strict_types=1);

use Base3\AssistantFoundation\Api\IAgentConfigurationService;
use Base3\AssistantFoundation\Api\IAgentExecutionService;
use Base3\AssistantFoundation\Dto\AgentExecutionRequest;

// $agents and $executor are injected Foundation services.
$agentId = 'example.support';
$settings = $agents->getAgent($agentId);
if (!is_array($settings) || ($settings['enabled'] ?? false) !== true) {
	throw new RuntimeException('The configured agent is unavailable.');
}

$prompt = 'Respond using your attached reference material.';
$result = $executor->execute(new AgentExecutionRequest(
	$settings,
	[
		'system' => (string)($settings['system_prompt'] ?? ''),
		'prompt' => $prompt,
		'mode' => 'manual_agent'
	],
	[
		'agent_id' => $agentId,
		'agent_config_group' => 'agent',
		'agent_label' => (string)($settings['label'] ?? ''),
		'agent_config' => $settings,
		'agent_run_mode' => 'manual',
		'agent_user_prompt' => $prompt
	]
));
$output = $result->getOutput();
```

The two mandatory file-owner keys are `agent_id` and `agent_config_group`. Other shown
context fields follow the existing Agent Admin calling convention. A `conversation_id` is
**not** necessary for a standalone request; supply conversation identifiers only where
configured memory or the application actually requires continuity.

Applications with different tasks may pass a task-specific `inputs['system']` and
`inputs['prompt']` for each call while reusing the same effective agent configuration and
trusted file-owner context. Do not create a second document loader, attachment registry,
retrieval service or shadow agent merely to access files already attached in Agent Admin.

## Trust and isolation

- `agent_id` must identify the exact agent whose effective settings were retrieved.
- `agent_config_group = agent` is for a trusted, configured agent from the `agent` settings
  group. Never set it from an untrusted client-supplied ID or use it to claim unrelated files.
- `chatbot_config_group` and `chatbot_config_name` belong to **chatbot** execution. They do
  not replace `agent_config_group` and never provide an agent-owner fallback when
  `agent_id` is present.
- The runtime deliberately does **not** infer the owner group from a bare ID or from the
  user prompt. Such inference could cross the agent/chatbot resource boundary.
- File forwarding still requires an installed managed storage backend, a configured model
  supporting `IAiFileCapableChatModel`, and provider compatibility. Not every agent has files.

## Cold-start checklist for new integrations

1. Read `AssistantFoundation/Dto/AgentExecutionRequest.php` and
   `AssistantFoundation/Api/IAgentExecutionService.php` for the generic contract.
2. Check MissionBay's current `AgentAdminDisplay::buildAgentContextVars()` and
   `ConfiguredChatModelAgentResource::prepareFileReferences()` for owner semantics.
3. Retrieve the effective agent through `IAgentConfigurationService::getAgent($agentId)`;
   do not assemble a second copy of its settings or tools.
4. Pass **both** `agent_id` and `agent_config_group = agent` in the request context.
5. When an agent answers but reports that it cannot see its attached files, inspect these
   two context values **before** changing prompts, adding document-handling code or
   modifying the model configuration.
6. Verify a real agent call with a document-specific question and compare it with the
   Agent Admin's run behavior; passing unit tests does not prove provider file availability.

## Source ownership

- `MissionBay/src/Resource/ConfiguredChatModelAgentResource.php` owns the managed-file
  reference lookup and safe agent/chatbot separation.
- `MissionBay/src/Display/AgentAdminDisplay.php::buildAgentContextVars()` provides the
  working manual-execution pattern.
- `MissionBay/src/Job/ScheduledAgentRunnerJob.php::buildAgentContextVars()` uses the same
  agent owner pair for scheduled executions.
- `AssistantFoundation` defines transport-neutral DTOs and interfaces; it does not own
  MissionBay-specific managed-file owner resolution.
