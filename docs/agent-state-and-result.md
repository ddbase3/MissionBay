# Agent State and Result

## Purpose

MissionBay now exposes a stable, typed state and terminal result for each agent run without removing the flexible context-variable bag used by existing stages.

The design follows two rules:

1. stable runtime axes belong in typed value objects;
2. stage-specific, experimental, or plugin-local values remain in `IAgentContext` variables.

This is an incremental compatibility boundary, not a big-bang rewrite of the stage pipeline.

## Contracts

`MissionBay` exposes the optional runtime context extension under `MissionBay\Api`:

```php
interface IAgentStateContext extends IAgentContext {

	public function getState(): AgentState;

	public function setState(AgentState $state): void;

	public function isFinished(): bool;

	public function finish(AgentResult $result): void;

	public function getResult(): ?AgentResult;
}
```

The interface stays in MissionBay because it describes MissionBay runtime behavior, not a cross-plugin replacement slot. `IAgentContext` itself remains unchanged. Existing context implementations therefore continue to satisfy the previous API. State-aware runtimes can opt into the stronger contract.

## Stable state sections

`AgentState` is an immutable aggregate with these sections:

```text
AgentTaskState
AgentPlanState
AgentKnowledgeState
AgentExecutionState
AgentMemoryState
AgentContextWindowState
AgentBudgetState
AgentSuspensionState
AgentResultState
```

The sections have distinct responsibilities:

| Section | Stable data |
|---|---|
| Task | run/turn identity, normalized input metadata, future task description |
| Plan | plan steps, current step and plan status |
| Knowledge | selected knowledge and tool observations |
| Execution | phase, status, counters, actions, tool calls, model results and stage traces |
| Memory | conversation-memory and context-contributor diagnostics |
| Context window | context assessments and compaction records |
| Budget | configured budget and checkpoint assessments |
| Suspension | interaction requests, awaiting status and resume handle |
| Result | visible output, verifications, continuation decisions and failure data |

Planning is not enabled merely because `AgentPlanState` exists. Patch 15 adds an explicit deliberate orchestrator profile that populates this existing slot with a concise evidence plan. It does not add another model call or planning stage; the normal semantic-verification stage remains the verification boundary.

## Terminal result

`AgentResult` contains:

```text
status
AgentState
transport-neutral output
metadata
```

It supports completed, failed, partial and suspended runs. The result is available through:

- `IAgentStateContext::getResult()`;
- `AgentToolOrchestratorResult::getAgentResult()`;
- `AgentAssistantTurnResult::getAgentResult()`;
- `AgentExecutionResult::getAgentResult()`.

The assistant node completes the typed result only after the visible final assistant response has been generated. This means the final result contains both the orchestration outcome and the user-visible content.

## Compatibility synchronizer

Existing MissionBay stages still read and write `AgentToolLoopContextKeys`. `AgentStateSynchronizer` projects those stable values into the typed state at stage boundaries.

```text
existing stage patch
  -> context variables
  -> AgentStateSynchronizer
  -> typed AgentState
```

The current bridge maps:

- execution status, phase and loop counters;
- actions, policy decisions and executed tool calls;
- model results and stage trace;
- capability selections and tool-contract validations;
- cache and progress records;
- context assessments and compactions;
- budget and budget assessments;
- interaction requests and resume handle;
- final response, verifications, continuation decisions and failures;
- conversation-memory and context-contributor diagnostics.

This lets stages migrate one by one. A later stage may write typed state directly once its contract is ready, while older stages continue using the existing keys.

## Dynamic context remains available

The typed state does not replace the generic context bag.

Examples that may remain dynamic:

```text
experimental selector scores
plugin-local trace values
temporary stage checkpoints
prototype planning metadata
integration-specific transport hints
```

The synchronizer does not remove unknown variables. This preserves extension compatibility and allows new stage ideas to mature before becoming foundation contracts.

## Run lifecycle

### New turn

A new turn starts with a fresh `AgentState` containing task identity and memory/context counts. Stable state from a previous turn is not carried into the new run.

### Tool orchestration

The state is synchronized after initialization, after stage patches and after trace entries. The orchestrator then creates a terminal or suspended `AgentResult`.

### Final visible response

Buffered and streaming assistant nodes update the result with the final assistant message and visible content after response generation.

### Suspension and resume

Suspended results use `awaiting_approval` or `awaiting_input`. Interaction requests and the opaque resume handle are represented in `AgentSuspensionState`. Existing durable suspension and replay protection remain authoritative.

## Diagnostics

Serializable snapshots are also placed in the context bag under:

```text
agent_state
agent_result
```

These values are arrays, not live DTO instances. They can be inspected through existing context/debug tooling without requiring transport code to understand PHP objects.

The snapshots may contain operational metadata and tool records. They should be treated as diagnostics and must not be exposed to untrusted clients without the same redaction rules used for existing agent traces.

## Migration guidance

New code should:

- accept `IAgentContext` when state awareness is optional;
- check for `IAgentStateContext` before using typed state;
- depend on `AgentResult` for transport-neutral terminal status;
- keep experimental values in the context bag;
- add a stable DTO section only when several stages or integrations share the same semantics.

Existing stages do not need to be rewritten solely to adopt this patch.

## Current use

Deterministic task normalization now populates `AgentTaskState` during turn preparation. Deliberate profiles may additionally populate `AgentPlanState`; standard and simple profiles keep the compact reactive path. Further state expansion is not planned as part of the current cleanup countdown.
