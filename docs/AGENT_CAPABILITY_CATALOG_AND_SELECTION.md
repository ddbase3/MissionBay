# MissionBay Capability Catalog and Tool Selection

## Purpose

An agent may own a small tool set or a pool with hundreds of callable functions. MissionBay separates that complete agent-specific pool from the bounded subset exposed to one model call.

```text
Tools docked directly on the assistant
+ explicitly configured tools/providers/modules
  -> capability discovery and module activation
  -> profile filtering
  -> run-specific AgentCapabilityCatalog
  -> capability-selection OR ai-capability-selection stage
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

`capability-discovery` is the first stage in the default pipeline. It publishes the run-local composition resolved from the agent's explicit capability source configuration. A profile may then select exactly one capability-selection stage before `model-decision`:

- `capability-selection` uses deterministic filtering and ranking without another model call;
- `ai-capability-selection` uses the active chat model to rerank a bounded deterministic candidate pool.

The two stages are explicit, mutually exclusive pipeline choices. Selection runs again before every `model-decision` phase because relevance may change after tool observations.

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

`strategy = all` disables deterministic ranking but still applies hard filters. `enabled = false` exposes all eligible tools. Both modes are intended for controlled compatibility profiles. AI usage is not selected through this configuration value. It is selected explicitly through the `ai-capability-selection` stage.

Profile-required tools are merged into `alwaysAvailable`. A run fails before model execution if hard filters remove a mandatory tool or if mandatory tools exceed `maxTools`.

## AI selection for large catalogs

The `ai-capability-selection` stage is intended for agents whose configured capability pool is too large or too heterogeneous for lexical ranking alone. Discovery remains deterministic. The profile explicitly replaces the deterministic selection stage with the AI stage:

```text
hard agent filters
  -> deterministic hybrid candidate ranking
  -> compact candidate summaries
  -> active chat model JSON reranking
  -> validated bounded tool subset
  -> deterministic fallback on any routing failure
```

Configuration adds:

```php
[
    'strategy' => 'hybrid',
    'maxTools' => 16,
    'selectAllThreshold' => 12,
    'semanticCandidateTools' => 48,
    'semanticMaxPromptCharacters' => 48000,
    'sticky' => false,
]
```

The selector never grants new capabilities. AI output is accepted only when every returned name exists in the already filtered candidate set. Required capabilities remain enforced. Invalid output, provider failures, or an unavailable model fall back to deterministic hybrid selection. The routing model call is recorded in the normal model-result metadata so usage and diagnostics remain visible.

The built-in `large-catalog` profile selects `ai-capability-selection`. Standard and custom profiles may select either capability-selection stage, but never both.

`alwaysAvailable` remains a narrow escape hatch for truly mandatory protocol tools. It is not intended for every list or entry-point function in a large catalog.

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

A project may provide another selector implementation for either explicit stage without changing the execution guard. A future embedding selector can therefore be mounted behind a dedicated selection stage instead of being hidden inside profile configuration.


## Configured source boundary

The catalog may receive tool functions from directly docked tools, explicitly configured tool components, configured capability providers, and activated modules. The source list is stored under `capability_sources` and is a hard per-agent allow-list. Discovery does not enumerate and grant every globally configured component.

Resource providers, prompt providers, module instructions, and module stage mounts are retained in the run-local discovery result even though only callable tool functions enter the model-facing catalog. See [AGENT_CAPABILITY_PROVIDERS_AND_MODULES.md](AGENT_CAPABILITY_PROVIDERS_AND_MODULES.md).
