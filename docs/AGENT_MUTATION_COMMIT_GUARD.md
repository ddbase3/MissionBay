# MissionBay Mutation Commit Guard

## Purpose

User approval binds a mutation to an exact tool name and input. It does not guarantee that authorization or target data are still current when execution resumes.

MissionBay therefore performs a final commit check inside `tool-execution`, immediately before `IAgentTool::callTool()`.

## Runtime flow

```text
action-policy requires approval
  -> AgentActionReviewService
       -> capture authorization/resource snapshot
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

It provides two operations:

```text
captureMutationCommitSnapshot()
validateMutationCommit()
```

The captured `AgentMutationCommitSnapshot` stays inside the server-owned suspension and is not included in the public interaction request. It should contain only the stable data needed for the later check, typically:

- authorization subject or tenant identity;
- required permission or scope;
- target resource IDs;
- expected versions, revisions, hashes, or ETags.

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
