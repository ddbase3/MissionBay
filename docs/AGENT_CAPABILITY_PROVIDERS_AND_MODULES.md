# MissionBay Configured Capability Providers and Modules

## Purpose

MissionBay separates three different scopes:

```text
Globally discoverable implementation classes
  -> explicitly configured component instances for one agent
  -> bounded function selection for one model decision
```

The agent configuration is the hard allow-list. Capability discovery never adds unrelated globally available components.

## Component interfaces

`AssistantFoundation` defines the reusable configured component slots:

```text
IAgentCapabilityProvider
IAgentModule
AgentCapabilitySourceConfig
AgentModuleManifest
AgentModuleActivation
AgentStageMount
AgentStageSlot
```

A capability provider groups run-local tools, resource providers, and prompt providers. It may represent a local bundle, an MCP server, or a project-specific adapter.

A module is an activatable bundle that may contribute:

- instructions;
- tools;
- resource providers;
- prompt providers;
- optional run-local stage mounts.

Providers and modules extend `IComponent`. Their static `getName()` identifies the implementation class, while `id()` identifies the configured runtime instance.

MissionBay keeps its existing dockable `IAgentTool` contract for compatibility. A tool resolved through `IComponentResolver` must therefore implement both `MissionBay\Api\IAgentTool` and `Base3\Api\IComponent`, or be exposed through a configured wrapper/provider that does. Directly docked legacy tools continue to work unchanged.

## Agent configuration

Agent settings now contain two independent sections:

```php
[
    'capability_sources' => [
        'tools' => ['internal-rag'],
        'providers' => ['github-mcp'],
        'modules' => ['coding-style'],
        'resourceProviders' => ['project-documents'],
        'promptProviders' => ['support-prompts'],
        'strict' => true,
    ],
    'capability_selection' => [
        'enabled' => true,
        'strategy' => 'hybrid',
        'max_tools' => 16,
        'select_all_threshold' => 16,
        'include_tags' => ['crm', 'mail'],
        'exclude_tags' => ['administration'],
        'always_available' => ['general_info'],
        'sticky' => true,
    ],
]
```

`capability_sources` determines what the agent may own. `capability_selection` determines what the model sees during one decision.

The same values are exposed on assistant nodes as:

```text
capabilitysources
capabilityselection
```

`AgentExecutionService` applies the high-level agent settings to both buffered and streaming assistant nodes, so normal agent administration does not require editing the raw AgentFlow JSON.

## Administration UI

The shared agent configuration form is used by the normal agent configuration display and by `AgentAdminDisplay`.

It provides multi-select fields for configured:

- tools;
- capability providers;
- modules;
- resource providers;
- prompt providers.

It also exposes the bounded selection settings, including maximum tools, select-all threshold, hard include/exclude lists, always-available functions, and sticky selection.

The options come from `IComponentResolver::all()` and therefore show configured component IDs, not every discovered implementation class.

## Runtime flow

Capability source activation happens before tool setup because profiles, module instructions, dynamic stage mounts, and the run catalog all need the resolved composition.

```text
Agent settings
  -> AgentCapabilityDiscoveryService
       -> resolve explicitly configured component IDs
       -> activate providers and modules
       -> collect tools/resources/prompts/instructions/stage mounts
  -> profile filtering
  -> duplicate-safe AgentCapabilityCatalog
  -> resolve core pipeline plus module stage mounts
  -> capability-discovery stage
  -> capability-selection stage
  -> model-decision
```

The service performs infrastructure resolution. The `capability-discovery` stage is the visible semantic checkpoint that publishes and validates the run-local composition before selection.

## Strict mode

With `strict = true`, a missing, invalid, or failing configured source aborts the run before model execution.

With `strict = false`, the source is omitted and a warning is recorded. This is intended for optional integrations; security-sensitive agents should normally use strict mode.

## Module instructions

Instructions returned by activated modules are appended to the server-side system prompt before profile tool setup and model execution. Empty and duplicate instruction blocks are removed.

Modules should return concise, stable instruction blocks. They must not include secrets or untrusted user content as authoritative system instructions.

## Run-local stage mounts

Modules do not register dynamic stages in the global container. They return `AgentStageMount` values for semantic slots:

```text
before_planning
planning
before_execution
execution
before_tool_call
after_tool_call
before_final_answer
after_final_answer
```

`AgentStagePipelineResolver` inserts these stages into the selected core pipeline. Mounts are ordered by `order` and then stage ID. The resolver rejects:

- unknown slots;
- slots whose anchor is absent from the selected pipeline;
- duplicate final stage IDs.

This keeps module behavior run-specific and prevents one agent's module configuration from changing another agent's pipeline.

## Tool and capability safety

Discovery can only add tools contributed by explicitly selected source components. Afterwards:

1. profile filtering reduces the pool;
2. catalog construction rejects duplicate operational function names;
3. capability selection reduces the model-facing definitions;
4. exact-selection guards reject model calls for functions not shown to that model call;
5. contract validation, action policy, approval, and mutation commit guards remain active.

Capability providers and modules therefore extend the source of the pool without bypassing existing safety boundaries.

## Diagnostics

The `capability-discovery` stage emits:

```text
capability.discovery
```

The payload contains selected source IDs, resolved IDs, counts, warnings, errors, and final catalog size. Executable objects are kept in the run context and are not serialized into the event payload.
