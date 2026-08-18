# MissionBay AI Development Context

## Purpose

This file is a compact development context for AI-assisted work on MissionBay. It describes current architecture boundaries and should not be treated as a runtime system prompt for end users.

## Core rules

MissionBay is a BASE3 implementation plugin for AssistantFoundation contracts, AgentFlow execution, configurable AI services and staged agent orchestration.

When changing MissionBay:

* use BASE3 dependency injection for known services
* use `IClassMap` for discoverable implementations
* keep `MissionBayPlugin::init()` lazy and composition-only
* use AssistantFoundation interfaces for stable cross-plugin contracts
* use component presets for configured `IAgentResource` instances
* use profile repositories for tool, memory, context and orchestrator composition
* use `ISettingsStore` for editable definitions
* use `IStateStore` for operational runtime state
* use `IAgentConfigValueResolver` only for MissionBay-specific late-bound values and delegate generic modes to BASE3
* resolve provider services through service-driver definitions and `ConfiguredServiceRuntimeResolver`
* never add silent provider or driver fallback chains for invalid configuration
* keep host-specific code outside MissionBay
* keep mandatory authorization filters server-owned and additive to model-requested filters

## Agent runtime

The default staged tool loop is:

```text
capability-discovery
capability-selection
model-decision
action-policy
tool-execution
context-compaction
tool-observation
semantic-verification
```

Additional registered stages are available only when explicitly composed.

Agent settings compile into one `aiassistantnode` with exactly one chat model preset plus selected tool, memory and context components.

## Flow runtime

Create flows through `IAgentFlowFactory`:

```php
$flow = $flowFactory->createFromArray('strictflow', $definition, $context);
$result = $flow->run($inputs);
```

Nodes and resources use stable lowercase `getName()` values and are discovered by the class map.

## Configuration layers

```text
connection
service-*
agent-component-preset
tool-profile / agent-memory-profile / agent-context-profile
agent-orchestrator-profile
agent
```

Do not flatten these layers into one settings record. Each has a distinct architecture role.

## Retrieval

Address vector collections through logical collection keys and `IRetrievalCollectionDefinition`. Do not hardcode backend collection names into generic tools or agents.

The model may use only filter fields and context fields exposed by the active collection definition. Stored technical and ACL metadata must not be leaked merely because it exists in the backend payload.

## Mutation safety

Mutating tools must declare accurate semantics. Approval, suspension, action fingerprint validation and the mutation commit guard are separate enforcement stages. Do not bypass them in a tool-specific fast path.

## Reference

Start with [readme.md](readme.md) and [docs/overview.md](docs/overview.md). The exact discoverable class names are in [docs/component-catalog.md](docs/component-catalog.md), and MissionBay-specific API signatures are in [docs/api-reference.md](docs/api-reference.md).
