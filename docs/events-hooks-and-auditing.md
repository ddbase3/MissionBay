# MissionBay Events, Hooks, and Auditing

## Purpose

This document explains the runtime notification and audit paths in MissionBay.

## Event objects

MissionBay currently defines event objects for:

```text
MissionBayToolStartedEvent
MissionBayToolFinishedEvent
MissionBayToolFailedEvent
MissionBayAgentActionAuditEvent
```

Tool lifecycle events allow logging and UI integrations to observe execution without making the orchestrator depend on those consumers directly.

## AI provider request events

`AiProviderRequestEventDispatcher` dispatches normalized provider-request information through the BASE3 event manager. AI usage listeners can persist or report model usage independently from the provider model implementation.

## Hook listeners

MissionBay uses BASE3 hook listeners to attach runtime event listeners during framework lifecycle initialization.

Current hook listeners include:

```text
MissionBayAiUsageEventRegistrationHookListener
MissionBayToolEventRegistrationHookListener
```

The hook is the lifecycle extension point. The event is the runtime notification path.

## Listeners

Current listeners include:

```text
MissionBayAiUsageLogListener
MissionBayToolEventDisplayListener
```

The usage listener writes normalized AI usage data. The tool display listener persists or exposes user-visible tool lifecycle information with the active database and user context.

## Tool audit context

`AgentToolAuditContext` keeps run-local audit information under the context key:

```text
missionbay.agent.tool_audit.current
```

This lets nested tool execution associate emitted events with the active run or action without a global parallel state model.

## Action audit versus provider usage

These are separate concerns:

```text
action audit
  what the agent/tool attempted and what policy/review decided

provider usage
  model/provider request and token/usage metadata
```

Keeping them separate avoids conflating authorization evidence with billing/usage data.

## Extension

Add a runtime listener when another subsystem needs to react to an event. Add a direct service dependency when the action is part of the source component's required behavior. Do not convert required business operations into events merely to reduce constructor arguments.
