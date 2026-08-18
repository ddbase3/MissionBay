# MissionBay Bootstrap and Lazy Composition

## Purpose

This document explains what `MissionBayPlugin::init()` is allowed to do and why lazy service registration is a protected behavior.

## Core rule

MissionBay plugin initialization is composition-only.

```text
Allowed in init()
  register services
  register aliases/defaults
  register component definitions
  register the plugin object

Not allowed in init()
  resolve runtime services
  load settings for runtime behavior
  query the database
  perform model/provider calls
  execute jobs
  inspect request-specific state
```

## Test-protected behavior

`test/MissionBayPluginLazyInitTest.php` verifies two properties:

1. `init()` does not call `get()` on the container while registering the plugin.
2. Runtime services are registered as closures, parameters remain declarative values, and aliases remain aliases.

This test turns lazy initialization into an explicit package invariant.

## Why it matters

MissionBay can run inside host systems with large plugin graphs. Eager service construction during bootstrap can:

* open database connections before the project has finalized infrastructure
* read settings too early
* instantiate optional services that are never used
* trigger circular construction
* make CLI and setup processes depend on request-only services
* cause provider network behavior during application startup

Lazy registration avoids these problems at the correct architecture boundary.

## Default services

MissionBay registers many runtime services as shared lazy factories. Some registrations use `NOOVERWRITE` because they provide a replaceable default. Others deliberately provide the MissionBay implementation.

Examples of fallback/default bindings include:

```text
IEventManager
IConfigValueResolver
IAgentConfigValueResolver
IMcpTransport
IMcpClientFactory
IAgentComponentPresetRepository
IAgentComponentPresetCatalog
IAgentComponentPresetMaterializer
IAiModelConfigurationProvider
ISpeechToTextService
IRealtimeSpeechToTextSessionService
ITextToSpeechService
IAgentCapabilitySelector
IAgentToolResultCache
```

A project or extension can replace a fallback before it is resolved.

## Declarative component definitions

Default stages and policies are not instantiated inside `init()`. The plugin registers `ComponentDefinition` parameters.

Default action policies:

```text
mutation-approval-actions
allow-all-actions
```

Default stage definitions:

```text
capability-discovery
capability-selection
ai-capability-selection
model-decision
action-policy
tool-execution
context-compaction
tool-observation
semantic-verification
final-answer-regenerate
```

Only a subset is active in the default pipeline. Registration means the component can be resolved, not that it automatically executes.

## Context compaction defaults

The default `context-compaction` component definition is configured with:

```text
minToolResultBytes = 12000
maxInputBytes = 80000
targetSummaryCharacters = 4000
```

These are implementation defaults of the registered component definition. An orchestrator composition can choose a different configured stage if needed.

## Project overrides

When a project needs a different final implementation, replace the appropriate service binding or configured component at composition time. Do not add runtime branching in unrelated services merely to preserve the default implementation alongside the project choice.
