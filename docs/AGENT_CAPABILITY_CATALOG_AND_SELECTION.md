# MissionBay Capability Catalog and Tool Selection

## Purpose

An agent may own a small tool set or a pool with hundreds of callable functions. MissionBay separates that complete agent-specific pool from the bounded subset exposed to one model call.

```text
Tools docked directly on the assistant
+ explicitly configured tools/providers/modules
  -> capability discovery and module activation
  -> profile filtering
  -> run-specific AgentCapabilityCatalog
  -> capability-selection stage
  -> bounded tool definitions for model-decision
```

The model never gains tools that are outside the agent's configured pool. Selection can only reduce that pool. The assistant `tools` dock accepts up to 512 tool resources; each resource may expose multiple function capabilities.

## Run-specific catalog

`AgentCapabilityCatalogBuilder` normalizes every callable function after the active profile has filtered the docked tools. One `IAgentTool` may contribute several functions, so catalog entries are function-level capabilities.

Each entry contains:

- operational function name;
- title and description;
- category and tags;
- priority;
- complete model-facing function definition and schemas;
- owning tool resource identity where available;
- `alwaysAvailable` and mutation-related metadata.

Operational names must be unique. Duplicate function names are rejected before the first model call instead of being resolved ambiguously during execution.

## Default selection stage

`capability-discovery` is the first stage in the default pipeline. It publishes the run-local composition resolved from the agent's explicit capability source configuration. `capability-selection` follows it and runs before every `model-decision` phase. It is a real stage because the choice depends on the current run context and may change after observations.

The default `HybridAgentCapabilitySelector` performs no additional model call. It uses:

1. hard agent filters for tool names, tags, and categories;
2. mandatory and always-available tools;
3. lexical relevance from recent messages, tool names, descriptions, tags, categories, and schema property names;
4. tool priority;
5. recently executed and previously selected tools for short-term stability;
6. a configurable maximum selection size.

Small pools are passed through when their size is at or below both `selectAllThreshold` and `maxTools`. Larger pools are ranked and truncated.

## Node configuration

Assistant nodes expose the optional `capabilityselection` input:

```php
[
    'enabled' => true,
    'strategy' => 'hybrid',
    'maxTools' => 16,
    'selectAllThreshold' => 16,
    'includeTools' => [],
    'excludeTools' => [],
    'includeTags' => ['crm', 'mail', 'info'],
    'excludeTags' => ['administration'],
    'includeCategories' => [],
    'excludeCategories' => [],
    'alwaysAvailable' => ['general_info'],
    'sticky' => true,
]
```

Snake-case variants such as `max_tools` and `include_tags` are accepted as well.

`strategy = all` disables ranking but still applies hard filters. `enabled = false` exposes all eligible tools. Both modes are intended for controlled compatibility profiles; large catalogs should normally use `hybrid`.

Profile-required tools are merged into `alwaysAvailable`. A run fails before model execution if hard filters remove a mandatory tool or if mandatory tools exceed `maxTools`.

## Execution safety

The selection is also an authorization boundary for the model response:

```text
Tool is in the agent catalog
AND tool passed hard profile/configuration filters
AND tool was included in the exact selection shown to this model call
  -> action-policy may evaluate it
```

`AgentCapabilitySelectionGuardService` enforces this before action policy and again at the execution boundary. A model call for a non-selected tool becomes a structured `capability_not_selected` observation and is never executed.

Approval suspensions store the selected names and the enforcement flag server-side. A resumed mutation therefore remains bound to the exact model-call selection that produced it.

## Diagnostics

Each selection:

- emits `capability.selection`;
- is recorded as `AgentCapabilitySelection` in the orchestrator result;
- is available as `orchestrator_capability_selections` in the agent context;
- records catalog size, eligible size, selected names, scores, and ranking reasons.

These diagnostics expose selection behavior without revealing hidden model reasoning.

## Extension point

Selection is replaceable through:

```php
AssistantFoundation\Api\IAgentCapabilitySelector
```

A project may later provide an embedding selector, a dedicated router, or another deterministic implementation without changing the stage or execution guard.


## Configured source boundary

The catalog may receive tool functions from directly docked tools, explicitly configured tool components, configured capability providers, and activated modules. The source list is stored under `capability_sources` and is a hard per-agent allow-list. Discovery does not enumerate and grant every globally configured component.

Resource providers, prompt providers, module instructions, and module stage mounts are retained in the run-local discovery result even though only callable tool functions enter the model-facing catalog. See [AGENT_CAPABILITY_PROVIDERS_AND_MODULES.md](AGENT_CAPABILITY_PROVIDERS_AND_MODULES.md).
