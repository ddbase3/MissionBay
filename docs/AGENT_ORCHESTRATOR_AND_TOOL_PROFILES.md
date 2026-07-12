# MissionBay Orchestrator and Tool Profiles

## Purpose

MissionBay separates operator-facing agent configuration from expert orchestration configuration.

A normal agent record selects:

```text
LLM
system prompt
orchestrator profile
tool profiles
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

## Dual tool and memory components

Some resources intentionally implement both `IAgentTool` and `IAgentMemory`. `UserPrefsAgentResource` is the main example:

- its tool facet lets the model list, set, and remove preferences;
- its memory facet loads the current preferences as system-role context for subsequent model calls.

Tool-profile resolution preserves all declared preset capabilities. A preset with:

```text
tool + memory
```

is attached to the assistant in both roles. Both adapters point to the same underlying configured resource, so a preference written through the tool facet is visible when the memory facet is loaded again.

### Refactoring assessment

The dual-capability concept is still useful and should not be split into unrelated resources merely because it implements two interfaces. It represents two controlled views of one domain component.

The current `IAgentMemory` contract, however, covers both conversation history and context injection. A future foundation revision should distinguish these concerns, for example:

```text
IAgentConversationMemory
IAgentContextContributor
```

A resource such as user preferences would then implement `IAgentTool` plus `IAgentContextContributor`, while session/chat history would implement `IAgentConversationMemory`. This is a contract clarification, not a reason to duplicate the underlying preference service or storage now.

Until that foundation change is planned, MissionBay keeps the existing compatible behavior and documents the two facets explicitly.

## Runtime resolution

```text
Agent settings
  -> resolve orchestrator profile
  -> write canonical stages and limits to assistant node
  -> resolve selected tool profiles
  -> expand component presets
  -> preserve tool/memory capability facets
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
| `AgentComponentPresetAdminDisplay` | Technical administrators configuring individual resource instances. |

`Base3IliasLab` registers Effective Composition and Orchestrator Profiles next to Agents, Tool Presets, and Tool Profiles. The composition display resolves actual tool names, memory facets, capability sources, module stage mounts, and final stages without adding these details back to the normal Agent form.
