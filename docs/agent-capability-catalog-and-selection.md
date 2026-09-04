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

The two stages are explicit, mutually exclusive pipeline choices. Deterministic `capability-selection` and function-level `ai-capability-selection` may run again before later `model-decision` phases. Source-level `ai-capability-selection` runs once for the orchestration turn and its bounded source-complete selection is reused for later model decisions in that turn.

The default `HybridAgentCapabilitySelector` performs no additional model call. It uses:

1. hard agent filters for tool names, tags, and categories;
2. mandatory and always-available tools;
3. lexical relevance from recent messages, tool names, descriptions, tags, categories, and schema property names;
4. tool priority;
5. recently executed and previously selected tools for short-term stability;
6. a configurable maximum selection size.

Small function pools are passed through when their size is at or below both `selectAllThreshold` and `maxTools`. For source selection, the shortcut uses the number of eligible sources, but only when all functions from those sources still fit within `maxTools` and the sources fit within `maxSources`. Larger pools continue through AI selection.

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

The selector never grants new capabilities. AI output is accepted only when every returned name exists in the already filtered candidate set. Required capabilities remain enforced. Invalid output, provider failures, or an unavailable model fall back to deterministic hybrid selection. The routing model call is recorded in the normal model-result metadata so usage and diagnostics remain visible. Once applied, the selected tools remain the model-facing set for the rest of the orchestration turn. Approval resume restores those same selected tool definitions from the current run catalog instead of exposing the full catalog or rerunning the AI router.

Source selection is dependency-complete rather than action-name-only. If a likely operation needs an identifier, state, candidate, schema, or other prerequisite supplied by another source, the selector may include that prerequisite source in the same selection call. The core router prompt remains domain-neutral. Domain-specific workflow rules stay in tool contracts.

An explicit user routing instruction is treated as a routing constraint when a matching candidate exists. Requests such as using a named capability or restricting the turn to one named source must select that source rather than silently substituting an unrelated source. Required dependency sources may still be added when they are necessary to complete the requested operation. A recent explicit routing instruction remains relevant across a direct follow-up when the current user message does not revoke or replace it. The current user message always wins when an older routing instruction conflicts with the new request.

For very large source catalogs, MissionBay keeps the AI source-selection call bounded by `semanticMaxPromptCharacters`. It first sends complete function summaries. If that no longer fits, it compacts each source to context-relevant representative functions while retaining the source id and total function count. If necessary, it reduces metadata further before omitting any candidate source id. This scaling behavior does not add another selector or another model call.

Capability routing uses only a small recent visible-history window. The source selector keeps the current user message plus at most two previous usable user messages and excludes assistant replies from this routing-only view. The assistant-side task normalization keeps at most two previous user messages because the current request is appended separately. This prevents an assistant error or stale claim from displacing an explicit user routing instruction while leaving the persisted conversation history unchanged.

The built-in `large-catalog` profile selects `ai-capability-selection`. Standard and custom profiles may select either capability-selection stage, but never both.

`alwaysAvailable` remains a narrow escape hatch for truly mandatory protocol tools. It is not intended for every list or entry-point function in a large catalog.

## Main-agent source selection for the agent-selected native profile

`agent-selected-native` does not run either capability-selection stage. Discovery still builds the complete run-local function catalog, but the main native agent owns source selection during its normal model-decision loop.

The agent always sees a compact catalog of every eligible configured capability source. It sees complete function schemas only for its current active working set plus the internal `missionbay_select_capability_sources` control function. Calling that function replaces the active source set for the next model decision. The same agent may call it repeatedly in one turn when the first source choice is insufficient or wrong.

This path intentionally has no preselection model call and no deterministic relevance router. Existing `includeTools`, `excludeTools`, tag/category filters, mandatory tools, `maxTools`, and `maxSources` still define the hard eligible universe and selection limits. Relevance is decided by the main agent from the compact source catalog.

For configured tool presets, source metadata is defined as follows:

```text
source_id     = exact component/tool preset id
label         = preset label
description   = underlying tool resource getDescription()
function_count = eligible functions owned by that source
```

The description is captured when `AgentComponentPresetMaterializer` wraps the tool. `ConfiguredAgentToolResource` preserves those source metadata and `ToolGuardAgentTool` forwards them when a hard function allow-list is applied. Tool Profile descriptions are not substituted for the tool's own description.

The active source set is working context, not persistent agent configuration. Replacing it does not change Tool Profiles, Component Presets, or the chatbot configuration. Approval, action policy, execution guards, observation, and compaction continue to operate on the selected real tool definitions exactly as in the existing pipeline.

The internal source-control call is intercepted before action policy and is never executed as a domain tool. Successful source selections are recorded in the existing `AgentCapabilitySelection` diagnostics. Invalid selections are returned to the same model as structured tool messages so it can choose again.

`large-catalog-native` remains unchanged and continues to perform its one-time AI source preselection. The two profiles are separate runtime choices.

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

Resource providers, prompt providers, module instructions, and module stage mounts are retained in the run-local discovery result even though only callable tool functions enter the model-facing catalog. See [agent-capability-providers-and-modules.md](agent-capability-providers-and-modules.md).

## Follow-up routing and evidence boundaries

Capability selection receives a small recent conversation window so that short follow-ups, pronouns, corrections and misspellings can be resolved against the active subject. The window is routing context only. It does not promote previous assistant statements to factual evidence.

A request such as "check that again" should therefore select an authoritative source for the active subject instead of relying on the assistant's preceding answer. Likewise, a short ambiguous message should remain attached to the immediate topic unless the user clearly changes domains.

