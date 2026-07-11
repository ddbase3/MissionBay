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

`AssistantFoundation\Api\IAgentSuspensionRepository` defines the storage boundary.

MissionBay provides:

```text
StateStoreAgentSuspensionRepository
UnavailableAgentSuspensionRepository
```

The normal implementation stores suspension state through `IStateStore`. The unavailable implementation fails closed when a project has not configured persistent runtime state.

## Lifecycle

```text
action review
  -> persist exact suspension snapshot
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

## Security boundary

An opaque handle protects suspension state from client-side modification and supports one-time use. It is not a replacement for authorization.

Immediately before a mutation, MissionBay still needs a commit guard that:

1. rechecks the current user and permission;
2. compares the reviewed resource version with the current version;
3. rejects stale or no-longer-authorized actions;
4. records typed audit events.
