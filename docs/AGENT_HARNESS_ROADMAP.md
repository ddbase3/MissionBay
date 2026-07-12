# MissionBay Agent Harness Roadmap

## Completed

- Component-based stage resolution and explicit default ordering.
- Standard `context-compaction` with integrated context assessment.
- Structured action policy, review, suspension, and deterministic resume.
- Mutation approval bound to exact tool input.
- Durable server-owned suspension storage through `IStateStore`.
- Opaque resume handles, short claim leases, one-time consumption, and replay rejection.
- Final mutation commit guard with authorization revalidation, optimistic version checks, mutation cache bypass, and typed audit events.
- Tool input validation before policy and output validation after execution or cache lookup, with structured correctable observations.
- Partial final response when the tool-loop limit is reached.
- Cleanup from 17 visible default stages to a compact semantic pipeline.
- Run-specific capability catalog with duplicate-name rejection, agent/profile hard boundaries, deterministic context ranking, bounded model exposure, and exact-selection execution enforcement.
- Explicit per-agent configuration for tools, capability providers, modules, resource providers, and prompt providers.
- Configured component resolution through `IComponentResolver` without granting unrelated global capabilities.
- Module activation with run-local instructions, capabilities, resources, prompts, and semantic stage mounts.
- Shared administration UI for capability source and tool-selection settings.
- Budget, cache, result verification, resume, loop control, and continuation mechanics moved into services/checkpoints.
- MissionBay documentation aligned with the compact pipeline.
- Orchestrator profiles with a fixed canonical core-stage order, read-only built-in modes, bounded tool-loop and selection settings, and runtime validation.
- Profile-centred agent administration: operators select an orchestrator profile and reusable tool profiles; direct components and low-level capability settings moved to an explicit expert/legacy section.
- Tool profiles reusable by internal agents, MCP, or both, including preservation of presets that expose both tool and memory facets.
- Base3IliasLab administration integration for orchestrator profiles.
- Read-only effective agent composition diagnostics with runtime profile expansion, actual callable tool names, memory facets, capability-source resolution, module stage mounts, final pipeline validation, and redacted diagnostic data.
- Single-line Orchestrator Profile search and filter control zones with horizontal overflow on narrow screens.
- Explicit separation of conversation history from run-local prompt/context contribution through `IAgentConversationMemory`, `IAgentContextContributor`, and typed `AgentInstructionBlock` values.
- Backward-compatible role resolution for legacy `IAgentMemory` implementations, with conversation-only writes and effective-composition warnings.
- Dual-role tool/context components remain one configured runtime resource, with run-local de-duplication and frozen contributor messages across suspension/resume.
- Separate operator-facing memory and context profiles that select concrete configured Component Presets, with agent-form selection, effective-composition diagnostics, and a bounded compatibility reader for old combined records.
- Stable typed `AgentState` and transport-neutral `AgentResult` models with task, plan, knowledge, execution, memory, context-window, budget, suspension, and result sections.
- Backward-compatible `MissionBay\Api\IAgentStateContext` extension while the existing `IAgentContext` variable bag remains available for experimental and stage-specific data.
- Incremental `AgentStateSynchronizer` projection from existing tool-loop context keys, including final visible output and access through orchestrator, turn, and execution results.
- Chatbot-agent `Reference context` configuration moved into the same collapsible visual pattern as the agent form's expert/legacy configuration.
- ILIAS session conversation memory moved fully behind `ISession`, with deterministic round-trip, trimming, and reset coverage.
- Built-in context-only resources no longer pretend to be chat-history stores or expose no-op `IAgentMemory` methods.
- Knowledge/skills tool exposure reduced from 22 specialized functions to six focused functions; compatibility aliases are no longer advertised.
- Knowledge, preference, and focus writes now participate in mutation approval through explicit tool annotations.
- Knowledge/skills administration reduced to common filters and actions while retaining technical metadata in the backend/detail model.
- Deterministic minimal task normalization in turn preparation, with follow-up-aware capability selection and conversation-recall tool bypass, reusing `AgentTaskState` without a new stage or model call.
- Provider-answer fast path for web search: bounded tool output is returned directly, without AI compaction, another tool-decision call, semantic verification, or a final rewriting model call when the search adapter already returned a usable answer.
- Recoverable model-decision failures after successful observations now fall through to a partial final response instead of discarding evidence.
- Knowledge/skills entries can be edited through one simplified modal form; the former inline content editor is no longer the normal UI path.
- MissionBay-only `IAgentStateContext` moved from AssistantFoundation to `MissionBay/Api`; the old foundation file is listed for deletion.
- Deliberate orchestrator profile with concise evidence planning stored in the existing `AgentPlanState`, without another stage, model call, planning interface, or provider hierarchy; existing semantic verification remains the verification boundary.
- Visible conversation history is supplied to every task and capability-selection request without phrase classifiers or wording-specific regular expressions. The current user turn is persisted before later orchestration can fail.
- Memory/context profiles now link directly to Component Presets, show the relevant preset configuration summary, and can deep-link into the generated preset editor for storage namespace, history limit, and priority.

## Current default stages

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

## Finite cleanup countdown

The current migration is governed by `AGENT_LEGACY_CLEANUP.md`. The list is fixed and may only shrink.

```text
C-06 conversation-memory reliability             done, hardened in patch 14
C-05 knowledge/skills surface simplification     done in patch 13
C-04 context-contributor legacy cleanup          done in patch 13
C-03 minimal task normalization                  done in patch 14
C-02 optional planning and verification          done in patch 15
C-01 compatibility removal and documentation     open

remaining: 1 / 6
```

## Recommended next implementation

### C-01: compatibility removal and documentation freeze

The final cleanup patch audits Foundation ownership against the concrete extension use cases, removes the unadvertised type-specific knowledge aliases, migrates or removes the remaining ILIAS session-memory compatibility alias, and consolidates unused state or adapter code. Every moved file must be accompanied by an explicit delete list because ZIP extraction cannot remove obsolete paths.

No new feature is part of C-01. Its finish condition is a clean compatibility boundary, no unresolved migration placeholder, and a countdown of `0 / 6`.

## After the countdown

This document intentionally contains no additional migration backlog. Once C-01 is complete, further features require a separate bounded plan with a concrete use case, a finish condition, and an explicit file/type budget.
