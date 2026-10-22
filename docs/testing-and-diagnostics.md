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


### Tool-use retention diagnostic

`ToolUseLogRetentionDiagnosticCheck` implements the new framework `IDiagnosticCheck` contract for the `privacydiagnosticscope` scope. It is discovered through `IClassMap` and executed on demand by `IDiagnosticRunner`. It queries only an aggregate count of records older than 48 hours (both `created_at` and `updated_at`), never returns the contents of an audit log or a user identifier, and never changes records. The threshold leaves time for the existing `MissionBayToolUseCleanupJob` to remove rows older than 24 hours in its nightly window. Both use `ToolUseRetention` for the common timestamp predicate.

States: `passed` when the count is zero; `failed` when greater than zero; `not_applicable` if the table is absent; `error` if the query cannot be evaluated. For failure the translated recommendation tells operators to verify the cleanup job and worker. Translations are in `lang/DiagnosticChecks/de.ini` and `en.ini`. This diagnostic does not replace or activate the cleanup job.

### External HTTP transport diagnostic

`ExternalConnectionTransportDiagnosticCheck` inspects enabled HTTP connection settings without initiating network requests or resolving secrets. It counts remote endpoints that are configured without `https`, ignores disabled/non-HTTP connections and local loopback connections, and warns on active HTTP connections with unparseable or missing URLs. Results contain only aggregate counts, never endpoint URLs, query parameters or secrets. It checks the configured transport scheme only, not certificate validity or the external provider's processing location. All visible texts belong to MissionBay's `lang/DiagnosticChecks` catalogs.

### AI usage reporting feature

`AiUsageReportsFeature` is classmap-discoverable and owns the `feature/aiusagereportsfeature` permission. Its default audience is the logical BASE3 `development` group, with effective audience decisions delegated to the active `IAuthorizationService`. The MissionBay-owned feature translations are in `lang/Features/`. The ILIAS Lab administration uses the decision to include or omit the AI Usage main tab. This navigation filter does not, on its own, protect other direct reporting entry points; the active reporting backend is responsible for server-side data access authorization.
