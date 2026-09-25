# MissionBay Jobs

## Purpose

This document describes MissionBay background execution.

## ScheduledAgentRunnerJob

The configured-agent background job is:

```text
scheduledagentrunnerjob
```

It is discoverable through the BASE3 worker/class-map mechanism.

The job reads named agents from the `agent` Settings Store group and executes configured agents according to the active job configuration and scheduling rules.

## MissionBayToolUseCleanupJob

The tool-use retention job is:

```text
missionbaytoolusecleanupjob
```

It is discoverable through the same BASE3 worker/class-map mechanism and owns cleanup only for MissionBay's `base3_missionbay_tooluse` table.

Retention behavior:

* rows are eligible only when both `created_at` and `updated_at` are older than 24 hours;
* the existing `created_at` index bounds cleanup candidates;
* deletion is limited to 100000 rows per successful run;
* the job uses `dailywindowjobpolicy` with a window from `02:00` to `04:00`;
* the job marks the policy run only after the cleanup query has executed.

The job is active by default. The normal `job` configuration can override:

```text
missionbaytoolusecleanupjob.active
missionbaytoolusecleanupjob.priority
```

## Responsibility split

```text
BASE3 worker
  discovers and schedules jobs

ScheduledAgentRunnerJob
  selects configured agents and initiates execution

MissionBayToolUseCleanupJob
  deletes expired MissionBay tool-use audit rows

MissionBay runtime services
  compile and run agents
```

Jobs should not duplicate the agent compiler, provider resolution logic, or host-specific lifecycle handling.

## Configuration

Agent definitions remain in `ISettingsStore`. Job activation, priority, and scheduling values follow the framework job configuration used by the current installation.

`MissionBayToolUseCleanupJob` defaults to active because the tool-use audit table has a fixed 24-hour retention requirement. Setting `missionbaytoolusecleanupjob.active` to `0` disables it explicitly.

## Failure handling

A provider failure, invalid agent preset, unavailable component or invalid compilation should be reported by the scheduled-agent job result/logging path. The job must not silently switch to another model or agent definition to compensate for invalid configuration.

The tool-use cleanup job skips when its table does not exist and reports a missing database connection. It does not create or migrate schema.

## Host-specific indexing jobs

Domain indexing queues do not belong in these generic MissionBay jobs. MissionBayIlias owns its own enqueue, worker and cleanup jobs because those jobs understand ILIAS content lifecycle and tables.
