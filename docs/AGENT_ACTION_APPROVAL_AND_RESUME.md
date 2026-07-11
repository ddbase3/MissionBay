# MissionBay Agent Action Approval and Resume

## Purpose

MissionBay supports transport-neutral review of actions that mutate data, require clarification, or need a dry run.

The core rule is:

```text
Tools execute actions.
The harness requests and validates user interaction.
```

## Runtime flow

```text
capability-discovery
  -> capability-selection
  -> model-decision
  -> action-policy
       -> allow / deny / require_approval / require_clarification / require_dry_run
       -> review service persists an exact server-owned suspension
  -> return awaiting_approval or awaiting_input plus opaque resume_handle

external transport
  -> shows exact tool name and structured input
  -> collects approve, deny, or submit
  -> sends resume_handle plus explicit responses

orchestrator resume service
  -> atomically claims the handle
  -> restores the suspension from server state
  -> validates one response per request
  -> validates action id, tool name, arguments, and fingerprint
  -> consumes the handle before approved execution
  -> restores approved/revised calls or normalized denials
  -> continues with tool-execution
```

A suspended run does not execute the reviewed action and does not generate the normal final answer.

## Mutation metadata

The default mutation policy requires review when a tool definition explicitly declares a side effect, including common annotations such as:

```text
mutation: true
requiresApproval: true
destructiveHint: true
sideEffectHint: true
readOnlyHint: false
```

New mutation tools must declare this metadata. Tool-name guessing is intentionally not used.

## Exact review binding

`AgentActionFingerprint` binds approval to the exact action type, tool name, and canonical input. Changed arguments require a new review.

The suspension itself stays server-side behind `IAgentSuspensionRepository`. The client cannot replace the reviewed action by returning a modified suspension payload.

## Statuses

```text
awaiting_approval
awaiting_input
```

Both are suspension outcomes, not failures. Denial becomes a normalized tool observation so the model can continue without executing the mutation.

## Durable resume

The default repository uses `IStateStore`, a 15-minute suspension TTL, a short claim lease, one-time consumption, and a replay marker. Details are documented in [AGENT_DURABLE_SUSPENSIONS.md](AGENT_DURABLE_SUSPENSIONS.md).

## Mutation commit guard

Approval is followed by a final execution-boundary check. Mutation tools can capture authorization and resource-version state before review and validate it immediately before `callTool()`. Stale or no-longer-authorized writes are returned as blocked tool results without invoking the tool.

See [AGENT_MUTATION_COMMIT_GUARD.md](AGENT_MUTATION_COMMIT_GUARD.md).
