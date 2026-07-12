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
  -> ends the current request or stream
  -> collects the next user response
  -> starts a new request with resume_handle plus either natural-language or explicit responses

orchestrator resume service
  -> atomically claims the handle
  -> restores the suspension from server state
  -> resolves natural-language input with the active chat model when needed
  -> validates one response per request
  -> validates action id, tool name, arguments, and fingerprint
  -> consumes the handle before approved execution
  -> restores approved/revised calls or normalized denials
  -> continues with tool-execution
```

A suspended run does not execute the reviewed action and does not generate the normal final answer.

## Two-request transport model

Human interaction never keeps the original transport connection open.

### First request

The agent reaches a review boundary, persists the suspension, returns an opaque `resume_handle`, and completes the current request.

For SSE, MissionBay emits:

```text
agent.interaction.required
  -> payload contains status, interaction_requests, and resume_handle
done
  -> the SSE connection closes
```

For REST, the endpoint returns:

```json
{
  "type": "interaction_required",
  "status": "awaiting_approval",
  "resume_handle": "opaque handle",
  "interaction_requests": []
}
```

### Second request

The next normal chat message is sent with the stored handle:

```json
{
  "prompt": "jo hau rein",
  "resume": {
    "resume_handle": "opaque handle",
    "response_text": "jo hau rein",
    "responses": []
  }
}
```

The Chatbot integration maps both SSE and REST to the same `AgentResume` payload. Other transport adapters, including MCP endpoints, can forward the same explicit responses or `response_text` without changing MissionBay resume semantics.

## Natural-language decisions

A user may respond in unrestricted natural language, for example:

```text
I agree.
ok
ja
jo hau rein
go
in Ordnung
lass das
abbrechen
```

No regular expression or fixed consent word list decides whether the action may run.

When `AgentResume` contains `response_text` but no explicit responses, `AgentInteractionResponseResolver` asks the already active chat model to classify the response against the exact server-owned interaction requests. The resolver accepts only strict structured output and validates:

- every returned request id exists,
- every pending request is covered exactly once,
- the decision is valid for the request kind,
- no action payload or fingerprint is supplied by the client.

The normalized decisions are:

```text
approve
deny
submit
unclear
```

The transport-level and DTO decision value for rejection is `deny`.

## Unclear responses

An unclear, unrelated, incomplete, invalid, or failed model interpretation never executes the pending action.

The resume claim is released, the original suspension remains server-side, and the same `resume_handle` is returned with the same public interaction requests. The user can then answer again in a later request.

The handle is consumed only after a complete and valid response set has been validated.

## Explicit API responses

Programmatic clients do not need natural-language interpretation. They may send explicit normalized responses:

```json
{
  "resume_handle": "opaque handle",
  "responses": [
    {
      "request_id": "exact request id",
      "decision": "approve",
      "input": {},
      "note": "optional"
    }
  ]
}
```

This remains the preferred form for deterministic API and MCP integrations. Natural-language and explicit response modes share the same server-side validation and one-time handle semantics.

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
