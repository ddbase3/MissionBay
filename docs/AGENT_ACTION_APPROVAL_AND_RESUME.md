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
model-decision
  -> action-policy
       -> allow / deny / require_approval / require_clarification / require_dry_run
       -> action review service creates exact requests and suspension
  -> return awaiting_approval or awaiting_input

external transport
  -> shows exact tool name and structured input
  -> collects approve, deny, or submit
  -> sends AgentResume

orchestrator resume service
  -> validates suspension and one response per request
  -> validates action id, tool name, arguments, and fingerprint
  -> restores approved/revised calls or normalized denials
  -> returns them to action-policy
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

`AgentActionFingerprint` binds approval to the exact action type, tool name, and canonical input. Changed arguments require a new review. The fingerprint detects inconsistencies but is not a signature or authorization token.

## Statuses

```text
awaiting_approval
awaiting_input
```

Both are suspension outcomes, not failures. Denial becomes a normalized tool observation so the model can continue without executing the mutation.

## Production work still required

The current DTO path can transport suspension state, but production mutation safety still needs:

- server-owned suspension persistence;
- opaque or authenticated resume handles;
- expiry and one-time consumption;
- authorization and resource-version recheck immediately before mutation;
- audit events for request, decision, resume, and result.
