# MissionBay Agent Harness Roadmap

## Completed

- Component-based stage resolution and explicit default ordering.
- Standard `context-compaction` with integrated context assessment.
- Structured action policy, review, suspension, and deterministic resume.
- Mutation approval bound to exact tool input.
- Partial final response when the tool-loop limit is reached.
- Cleanup from 17 visible default stages to 6 semantic stages.
- Budget, cache, result verification, resume, loop control, and continuation mechanics moved into services/checkpoints.
- MissionBay documentation aligned with the compact pipeline.

## Current default stages

```text
model-decision
action-policy
tool-execution
context-compaction
tool-observation
semantic-verification
```

## Recommended next implementation

### Durable suspension and mutation commit safety

This is the next priority before broadening mutation-capable tools.

Planned parts:

1. A stable suspension repository contract and a MissionBay implementation backed by `IStateStore`.
2. Opaque resume handles instead of round-tripping trusted suspension state through the client.
3. TTL, one-time consumption, replay rejection, and optimistic versioning.
4. A final authorization and resource-version check immediately before tool invocation.
5. Typed audit events for interaction requested, decided, resumed, executed, and failed.

This closes the main gap between the implemented review UX and production-safe writes.

## Later semantic stages

Only add these where an orchestrator profile actually needs them:

### `capability-discovery`

Build a run-specific immutable catalog from configured tools, modules, resource providers, prompt providers, and policies.

### `module-activation`

Activate configured `IAgentModule` components and mount their instructions, capabilities, policies, and optional stages.

### `task-normalization`

Translate the user turn and profile into a provider-neutral task model with objective, constraints, requested output, interaction policy, and completion criteria.

### `planning` and `plan-verification`

Use only for deliberate/planning profiles. Reactive profiles should retain the compact loop.

### `memory-writeback`

Persist selected facts or summaries only after successful or explicitly partial visible completion.

## Orchestrator profiles

An agent profile should select a configured orchestrator profile containing:

```text
orchestrator component id
ordered semantic stage ids
module ids
policy ids
budget profile
cache profile
interaction policy
verification policy
```

Multiple profiles may use the same implementation class with different component ids and configuration.
