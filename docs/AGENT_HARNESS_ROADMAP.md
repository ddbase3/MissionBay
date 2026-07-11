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

## Recommended next implementation

### Memory and context contributor profiles

The current patch deliberately preserves resources that implement both `IAgentTool` and `IAgentMemory`, such as user preferences. The next design step should introduce a small operator-facing memory/context profile without mixing it into orchestration settings.

Before implementation, evaluate a foundation-level contract split between conversation history and prompt/context contribution. This should remain backward compatible with `IAgentMemory` adapters and must not duplicate the underlying resource or storage.

### Profile diagnostics

Add a read-only effective-composition view that shows, for one agent:

```text
resolved orchestrator profile
canonical stage sequence
expanded tool profiles
component presets and capability facets
final capability pool
selection limits
configuration warnings
```

This is more useful to operators than exposing additional low-level fields in the agent form.

## Nice-to-have: visible model intent

A model response may contain both tool calls and a short assistant message. MissionBay can expose that content as an optional `agent.intent` or `agent.status` event before tool execution.

The text should be one short user-facing sentence such as “I am checking the current record before updating it.” It must not expose hidden reasoning or chain-of-thought. Providers may return no content alongside tool calls, so this remains optional and should not control execution.

## Later semantic stages

Only add these where an orchestrator profile actually needs them:

### `task-normalization`

Translate the user turn and profile into a provider-neutral task model with objective, constraints, requested output, interaction policy, and completion criteria.

### `planning` and `plan-verification`

Use only for deliberate/planning profiles. Reactive profiles should retain the compact loop.

### `memory-writeback`

Persist selected facts or summaries only after successful or explicitly partial visible completion.

## Later capability work

- embedding-backed selection for very large catalogs;
- dedicated small-model routing as an optional selector implementation;
- dynamic MCP capability refresh and cache invalidation;
- resource and prompt consumption stages for profiles that need them;
- module dependency/conflict declarations;
- administration diagnostics for unavailable remote providers.
