# MissionBay Retrieval Payload Specification

This document defines the generic payload boundary used by MissionBay indexing and retrieval. MissionBay does not define one universal domain payload. Each consuming domain provides an `AssistantFoundation\Api\IRetrievalCollectionDefinition` that owns its collection schema and external retrieval view.

## 1. Responsibility split

```text
AssistantFoundation
  stable retrieval contracts and DTOs

MissionBay
  generic indexing, retrieval, Qdrant and phonetic implementations

Domain plugin
  logical collection keys
  physical collection mapping
  payload schema
  index schema
  filter allowlist
  agent context projection
```

A generic MissionBay class must not assume domain-specific field names, ACL conventions or backend collection names.

## 2. RetrievalIndexItem

`RetrievalIndexItem` is the write-side transfer object produced by the indexing node. It carries:

* logical collection key
* chunk text
* dense vector
* stable hash
* chunk position
* domain metadata
* additional search representations such as phonetic text

The domain collection definition validates the item and converts it to the final stored payload.

## 3. Stored payload

The stored payload may contain:

* text used for retrieval and context
* stable document/chunk identity
* source metadata
* ACL metadata
* phrase-search fields
* technical hashes and index markers

The exact fields are returned by:

```php
IRetrievalCollectionDefinition::getPayloadSchema()
```

A backend such as Qdrant may create payload indexes from this schema. Payload fields therefore have an explicit owner instead of being inferred by the generic vector-store implementation.

## 4. Search representations

The collection definition returns backend-neutral representation metadata through:

```php
IRetrievalCollectionDefinition::getIndexSchema()
```

A collection may define, for example:

* dense semantic vectors
* sparse lexical/BM25 vectors
* sparse phonetic vectors
* phrase-search payload fields
* phonetic phrase-search payload fields

Not every collection must expose every representation. MissionBay reads the definition instead of hard-coding a domain layout.

## 5. Agent filter boundary

Stored fields are not automatically filterable by an agent.

```php
IRetrievalCollectionDefinition::getAgentFilterSchema()
```

returns only fields and operators that a caller may expose as agent-controlled filters. Mandatory server-side filters, especially ACL constraints, are supplied independently and merged with agent filters. Agent filters may narrow retrieval but must not relax mandatory restrictions.

Filter definitions may include domain-owned descriptions, value descriptions, and examples. `RetrievalAgentTool` publishes this documentation in the `retrieval_search` schema and through `retrieval_filter_help`. The generic tool does not infer field meaning from technical names.

## 6. Agent context projection

Stored payload is not automatically returned to the LLM.

```php
IRetrievalCollectionDefinition::getAgentContextFields()
IRetrievalCollectionDefinition::projectPayload()
```

define the external retrieval view. Technical hashes, ACL arrays, internal index fields or other stored metadata remain private unless the domain explicitly exposes them.

This projection is applied inside the retrieval-index boundary before a `RetrievalHit` reaches `RetrievalAgentTool`.

## 7. Neighbor context

Collections that support neighbor retrieval define their document grouping and chunk position fields through:

```php
IRetrievalCollectionDefinition::getContextSchema()
```

`IRetrievalIndex::context()` reloads neighboring chunks while applying the supplied mandatory retrieval filter again. A previously returned point identifier therefore does not bypass access restrictions.

## 8. Design rules

* one stored point may carry multiple search representations
* collection names and domain fields belong to the domain collection definition
* generic MissionBay code does not invent payload keys
* payload schema and agent-visible context are separate concerns
* mandatory access filters are independent from agent-controlled filters
* retrieval results are projected before they leave the retrieval layer
* domain plugins may evolve their schema without introducing domain assumptions into MissionBay


## Current collection addressing

Retrieval payload processing must use the active `IRetrievalCollectionDefinition` and a logical collection key.

MissionBay stores logical-to-physical mappings in the `retrieval-collection` settings group. Generic code must not assume a fixed backend collection name. Host integrations may provide their own collection definition and schema.

The active embedding composition refers to the same logical collection through `embedding-orchestrator/default.collection_key`.

This keeps indexing, inspection and retrieval on one collection identity boundary while allowing the physical backend name to change without rewriting agent or flow definitions.

## Agent-visible projection

Stored vector payload and agent context are intentionally different surfaces. The collection definition decides which fields may be used as agent filters and which fields are projected into model-visible context.

Mandatory authorization filters supplied by server-side filter providers are additive. Agent arguments may narrow the result set but must not relax server-owned restrictions.
