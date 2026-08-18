# MissionBay Retrieval and Vector Search

## Purpose

This document explains MissionBay's generic retrieval architecture. Host-specific schemas such as the ILIAS retrieval schema are owned by their extension packages.

## Main contracts

MissionBay consumes AssistantFoundation retrieval contracts including:

```text
IRetrievalCollectionDefinition
IRetrievalIndex
IRetrievalIndexInspector
IVectorSearch
IConfigurableVectorSearch
IRetrievalFilterProvider
```

MissionBay adds configuration and high-level retrieval services around them.

## Logical collection keys

Consumers address collections by a logical key.

The mapping from logical key to backend collection name is stored by `IRetrievalCollectionConfigRepository` in the `retrieval-collection` settings group.

```mermaid
flowchart LR
    K[logical collection key] --> D[IRetrievalCollectionDefinition]
    D --> R[IRetrievalCollectionConfigRepository]
    R --> B[physical backend collection]
```

Generic agent settings should not embed physical Qdrant collection names.

## Collection definition responsibility

`IRetrievalCollectionDefinition` is more than a name mapper. A concrete definition can own:

* collection keys
* backend names
* dense and sparse representation schema
* payload schema
* payload validation
* agent-visible filter schema
* agent-context projection
* context grouping and ordering
* phonetic encoding choice

This makes the definition the boundary between stored retrieval metadata and the data a model is allowed to filter or receive.

## Default versus host-specific definition

MissionBay registers `DefaultRetrievalCollectionDefinition` as a default. A host extension can replace it at composition time.

MissionBayIlias replaces this service with `IliasRetrievalCollectionDefinition` because ILIAS needs ACL fields, object-tree metadata and a stricter agent projection.

## Retrieval tools

Current generic resources include:

```text
retrievalagenttool
ragsearchagenttool
configuredvectorsearchagentresource
configuredvectorstoreagentresource
qdrantvectorsearch
```

`RetrievalSearchService` can list component presets that expose retrieval search and execute search/context operations through the materialized configured component.

## Filters

Agent-requested filters are data, not authority.

A retrieval resource may receive filter providers that inject mandatory server-side constraints. Host extensions can therefore enforce ACL, tenant, source-kind or other mandatory constraints independently from what the model asks for.

Do not expose a technical filter field to the agent merely because the vector backend can filter it. The collection definition decides the agent filter surface.

## Stored payload versus agent context

The retrieval backend may need technical fields for indexing, deletion, ACL or bookkeeping. The model should receive only the collection definition's projected context fields.

This is formalized further in [../rag-payload-spec.md](../rag-payload-spec.md).

## Vector store configuration

Vector stores are normal configured services. `service-vectorstore` selects a driver and connection. A component preset can wrap that configured service as a reusable agent resource.

The embedding orchestrator then selects the vector-store component preset by ID rather than duplicating the vector service configuration.

## Embedding orchestrator

MissionBay stores the active generic indexing composition under:

```text
embedding-orchestrator/default
```

with:

```text
embedding_preset
vector_store_preset
collection_key
```

Host integrations can use this configuration to build their own domain-specific indexing flow.

## Qdrant

The built-in vector-store path includes Qdrant support. Qdrant-specific connection and service options belong to the corresponding connection/service driver and vector-store implementation, while the logical payload schema remains owned by `IRetrievalCollectionDefinition`.
