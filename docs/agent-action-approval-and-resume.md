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
  -> shows the user-facing title, message, and summary
  -> keeps the exact tool name and structured input under technical details
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


## User-facing review data

Public interaction requests contain `title`, `message`, and `summary` for the
primary user-facing display. Guarded mutation tools create these values through
`IAgentMutationGuardedTool::getActionReview()` from the same server-owned
snapshot that will later be validated at commit time.

The exact `AgentAction` remains part of the interaction request. Transports
should render `action.name` and `action.input` separately under technical
details instead of using raw JSON as the main confirmation text.

Tool-development rules and examples are documented in
[agent-tool-development.md](agent-tool-development.md).

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

The default repository uses `IStateStore`, a 15-minute suspension TTL, a short claim lease, one-time consumption, and a replay marker. Details are documented in [agent-durable-suspensions.md](agent-durable-suspensions.md).

## Mutation commit guard

Approval is followed by a final execution-boundary check. Mutation tools can capture authorization and resource-version state before review and validate it immediately before `callTool()`. Stale or no-longer-authorized writes are returned as blocked tool results without invoking the tool.

See [agent-mutation-commit-guard.md](agent-mutation-commit-guard.md).

## Approval reuse scope (phase 1 baseline)

The current exact-action `AgentResume` approval is a one-time confirmation, not a reusable chat-wide or agent-wide grant. Support for optional chat-scoped tool/MCP approval and explicit durable agent-scoped tool approval for scheduled jobs is tracked in [agent-hitl-phase-1.md](agent-hitl-phase-1.md). Neither scope is active in this baseline. No caller may infer reusable consent from a previous exact-action approval, and no worker may execute an approval-required action without its authoritative policy decision.

## AgentAdmin and personal API integration (phase 2)

The existing AgentAdmin run endpoint (`mode=run`) now reports suspended agent executions with `action=interaction_required` and accepts the standard `AgentResume` payload in `resume`. The runner dialog requires an explicit user decision for every pending request and uses the original `IAgentExecutionService` to continue the suspended run. It does not start a new prompt or create a separate review store.

The existing `Base3XrmWebsite` Jarvis JSON API returns HTTP 200 with `type=interaction_required`, status, public `interaction_requests`, `resume_handle` and the original `conversation_id`. It accepts `resume` with the same opaque handle and normalized decisions. The API continues to check personal KeyHarbor credentials on every request, including resume. An actor-qualified suspension scope is used while the existing conversation memory channel is preserved. Current client UX support is still a phase 5 task.

Suspensions returned from the repository may include private tool and mutation-commit metadata needed for server-side execution. The public presentation uses `AgentInteractionRequest::toPublicArray()`, which preserves the action details necessary for an informed decision while excluding private request metadata. Raw suspended agent state must never be serialized into a public review response. This does not confer extra permissions and does not support permanent approval. See [agent-hitl-phase-1.md](agent-hitl-phase-1.md) for the implementation baseline and remaining phases.

## Delegated agent suspensions (phase 3)

The configured-agent and available-agent tools call the normal `IAgentExecutionService`. A child status of `awaiting_approval` or `awaiting_input` is an execution pause, never an ordinary failed tool result. `AgentToolExecutionStage` stops the parent batch at exactly the delegated call. `AgentActionReviewService` saves the **parent's existing** orchestrator snapshot using the same `IAgentSuspensionRepository`, with the internal child's handle held only inside that server-owned suspension state. The caller receives only a newly created outer handle with the child's public review requests. There is no delegated executor, alternate tool policy or additional store.

`AgentActionResumeService` validates the outer response set and consumes the outer handle once. The existing tool-execution stage then passes a normal `AgentResume` to the specific child via `IAgentExecutionService`. The child validates and consumes its own handle. The same process works recursively for A to B to C, and newly required subsequent approvals produce new outer suspensions rather than reusing consumed handles.

The parent snapshot preserves existing tool results, executed-call records, pending calls and the paused position in the tool batch. On resume, finished siblings are **not** invoked again. The resumed delegated call bypasses only the ordinary tool-result cache lookup for that exact call, since a cached output cannot replace child resume. Action policy evaluation and the mutation commit guard remain mandatory before execution. If the parent policy has changed and its delegated call is no longer allowed or requires a new review, the pending continuation fails closed rather than executing an unreviewed operation.

An invalid request ID, duplicate response, expired child suspension, consumed handle or concurrent claim never grants permission. A suspended child whose canonical typed suspension is missing a valid handle is also rejected rather than converted into success. Internal child handles and parent snapshots are not serialized into public interaction requests. This phase does not implement chat-wide, permanent or scheduler approvals. See [agent-durable-suspensions.md](agent-durable-suspensions.md).

## Scheduled approvals and durable tool grants (phase 4)

`ScheduledAgentRunnerJob` now treats only the typed `AgentExecutionStatus::COMPLETED` as a successful scheduled run. Suspensions are not errors, are never marked as complete, and remain available through the existing `IAgentSuspensionRepository`. An execution without a typed agent result is not counted as successful.

