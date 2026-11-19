# MissionBay Durable Agent Suspensions

## Purpose

A mutation approval may span multiple HTTP requests or worker processes. The reviewed action and the orchestration state therefore remain on the server instead of being trusted when they return from the client.

The client receives only:

```json
{
  "resume_handle": "opaque-random-handle",
  "interaction_requests": []
}
```

A resume request contains the same opaque handle and one explicit response for every interaction request:

```json
{
  "resume_handle": "opaque-random-handle",
  "responses": [
    {
      "request_id": "air-...",
      "decision": "approve"
    }
  ]
}
```

The serialized `AgentSuspension` is never accepted from the client.

## Components

`Base3\AssistantFoundation\Api\IAgentSuspensionRepository` defines the storage boundary.

`AssistantRuntime` provides the shared default implementation:

```text
Base3\AssistantRuntime\Service\StateStoreAgentSuspensionRepository
```

It stores suspension state through `IStateStore`. A suspension with a stable scope id is stored as one canonical state record. `findPending()` and `claim()` both resolve that same record; no secondary suspension copy or lookup index is maintained. MissionBay consumes only the `IAgentSuspensionRepository` contract and may be composed with another host-provided implementation.

## Lifecycle

```text
action review
  -> persist exact suspension snapshot under its stable scope
  -> reject a second pending suspension for the same scope
  -> return opaque resume handle

resume request
  -> atomically claim handle
  -> restore server-owned suspension
  -> validate request fingerprints and all user responses
  -> release claim when the response is correctable but invalid
  -> consume handle before approved tool execution
  -> keep a replay marker
```

Defaults:

```text
suspension TTL     900 seconds
claim lease         30 seconds
replay marker    86400 seconds
```

The claim contains a private random claim token. Only the owner of the current claim may release or consume it. This prevents a stale resume attempt from releasing or consuming a newer claim after its lease expired.

## Failure semantics

- Missing or expired handles are rejected.
- Concurrent resume attempts are rejected while one claim is active.
- Consumed handles are rejected as replay attempts.
- Invalid response sets release the claim so the user can correct the response while the suspension TTL remains active.
- Persistence failures stop the run before a mutation can execute.

## Nested agent calls

For a delegated A to B to C action, each existing MissionBay orchestrator preserves its own run snapshot in the configured `IAgentSuspensionRepository`. Parent suspension state additionally holds the child handle and the exact interrupted tool call ID/name. This is private continuation data inside the existing snapshot, not an extra conversation, state store or approval queue. Only the outermost handle is returned to the user. Parent and child handles are consumed once at their respective original resume boundaries.

Completed actions preceding the interrupted child call remain in the parent's stored tool results and execution records. On resume, the first pending call is the interrupted delegation, and any remaining sibling calls follow in their original order. A new child approval creates a new parent suspension; prior mutations are not repeated. If a child has expired or the configured policy no longer authorizes the paused action, the run fails closed. The parent TTL is limited by a verified child expiry when available. A missing or invalid child handle cannot trigger automatic approval.

This lifecycle does not grant global, chat-wide, agent-wide or scheduled approval. The operation's ordinary authorization and commit guard still apply.

## Security boundary

An opaque handle protects suspension state from client-side modification and supports one-time use. It is not a replacement for authorization.

Immediately before a mutation, MissionBay still needs a commit guard that:

1. rechecks the current user and permission;
2. compares the reviewed resource version with the current version;
3. rejects stale or no-longer-authorized actions;
4. records typed audit events.
