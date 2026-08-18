# MissionBay Testing and Diagnostics

## Purpose

MissionBay contains tests for architecture invariants, services, orchestration, resources and provider behavior. This document highlights the tests and runtime diagnostics that are especially important when extending the package.

## Lazy-init test

`MissionBayPluginLazyInitTest` protects the rule that plugin initialization performs registration only and does not resolve services.

Any change to `MissionBayPlugin::init()` should preserve this test.

## Service tests

Provider configuration displays expose test operations for configured services. Parser services also use `IParserServiceTestService`.

These tests are useful for isolating layers:

```text
connection failure
  investigate connection/secret/endpoint

driver resolution failure
  investigate service type and driver definition

service test success but agent failure
  investigate component preset / profile / agent composition
```

## Component preset tests

`AgentComponentPresetTestAdminDisplay` tests resource materialization. This is the correct diagnostic boundary for questions such as:

* does the preset type exist?
* is the preset enabled?
* can docked presets be materialized?
* does the resource expose the declared capability?
* are there circular dock references?

## Effective composition

`AgentCompositionInspector` and `AgentCompositionAdminDisplay` expose what an agent actually compiles to after profiles and presets are resolved.

Use effective composition diagnostics instead of guessing from isolated settings records.

See [agent-effective-composition.md](agent-effective-composition.md).

## Retrieval diagnostics

MissionBay provides displays for:

* collection mapping
* retrieval search
* vector points
* embedding orchestrator selection

All use the same repositories and collection definition as runtime code.

## Tool logs

`AgentToolLogAdminDisplay` and the tool lifecycle listeners provide runtime visibility into tool execution.

## Dependency check

`MissionBayPlugin` implements `ICheck`. The current check reports whether `assistantfoundationplugin` is available.

## Common debugging order

1. Verify BASE3 dependencies and settings/state services.
2. Verify connection configuration.
3. Test the configured provider service.
4. Test the component preset.
5. Inspect tool/context/memory/orchestrator profiles.
6. Inspect effective agent composition.
7. Inspect tool/audit logs and provider usage logs.
8. Only then debug the agent stage or tool implementation.

This order follows the actual composition path and avoids creating diagnostic fallback behavior inside runtime services.
