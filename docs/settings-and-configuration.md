# MissionBay Settings and Configuration

## Purpose

This document lists the persistent settings domains used by MissionBay and explains what belongs in each one.

## Storage model

MissionBay primarily uses BASE3 `ISettingsStore` for editable named configuration records.

Operational markers and caches use `IStateStore`. Static deployment configuration remains in BASE3 `IConfiguration`.

## Settings groups

### `agent`

Named runnable agent definitions.

The current compiler consumes fields including:

```text
chatmodel
orchestrator_profile
memory_profile
context_profile
tool_profiles
agent_components
expert_overrides_enabled
capability_sources
capability_selection
```

`chatmodel` is required by `AgentFlowCompiler`.

### `agent-component-preset`

Reusable configured `IAgentResource` definitions.

Each record is addressed by preset ID and normally contains a resource `type`, optional `config`, optional `docks`, optional capability declarations, label metadata and an `enabled` value.

### `agent-orchestrator-profile`

Named orchestrator pipeline profiles. A profile can control stage IDs, maximum tool loops, capability selection, model-decision configuration and planning behavior.

### `agent-memory-profile`

Named sets of component presets attached as memory capabilities.

### `agent-context-profile`

Named sets of context-contributor component presets.

### `tool-profile`

Named tool profiles. MissionBay and the MCP integration both use this group to define bounded tool sets.

### `connection`

Named provider connection definitions. Service presets reference these records.

### Provider service groups

```text
service-llm
service-embedding
service-image
service-search
service-vectorsearch
service-vectorstore
service-parser
service-stt
service-tts
```

A service record identifies the service type, connection, driver, provider/model information, enablement and driver-specific options.

### `retrieval-collection`

Maps a logical collection key to a physical backend collection name.

Conceptual record:

```json
{
  "backend_collection": "physical_qdrant_collection"
}
```

The repository normalizes technical keys and prevents ambiguous physical mappings.

### `embedding-orchestrator`

The active embedding composition is stored under the name `default`.

Required values:

```json
{
  "embedding_preset": "embedding-default",
  "vector_store_preset": "qdrant-default",
  "collection_key": "content"
}
```

All three values are required when saving.

## Settings ownership

Avoid duplicating the same selection across groups.

For example:

```text
connection
  provider endpoint and credential reference

service-embedding
  selected connection, driver, model and embedding options

agent-component-preset
  configured resource referring to the service preset

embedding-orchestrator
  selected embedding resource preset, vector-store resource preset and collection key
```

Each layer answers a different question.

## Runtime value resolution

`IAgentConfigValueResolver` wraps the generic BASE3 `IConfigValueResolver`.

Generic modes such as fixed, configuration, environment and file remain owned by BASE3. MissionBay adds only agent-specific runtime modes handled by `AgentConfigValueResolver`, currently including:

* `inherit`
* `random`
* `uuid`

Unknown generic behavior should not be reimplemented in MissionBay.

## State keys

MissionBay runtime state includes:

| Prefix or key | Purpose |
| --- | --- |
| `missionbay.agent_tool_result_cache.` | cached tool results |
| `missionbay.agent.tool_loop.` | tool-loop context values |
| `missionbay.agent.tool_audit.current` | current in-process tool audit context key |
| `base3_missionbay_conversation_memory_format` | session memory format marker |
| `base3_missionbay_conversation_memory_chunk_count` | session memory chunk count |
| `base3_missionbay_conversation_memory_chunk_` | session memory chunks |

The exact persistence backend comes from the active BASE3 state or session services.

## Secrets

Service settings should reference connection and secret configuration instead of copying resolved secret values into multiple presets. The configured runtime resolver resolves the connection at use time.

MCP profile credentials can use `ICredentialAccess` with the service prefix:

```text
missionbay:mcp:
```

## Administration

The displays in `src/Display/` provide editing and diagnostics for these settings groups. See [administration.md](administration.md).
