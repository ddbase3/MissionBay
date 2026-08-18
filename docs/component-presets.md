# MissionBay Component Presets

## Purpose

Component presets create reusable configured instances of discoverable MissionBay resources without creating another service container.

## Storage

Presets are stored under:

```text
agent-component-preset/<preset-id>
```

through `ISettingsStore`.

## Main services

```text
IAgentComponentPresetRepository
IAgentComponentPresetCatalog
IAgentComponentPresetMaterializer
IAgentComponentPresetFlowExpander
IAgentComponentFlowBuilder
```

### Repository

The repository performs CRUD on preset records and adds the record ID to the returned array if needed.

### Catalog

The catalog filters enabled presets by the interface implemented by the preset's resource `type`. It uses `IClassMap::getClassByInterfaceName()` and does not instantiate the resource merely to build selection options.

### Materializer

The materializer creates the actual runtime resource graph.

For every top-level materialization it resets its local resource cache and then:

1. loads the preset
2. verifies enabled state
3. resolves the discoverable resource `type`
4. creates a fresh resource instance
5. applies `config`
6. recursively materializes docked presets
7. initializes the resource with docks and context
8. derives declared/effective capabilities
9. creates capability wrappers when needed
10. returns warnings and resolved dock information

Circular dock references are detected and reported as warnings.

## Conceptual preset

```json
{
  "id": "retrieval-ilias",
  "label": "ILIAS Retrieval",
  "type": "retrievalagenttool",
  "enabled": true,
  "capabilities": ["tool"],
  "config": {
    "collection_key": {
      "mode": "fixed",
      "value": "ilias"
    }
  },
  "docks": {
    "vectordb": ["qdrant-main"]
  }
}
```

The exact config and docks depend on the selected resource implementation.

## Implementation identity versus preset identity

Do not confuse the two names:

```text
IAgentResource::getName()
  discoverable implementation identity

preset id
  configured instance identity
```

Several presets may use the same resource implementation with different settings.

## Capabilities

A materialized resource may expose capabilities such as:

```text
tool
memory
context
```

Declared capabilities are validated against the actual resource interfaces. The materializer can provide wrappers such as `ConfiguredAgentToolResource` and `ConfiguredAgentMemoryResource` when that is the canonical runtime representation.

## Docked presets

Preset docks allow one configured resource to depend on another configured resource.

This relationship is resolved at materialization time. Do not copy the child preset configuration into the parent as a workaround. The preset reference is the intended architecture boundary.

## Flow expansion

`IAgentComponentPresetFlowExpander` expands selected presets into a declarative flow definition and returns the resource IDs that correspond to each preset.

This is used by the agent compiler and by host-specific indexing flows such as MissionBayIlias.

## Profiles versus presets

A component preset configures one reusable resource.

A tool profile, memory profile or context profile selects and composes multiple presets for a particular runtime role.

An orchestrator profile configures the staged orchestration behavior.

These are different concerns. Do not merge them into one giant preset structure.

## Administration

`AgentComponentPresetAdminDisplay` edits presets. `AgentComponentPresetTestAdminDisplay` materializes and tests configured components without requiring them to be attached to a production agent first.
