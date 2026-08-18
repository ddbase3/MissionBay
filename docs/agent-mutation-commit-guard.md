# MissionBay Mutation Commit Guard

## Purpose

User approval binds a mutation to an exact tool name and input. It does not guarantee that authorization or target data are still current when execution resumes.

MissionBay therefore performs a final commit check inside `tool-execution`, immediately before `IAgentTool::callTool()`.

## Runtime flow

```text
action-policy requires approval
  -> AgentActionReviewService
       -> capture authorization/resource snapshot
       -> build the user-facing review from that exact snapshot
       -> persist snapshot with the durable suspension

user approves exact action
  -> AgentActionResumeService
       -> restore server-owned snapshot
       -> bind approved fingerprint to the resumed tool call

AgentToolExecutionStage
  -> bypass tool cache for every mutation
  -> AgentMutationCommitGuardService validates approval binding
  -> guarded tool rechecks authorization and resource versions
  -> allow: callTool()
  -> deny: return normalized blocked tool result without invoking the tool
```

The commit guard is an execution service, not another configurable stage.

## Tool contract

A mutation tool that requires final validation implements:

```php
MissionBay\Api\IAgentMutationGuardedTool
```

It provides three operations:

```text
captureMutationCommitSnapshot()
getActionReview()
validateMutationCommit()
```

`getActionReview()` returns an `AgentActionReview` containing a user-facing
title, message, and directly renderable summary. The review should resolve
technical IDs to names and describe relevant current/target values. It must be
built without side effects, preferably from domain data stored in the snapshot
metadata. The exact tool name and original input remain available separately
through `AgentAction` as technical details.

The captured `AgentMutationCommitSnapshot` stays inside the server-owned suspension and is not included in the public interaction request. It should contain only the stable data needed for the later check, typically:

- authorization subject or tenant identity;
- required permission or scope;
- target resource IDs;
- expected versions, revisions, hashes, or ETags;
- domain data required for the user-facing review.

Immediately before commit, the tool returns an `AgentMutationCommitDecision`.

Typical denial codes are:

```text
mutation_unauthorized
mutation_stale
mutation_invalid_snapshot
mutation_commit_guard_unavailable
mutation_commit_rejected
```

## Tool annotations

Mutation detection uses the same explicit annotations as the approval policy:

```text
mutation: true
requiresApproval: true
destructiveHint: true
sideEffectHint: true
readOnlyHint: false
```

For mutations, `commitGuardRequired` defaults to `true`. A legacy mutation may explicitly set:

```text
commitGuardRequired: false
```

That opt-out permits execution after exact approval but skips authorization/version revalidation. It should be temporary and documented by the owning plugin.

## Optimistic concurrency

A guarded tool should compare the reviewed version with the current version just before writing. A mismatch returns `mutation_stale`; it must not silently overwrite newer data.

The tool remains responsible for using an atomic backend write where possible, for example an update constrained by the expected version. The harness check narrows the race window but does not replace backend-level optimistic locking.

## Audit events

`MissionBayAgentActionAuditEvent` reports typed transitions:

```text
approval_requested
approval_granted
approval_denied
commit_allowed
commit_blocked
commit_succeeded
commit_failed
```

Listeners may persist these events in a project-specific audit backend. The event contains the semantic action, reason, trace metadata, and timestamp.

## Cache rule

Mutation calls are never served from or written to the tool-result cache, even if a cache rule accidentally matches them. This keeps approval and commit validation on the real execution path.

## User preferences are not approval-bound

`UserPrefsAgentResource` intentionally does not implement `IAgentMutationGuardedTool`. Its preference writes are low-risk, user-scoped operations and execute without a confirmation suspension.

The write functions remain explicitly marked as side-effecting mutations so that execution ledgers and the tool-result cache continue to treat them correctly:

```text
set_user_pref
unset_user_pref
```

Their definitions declare:

```text
mutation=true
requiresApproval=false
commitGuardRequired=false
```

Read-only functions remain explicitly non-mutating:

```text
list_allowed_prefs
list_user_prefs
```

This is a deliberate low-risk exception. Generic or destructive write tools should continue to use approval and `IAgentMutationGuardedTool`.

## Configured tool wrappers

Agent component presets are exposed to the assistant through `ConfiguredAgentToolResource`. The wrapper must preserve optional tool capabilities instead of hiding them.

For guarded mutations, the wrapper therefore also implements `IAgentMutationGuardedTool` and delegates snapshot capture, action review, and commit validation to the docked tool. When a namespace changes the externally visible function name, the wrapper translates the reviewed action back to the original function name before delegation. The reviewed action ID, input, metadata, and outer action fingerprint remain unchanged.

This keeps the wrapper transparent for guarded tools without adding tool-specific behavior to the policy, approval, or execution services. A wrapped mutation that requires a commit guard still fails closed when the docked tool does not implement `IAgentMutationGuardedTool`.


## MCP execution boundary

The inbound MCP endpoint does not run the local pending-confirmation lifecycle. It publishes the tool safety annotations and executes an authorized `tools/call` directly after Bearer-token and profile checks. The MCP client or host owns user approval. `IAgentMutationGuardedTool` remains authoritative for policy-controlled in-process MissionBay agent execution.

Read-only functions from the same configured tool remain direct calls. Mutation handling begins only for the concrete function invocation, never because another function in the catalog can mutate.

`IConfirmableAgentTool` remains a legacy direct-confirmation contract for definitions that do not explicitly require the commit guard. It does not replace or downgrade `IAgentMutationGuardedTool`.


## Tool developer guide

A complete implementation guide with definition examples, review rules, wrapper
requirements, and a testing checklist is available in
[agent-tool-development.md](agent-tool-development.md).
