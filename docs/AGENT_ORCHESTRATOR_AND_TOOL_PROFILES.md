# MissionBay Orchestrator and Tool Profiles

## Purpose

MissionBay separates operator-facing agent configuration from expert orchestration configuration.

A normal agent record selects:

```text
LLM
system prompt
orchestrator profile
tool profiles
separate memory and context profiles
```

The complex details stay in dedicated administration displays. This keeps the agent form suitable for platform operators who do not need to understand stage ordering, tool-selection limits, component presets, or MCP wiring.

## Orchestrator profiles

An orchestrator profile controls:

- the orchestration mode;
- maximum tool loops;
- optional semantic stages;
- capability-selection strategy and limits.

The core stage order is not user-configurable. MissionBay constructs the effective pipeline from a canonical sequence.

Required stages:

```text
model-decision
  -> action-policy
  -> tool-execution
  -> tool-observation
```

Optional stages can only be inserted at their canonical positions:

```text
capability-discovery
capability-selection
context-compaction
semantic-verification
```

The administration UI therefore uses checkboxes instead of drag-and-drop ordering. `AgentStagePipelineResolver` validates the same invariant again at runtime, so malformed or manually edited flow data cannot reorder the core pipeline.

### Built-in profiles

MissionBay always exposes three read-only profiles:

| Profile | Intended use |
|---|---|
| `simple` | One bounded tool loop for small, direct tool tasks. |
| `standard` | General multi-step tool orchestration with discovery, selection, compaction, and verification. |
| `governed` | Full orchestration for agents that may execute approved mutations. |

Built-in profiles can be duplicated into custom profiles. They cannot be overwritten or deleted.

### Important boundary

Approval, durable resume, replay protection, contract validation, caching, budgets, mutation commit guards, and audit events are services/checkpoints. They are not optional stage checkboxes. A governed profile describes the intended use, while the actual mutation safety boundary remains enforced by the action policy and execution services.

## Tool profiles

Tool profiles group configured component presets. One profile can be enabled for:

- internal MissionBay agents;
- MCP exposure;
- both internal agents and MCP.

This reuses the existing MCP-oriented profile administration instead of creating a second grouping mechanism. The profile stores preset IDs; the runtime resolves those presets into the current AgentFlow.

Multiple selected tool profiles are merged. Repeated preset IDs are de-duplicated. Disabled, missing, or non-internal profiles fail closed when an internal agent is built.

## Dual tool and context components

Some resources intentionally expose a tool facet and a context-contributor facet. `UserPrefsAgentResource` is the main example:

- its tool facet lets the model list, set, and remove preferences;
- its context-contributor facet adds current preferences to a new turn without acting as chat history.

AssistantFoundation now distinguishes:

```text
IAgentConversationMemory
IAgentContextContributor
```

Session, volatile, and database histories implement `IAgentConversationMemory`. User preferences, focus, time, page context, and sub-agent descriptions implement `IAgentContextContributor`. Knowledge / Skills remains an explicit tool. Compatibility adapters may still implement `IAgentMemory`, but MissionBay resolves their explicit role and does not write user/assistant messages to context-only components.

Tool-profile resolution continues to preserve all declared preset capabilities. A preset with:

```text
tool + memory
```

is attached through the existing compatibility wrapper, which reports the wrapped component as a context contributor or conversation memory at runtime. Both wrappers point to the same underlying configured resource, so a preference written through the tool facet is available to the contributor on the next new turn.

Memory/context composition is exposed through a separate profile. It selects configured component presets, their automatic or explicit role, deterministic priority, and conversation read/write switches. Component-specific storage, credentials, namespaces, and user scoping stay in the component preset.

When such a profile is selected, tool profiles contribute tool facets only. The separate memory and context profiles contributes the memory facet. Both still point to the same base preset resource, so dual-role components are not duplicated.

## Runtime resolution

```text
Agent settings
  -> resolve orchestrator profile
  -> write canonical stages and limits to assistant node
  -> resolve selected tool profiles
  -> resolve selected separate memory and context profiles
  -> merge repeated component presets
  -> preserve one shared base resource per preset
  -> build AgentFlow resources and docks
  -> capability discovery
  -> bounded capability selection
  -> model decision
```

Legacy direct component and capability settings remain readable. They are shown only in the expert section and override profile defaults only when expert overrides are explicitly enabled.

## Administration

The following displays are intended for different audiences:

| Display | Audience |
|---|---|
| `AgentAdminDisplay` | Platform operators creating and assigning agents. |
| `AgentCompositionAdminDisplay` | Experts inspecting the effective read-only runtime composition of an agent. |
| `AgentOrchestratorProfileAdminDisplay` | Experts maintaining safe orchestration modes and limits. |
| `ToolProfileAdminDisplay` | Experts grouping configured component presets for internal agents and/or MCP. |
| `AgentMemoryProfileAdminDisplay` | Experts composing conversation memories and context contributors. |
| `AgentComponentPresetAdminDisplay` | Technical administrators configuring individual resource instances. |

`Base3IliasLab` registers Effective Composition, Orchestrator Profiles, Tool Profiles, Memory Profiles, Context Profiles, and Component Presets next to Agents. The composition display resolves actual tool names, memory facets, capability sources, module stage mounts, and final stages without adding these details back to the normal Agent form.
