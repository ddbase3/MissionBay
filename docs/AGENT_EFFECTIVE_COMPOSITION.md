# MissionBay Effective Agent Composition

## Purpose

Agent configuration is deliberately profile-centred. Platform operators select an LLM, an orchestrator profile and one or more tool profiles, while expert settings stay in dedicated administration areas.

That simplicity creates a diagnostic need: administrators must still be able to see what the runtime will actually assemble.

`AgentCompositionAdminDisplay` provides a read-only effective-composition view for this purpose.

## What the display resolves

For one stored agent, the display follows the same runtime preparation path used by `AgentExecutionService`:

```text
agent settings
  -> orchestrator profile
  -> canonical core stages and limits
  -> selected tool profiles
  -> selected memory profile and selected context profile
  -> component presets
  -> effective AgentFlow resources and docks
  -> configured capability sources
  -> capability-provider and module activation
  -> callable capability catalog
  -> module stage mounts
  -> final stage sequence
```

The view reports:

- selected orchestrator profile and mode;
- maximum tool loops and capability-selection settings;
- selected tool profiles and their component presets;
- selected memory and context profiles with their concrete preset IDs;
- component capability facets such as `tool` and `memory`;
- final callable tool names, source resources, categories, tags and mutation metadata;
- attached conversation memories and context contributors, their concrete preset ids, implementations, priorities, docks, and legacy status;
- configured and resolved capability providers, modules, prompt providers and resource providers;
- core stages, module-mounted stages and the final validated stage sequence;
- warnings and hard configuration errors.

## Runtime boundary

The view resolves the configured capability pool, not the prompt-specific selection for a future model call.

Per-call preselection still depends on the current user request, conversation context and sticky selection state:

```text
effective configured catalog
  -> current prompt and context
  -> capability-selection stage
  -> bounded model-facing tool set
```

Therefore the display answers:

> Which capabilities can this agent use after profile and provider resolution?

It does not claim that every listed tool will be sent to every model decision.

## Safety

The inspector never executes an agent node and never invokes a tool function.

It may initialize configured resources and ask tools/providers for their definitions so that actual callable names can be shown. Remote providers can therefore still be unavailable or slow; those failures are returned as diagnostics rather than hidden.

Configuration data shown in the diagnostic JSON is recursively redacted for keys that commonly contain credentials, including passwords, tokens, secrets, API keys, authorization data and private keys.

## Conversation-memory and context roles

The runtime now distinguishes explicit conversation history from run-local context contribution. The display reports these roles separately:

```text
conversation-memory
context-contributor
legacy-memory
tool
```

A component preset can expose both tool and context-contributor facets. The display keeps these facets attached to the same preset and shows:

- the callable tool names produced by its tool wrapper;
- the concrete Memory Profile or Context Profile that selected it;
- the effective `memory`, `contextcontributors`, or `tools` dock;
- the resolved conversation/context/legacy role and priority.

Resources such as user preferences therefore appear as `tool + context-contributor`, while session or database history appears as `conversation-memory`. Legacy-only `IAgentMemory` implementations remain conversation-compatible and produce a warning until they adopt `IAgentConversationMemory`.

## Administration integration

`Base3IliasLab` registers the display directly after the normal Agents display:

```text
Agents
Effective Composition
Orchestrator Profiles
Tool Profiles
Memory Profiles
Context Profiles
Component Presets
```

The normal Agent form remains simple. The composition display is intended for diagnostics and expert support, not for editing the agent.
