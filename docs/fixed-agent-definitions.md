# Fixed agents supplied by BASE3 plugins

## Purpose

MissionBay owns one `IAgentConfigurationService`. It combines arbitrary
`IAgentDefinition` implementations discovered by `IClassMap` with existing
`ISettingsStore` group `agent` records. This is the same service used by the
agent admin, composition display, scheduled runner, sub-agent tools and
external endpoints. Normal agents continue to be stored and deleted as before.

A plugin can publish a stable agent with one small PHP class. It must implement
`Base3\AssistantFoundation\Api\IAgentDefinition`, provide the globally unique
classmap `getName()` and immutable agent `getId()` and `getLabel()`, and return
editable defaults through `getDefaultSettings()`. The definition is discovered
automatically as part of the existing PluginClassMap. No plugin init-time
agent seeder, second registry or migration is necessary.

## Example of a plugin-owned definition

```php
<?php declare(strict_types=1);

namespace ExamplePlugin\Agent;

use Base3\AssistantFoundation\Api\IAgentDefinition;

final class SupportAgentDefinition implements IAgentDefinition {

    public static function getName(): string {
        return 'supportagentdefinition';
    }

    public function getId(): string {
        return 'support';
    }

    public function getLabel(): string {
        return 'Support';
    }

    public function getDefaultSettings(): array {
        return [
            'agent_runtime' => 'missionbay',
            'enabled' => false,
            'label' => $this->getLabel(),
            'policy' => ['policy' => 'manualonlyjobpolicy', 'data' => []]
        ];
    }
}
```

There can be multiple definitions from multiple plugins. Their **agent ids**
must be unique globally in the `agent` group and match `[a-z0-9._-]+`; classmap
`getName()` is a different, unique lower-case class name. Duplicate or invalid
ids cause an explicit error, not a last-wins override. Existing named agent
settings with the same id are treated as the editable configuration for the
code-owned identity. They are not overwritten automatically.

## How it behaves

- A fixed agent appears in the Agents list even if its SettingsStore record
  has never been saved. No default record is persisted on initialization.
- Its id and display label are owned by the class. The UI marks it as fixed,
  makes the identity fields read-only and hides the delete action. The
  service rejects forged deletion/rename requests server-side.
- All runtime fields are editable as before: chat model, orchestrator, tool
  profiles, memory/context profiles, system prompt, capability sources,
  components, enablement and scheduling policy.
- On reading, existing stored settings win over definition defaults. Only the
  fixed identity label wins over a stored label. The stored record itself is
  never rewritten by a read.
- A newly declared, unconfigured agent should default to `enabled=false`, so
  an installed definition cannot trigger a model call or scheduled work just
  by being installed. Once configured and explicitly enabled, it uses the
  ordinary execution path.
- `getAgents('other-group')` and `getAgent($id, 'other-group')` preserve the
  previous custom-group catalog behavior; only `agent` includes definitions.
- `IAgentExecutionService` receives effective configuration. Identity,
  authorization and conversation-memory rules stay with existing owners.
  A fixed definition does not grant execution rights to any user or service.

## Consumer integration

Inject `IAgentConfigurationService` from DI. Resolve configuration with
`getAgent('support')`, test `hasAgent('support')`, list with `getAgents()`,
modify with `saveAgent('support', $settings)` and use `isFixed('support')` for
identity controls. A non-fixed record can be removed with
`deleteAgent('name')`. Existing consumers must no longer directly write
records in group `agent`; that would bypass the code-owned invariant.

## Jarvis implementation and compatibility

`Base3XrmWebsite\Agent\JarvisAgentDefinition` defines the fixed id `jarvis`
and label `Jarvis`. It starts disabled with MissionBay as the default runtime
and a manual-only job policy; no model or secret is invented. The existing
record `agent/jarvis`, if present, retains its model, tool profiles, memory,
context and other settings. The personal KeyHarbor HTTP interface
`/jarvisagentapi.json` and its service id `missionbay:agent:jarvis` are unchanged.
The API, admin, jobs and agent tools use the shared configuration owner.

Existing technical HMAC microservices, KeyHarbor credentials and the MCP
transport are not modified. Verify normal agent create/edit/delete, fixed
agent edit/no-delete, tool and job invocation, and existing XRM authorized
Jarvis calls in the deployed application before production rollout.

## Verification

```sh
php plugin/MissionBay/test/Service/AgentConfigurationServiceSmokeTest.php
php plugin/Base3XrmWebsite/test/Agent/JarvisAgentApiSmokeTest.php
php plugin/Base3XrmWebsite/test/Service/XrmCredentialServiceSmokeTest.php
php plugin/Base3XrmWebsite/test/Service/XrmAuthenticationMicroserviceSmokeTest.php
php plugin/Base3XrmWebsite/test/Service/XrmUsermanagerMicroserviceSmokeTest.php
```
