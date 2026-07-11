# MissionBay Agent Harness Roadmap

## Completed

- Component-based stage resolution and explicit default ordering.
- Standard `context-compaction` with integrated context assessment.
- Structured action policy, review, suspension, and deterministic resume.
- Mutation approval bound to exact tool input.
- Durable server-owned suspension storage through `IStateStore`.
- Opaque resume handles, short claim leases, one-time consumption, and replay rejection.
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

### Mutation commit guard

Approval says that the user accepted a reviewed action. It does not prove that the user is still authorized or that the target data is unchanged when execution begins.

The next patch should add a final checkpoint immediately before a mutating `callTool()`:

1. identify mutation calls through explicit tool metadata;
2. recheck the current user and required permission;
3. compare reviewed resource versions or ETags with current values;
4. reject stale or no-longer-authorized writes without invoking the tool;
5. emit typed audit events for requested, approved, denied, committed, and failed actions.

This is more important than adding further planning stages.

## Nice-to-have: visible model intent

A model response may contain both tool calls and a short assistant message. MissionBay can expose that content as an optional `agent.intent` or `agent.status` event before tool execution.

The text should be one short user-facing sentence such as “I am checking the current record before updating it.” It must not expose hidden reasoning or chain-of-thought. Providers may return no content alongside tool calls, so this remains optional and should not control execution.

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
