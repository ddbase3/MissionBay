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

## User preferences implementation

`UserPrefsAgentResource` implements `IAgentMutationGuardedTool` directly. The existing MissionBay approval and resume flow remains unchanged.

Read-only functions are explicitly marked as non-mutating:

```text
list_allowed_prefs
list_user_prefs
```

The write functions require approval and a commit guard:

```text
set_user_pref
unset_user_pref
```

Before approval, the resource captures:

- the concrete component-preset resource ID;
- the effective user and session targets affected by the operation;
- the normalized preference value and resolved scope;
- the current preference definition hash;
- the current values of all affected rows.

A user-scoped set also captures the current session-scoped row because the write removes that session override after saving the user value. An unset without an explicit scope captures both the current user and session rows.

Immediately before execution, the resource rejects the mutation when:

- another component preset is used;
- the user or session target changed;
- the preference definition changed;
- an affected preference row changed;
- the stored snapshot does not match the approved operation.

The tool still performs its normal input validation inside `callTool()`. The commit guard adds final identity and stale-state protection; it does not replace the existing approval policy or tool contract validation.


## Configured tool wrappers

Agent component presets are exposed to the assistant through `ConfiguredAgentToolResource`. The wrapper must preserve optional tool capabilities instead of hiding them.

For guarded mutations, the wrapper therefore also implements `IAgentMutationGuardedTool` and delegates snapshot capture, action review, and commit validation to the docked tool. When a namespace changes the externally visible function name, the wrapper translates the reviewed action back to the original function name before delegation. The reviewed action ID, input, metadata, and outer action fingerprint remain unchanged.

This keeps the wrapper transparent for guarded tools without adding tool-specific behavior to the policy, approval, or execution services. A wrapped mutation that requires a commit guard still fails closed when the docked tool does not implement `IAgentMutationGuardedTool`.


## Tool developer guide

A complete implementation guide with definition examples, review rules, wrapper
requirements, and a testing checklist is available in
[AGENT_TOOL_DEVELOPMENT.md](AGENT_TOOL_DEVELOPMENT.md).
