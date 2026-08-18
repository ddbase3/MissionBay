# MissionBay Jobs

## Purpose

This document describes MissionBay background execution.

## ScheduledAgentRunnerJob

The current MissionBay background job is:

```text
scheduledagentrunnerjob
```

It is discoverable through the BASE3 worker/class-map mechanism.

The job reads named agents from the `agent` Settings Store group and executes configured agents according to the active job configuration and scheduling rules.

## Responsibility split

```text
BASE3 worker
  discovers and schedules the job

ScheduledAgentRunnerJob
  selects configured agents and initiates execution

MissionBay runtime services
  compile and run the agent
```

The job should not duplicate the agent compiler or provider resolution logic.

## Configuration

Agent definitions remain in `ISettingsStore`. Job activation/priority/scheduling values follow the framework job configuration used by the current installation.

## Failure handling

A provider failure, invalid agent preset, unavailable component or invalid compilation should be reported by the job result/logging path. The job must not silently switch to another model or agent definition to compensate for invalid configuration.

## Host-specific indexing jobs

Domain indexing queues do not belong in this generic job. MissionBayIlias owns its own enqueue, worker and cleanup jobs because those jobs understand ILIAS content lifecycle and tables.
