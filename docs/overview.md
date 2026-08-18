# MissionBay Overview

## Purpose

This document gives a high-level map of the current MissionBay package and explains where each major responsibility belongs.

MissionBay is not only an AgentFlow engine. The current package combines a flow runtime, the MissionBay implementation of AssistantFoundation runtime contracts, configurable AI service resolution, a staged tool orchestrator, retrieval infrastructure, parser and speech services, MCP integration, administration displays, auditing and scheduled execution.

For a new developer, the most useful model is:

```text
AssistantFoundation
  stable cross-plugin contracts

AssistantRuntime
  generic runtime selection and routing

MissionBay
  concrete runtime, flow and provider implementation

MissionBayIlias / MissionBayReporting
  host and domain extensions
```

## Main source areas

| Directory | Responsibility |
| --- | --- |
| `Agent/` | flow factories, context construction, config value resolution and knowledge helpers |
| `Ai/` | chat message adaptation, provider request events and normalized results |
| `Api/` | MissionBay-specific contracts |
| `Audit/` | per-run tool audit context |
| `Cache/` | tool result cache implementations and cache-key construction |
| `Capability/` | capability discovery, catalogs and selection |
| `ChatModel/` | concrete chat model implementations |
| `Composition/` | effective agent composition inspection |
| `Connection/` | connection configuration representation |
| `ConnectionDriver/` | connection driver definitions |
| `Content/` | output/content endpoints |
| `Context/` | MissionBay agent context and context profile integration |
| `Display/` | administration displays |
| `Dto/` | MissionBay-specific DTOs |
| `EmbeddingModel/` | embedding model implementations |
| `Event/` | MissionBay runtime event objects |
| `Flow/` | AgentFlow implementations |
| `Hook/` | lifecycle hook listeners that attach event listeners |
| `ImageModel/` | image generation implementations |
| `InfoProvider/` | agent information topic providers |
| `Job/` | background jobs |
| `Listener/` | event listeners |
| `Mcp/` | MCP server, client, authorization and catalogs |
| `Memory/` | MissionBay memory implementations |
| `Node/` | AgentFlow nodes |
| `Orchestrator/` | staged assistant tool loop, policies, services and profiles |
| `ParserService/` | configured parser services and tests |
| `Policy/` | action policies |
| `Profile/` | tool, context and memory profile resolvers |
| `Resource/` | dockable agent resources and tools |
| `Retrieval/` | retrieval collection, query materialization and phonetic support |
| `SearchService/` | model-backed search implementations |
| `Service/` | configuration repositories and runtime services |
| `ServiceDriver/` | discoverable service-driver definitions |
| `Speech/` | STT and TTS runtime adapters and provider drivers |
| `Tool/` | tool profile integration |
| `Transport/` | provider HTTP transports |
| `VectorStore/` | vector-store service implementations |

## Runtime layers

MissionBay has several runtime layers that should not be collapsed into one abstraction.

### Provider service layer

Connections and service settings choose provider implementations for chat, embeddings, images, search, vector services, parsers and speech.

### Component preset layer

Component presets turn discoverable `IAgentResource` implementations into reusable configured instances with stable preset IDs.

### Agent compilation layer

Agent settings, profiles and component presets compile into a MissionBay flow definition.

### Flow execution layer

`IAgentFlowFactory` instantiates the requested flow implementation and executes the graph inside an `IAgentContext`.

### Assistant tool orchestration layer

`AiAssistantNode` and the orchestrator stages perform capability discovery, bounded selection, model decisions, action-policy checks, tool execution, context compaction, observation and semantic verification.

### Generic runtime layer

MissionBay exposes execution, conversation and text-task services that can be selected through AssistantRuntime.

## Data ownership

```text
IConfiguration
  static framework and deployment configuration

ISettingsStore
  editable MissionBay definitions

IStateStore
  transient or persistent operational state

IConfigValueResolver
  generic late-bound values

IAgentConfigValueResolver
  MissionBay-specific value modes layered over the generic resolver
```

Do not move one concern into another storage mechanism merely because it is technically possible.

## Where to continue

Read [architecture.md](architecture.md) next, then use the topic-specific documents from the main [readme](../readme.md).