For each configured scheduled agent, the worker uses the existing `IStateStore` for an execution lock and a small *run hold marker*. The marker is written **before** executing a due run, so a process error, partially performed mutation or expired suspension cannot automatically replay previous actions. It contains a timestamp, not a copy of the suspension or the tool state. While a live suspension exists, the worker reports the run as pending and does not send a second prompt. A stale hold without a live suspension blocks restart until the authorized AgentAdmin operator explicitly reviews and rearms it. Rearming can repeat a partial mutation; the confirmation dialog warns about that risk. There is no automatic approval or second job executor.

In the existing AgentAdmin runner dialog, **Review scheduled run** loads a pending run and allows explicit approval, denial or input using `AgentResume`. The admin endpoint delegates to the original `ScheduledAgentRunnerJob`, which calls `IAgentExecutionService`. A completed resumed run marks the configured job timing policy and removes the hold. An unsuccessful resume leaves the hold in place for review. Job reset is rejected while a live suspension still exists.

The existing `MutationApprovalAgentActionPolicy` also accepts a separately and explicitly granted, **agent-scoped** reusable tool approval. This is selected only in the AgentAdmin's manual run dialog as **Always approve this tool for this agent, including future scheduled runs**. A normal one-time approval remains the default selection. A durable grant is based exclusively on the server-owned pending request, its mutation-policy decision, the current tool-definition fingerprint and the trusted authenticated admin identity. The Admin saves grants through `IAgentConfigurationService` into the same agent's existing `agent` SettingsStore record under `tool_approval_grants`. It never trusts client-supplied tool fingerprints or grants from ordinary settings forms.

The policy compares the grant's fingerprint to the **complete current tool definition** for the exact tool name on every subsequent call. A changed definition or remote MCP endpoint fingerprint invalidates the previous grant. The configured tool name includes the provider/preset namespace. Other action policies, capability restrictions, tool input validation, host-owned authorization and the mutation commit guard still execute normally. The grant authorizes the human review requirement only, not the underlying operation. The effective worker identity must still possess its own real permissions. A grant cannot be used to give the worker an admin's personal rights.

Current explicit grant shape within the agent configuration:

```json
{
	"tool_approval_grants": {
		"configured_mcp__write": {
			"scope": "agent",
			"fingerprint": "<64 hexadecimal characters>",
			"approved_by": "<authoritative host instance>:user:<verified id>",
			"approved_at": "2026-11-19T00:00:00+00:00"
		}
	}
}
```

AgentAdmin lists granted tools and provides **Revoke approval**, which removes the selected record through the same agent configuration service. Normal editor saves preserve existing grants but cannot create or change them. Deleting a non-fixed agent removes its grants together with its configuration. Fixed agents retain their normal immutable identity while editable settings, including grants, use the established configuration owner.

The `AgentResume` and `AgentInteractionResponse` DTOs are unchanged. The admin-only UI choice is normalized to an explicit `decision=approve` with `metadata.approval_scope=agent`, validated against the server-owned suspension **before** submitting the resume. The durable grant is saved only after the runtime accepts that resumed execution. A failed or invalid resume does not create a grant. Saving a permanent grant can itself fail after a valid one-time execution; the caller receives a specific failure and must not assume future job approval was stored. This non-atomic boundary must be operationally monitored.

**Not introduced in phase 4:** chat-scoped reusable approvals, blanket MCP-server grants, automatic privileged job identities, another permission database, another suspension store, or another execution endpoint. Browser/CLI clients are still a phase 5 concern. This phase's local smoke tests cover job pause/resume/expiry/reset, grant/revocation/changed tool definition, prior approved action compatibility and nested agent execution. Real host load, identity-specific authorization and end-to-end remote MCP behavior require installation tests.

## Phase 5: Chat-scoped reusable approval

When a mutation approval request is displayed, an explicit `AgentInteractionResponse` with decision `approve` and metadata `{ "approval_scope": "conversation" }` creates a grant for subsequent calls of the **same full tool definition** within the authenticated conversation. The original one-time action is still resumed using the normal opaque handle. No other approval decision or natural-language classification creates the reusable grant. A plain `approve` remains a one-time decision.

`AgentActionFingerprint::conversationApprovalScope()` derives the established `AgentSuspensionScope` from the trusted channel, conversation and actor. The `RunConfiguredAgentTool` and `RunAvailableAgentTool` pass this scope only as execution context to a nested agent. The existing MutationApproval policy looks up a matching fingerprint and tool identity in `IStateStore`; it never serves as a second authorization system. The approval expires 90 days after creation, and the normal conversation deletion owner removes its keys on deletion. A different user/chat/tool definition is never implicitly allowed.

For tools requiring a mutation commit guard, `AgentActionPolicyStage` delegates to the existing `AgentActionReviewService` and `AgentMutationCommitGuardService` to capture the **current** action-specific commit snapshot before tool execution. The existing commit-validation owner checks the current native authorization and resource version again at execution time; absent or stale guards block execution. This also protects configured-agent durable approvals. A grant does not authorize a different operation, skip input validation or override any other action policy. There is no second runner, grant queue, service identity or repository.
