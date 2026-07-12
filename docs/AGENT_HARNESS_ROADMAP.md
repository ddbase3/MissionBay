# MissionBay Agent Harness Status

## Status

The bounded agent-harness migration is complete.

```text
Harness completion: 100%
Open migration items: 0
```

This document is retained as the final status record. It is no longer an open-ended roadmap.

## Stable default pipeline

```text
capability-discovery
capability-selection
model-decision
action-policy
tool-execution
context-compaction
tool-observation
semantic-verification
```

The order is canonical. Profiles may select supported modes and limits, but they cannot freely reorder required semantic stages.

## Completed architecture

### Orchestration

- component-resolved semantic stages;
- compact default pipeline;
- bounded capability discovery and selection;
- action policy, review, suspension, deterministic resume, and replay rejection;
- mutation commit guard for tools that explicitly require it;
- tool input/output contract validation;
- cache, budget, loop-progress, compaction, and verification services behind the semantic stages;
- partial-result preservation after recoverable model failures;
- deliberate profile using the existing `AgentPlanState` and semantic-verification boundary without another planning class hierarchy.

### Configuration

- orchestrator profiles;
- reusable tool profiles;
- separate Memory Profiles containing configured conversation-memory Component Presets;
- separate Context Profiles containing configured `IAgentContextContributor` Component Presets;
- one shared configured base resource when the same preset contributes tool and context facets;
- profile-centred agent forms with expert/legacy settings isolated in a collapsible section;
- complete redacted agent-configuration export at the bottom of the agent form;
- Effective Composition diagnostics.

### Memory and context

- visible conversation history supplied to later turns;
- current user message persisted before later orchestration can fail;
- `ISession`-based session memory with concrete preset and conversation isolation;
- explicit `IAgentConversationMemory` and `IAgentContextContributor` contracts;
- built-in context contributors no longer implement fake/no-op chat-history APIs;
- Knowledge / Skills remains an explicit tool and is not conversation memory or automatic context injection;
- no phrase-specific conversation-routing regular expressions.

### State and results

- stable provider-neutral `AgentState` and `AgentResult` DTOs;
- typed task, plan, knowledge, execution, memory, context-window, budget, suspension, and result areas;
- dynamic context variables retained for experimental and integration-specific values;
- MissionBay-specific state-context access located in `MissionBay/Api`.

### Administration and diagnostics

- Memory, Context, Tool, Orchestrator, Component Preset, Agent, and Effective Composition displays;
- simplified Knowledge / Skills tool and editor;
- regex inventory/control script shipped with every patch from patch 16 onward;
- Foundation interface ownership audit and complete extension documentation.

## AssistantFoundation boundary

Every interface remaining in `AssistantFoundation/src/Api` has a concrete plugin-to-plugin extension, replacement, or adapter use case.

The normative ownership audit, implementation examples, and registration instructions are in:

```text
ASSISTANTFOUNDATION_EXTENSION_POINTS.md
```

MissionBay-only contracts remain in `MissionBay/Api`.

## Supported compatibility boundaries

The following compatibility paths are intentionally supported and are not open cleanup tasks:

- direct legacy `IAgentMemory` implementations remain conversation-compatible;
- older combined memory/context profile records are read and split by actual runtime interfaces until operators save the separated profile fields;
- direct expert `agent_components` remain available only through explicit expert/legacy configuration;
- unadvertised historical Knowledge tool names remain callable for saved flows and old system prompts, while new tool definitions expose only the reduced public surface.

Compatibility is removed only through a separate migration plan with real stored-configuration migration and an explicit file-delete list.

## Completion rule

The harness is closed at this point. Further work is a new feature or a targeted defect fix and requires:

1. a concrete use case;
2. a bounded finish condition;
3. an explicit production type/file budget;
4. updated extension documentation when Foundation contracts change;
5. a complete file-delete list for moved or removed files.
