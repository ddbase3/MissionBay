# MissionBay Parsing and Indexing

## Purpose

This document explains the generic parsing and indexing building blocks inside MissionBay.

## Parser boundary

AssistantFoundation defines `IFileParserService`. MissionBay adds `IParserService` for content-item based parsing and configured parser resolution.

Current parser services include:

```text
doclingparserservice
unstructuredparserservice
```

The selected parser service is configured under `service-parser` and resolved through the generic configured-service runtime.

## Configured parser resource

`configuredparserserviceagentresource` exposes a configured parser service as an AgentFlow resource. This lets indexing flows use the same parser configuration edited through MissionBay administration.

## Parser test service

`IParserServiceTestService` allows administration UIs to test a configured parser with a small document. The test reports the selected driver/connection and returned parsed content.

## Indexing node

`aiindexingnode` coordinates a dock-based indexing pipeline.

Conceptually:

```mermaid
flowchart LR
    E[extractor] --> P[parser]
    P --> C[chunker]
    C --> M[embedder]
    M --> V[vector index]
    V --> L[logger]
```

The node uses docks rather than hardcoding one parser, chunker, embedding provider or vector backend.

## Extractor, parser and chunker roles

### Extractor

Provides source content and metadata to the flow.

### Parser

Normalizes source content into text or structured parsed content.

### Chunker

Splits parsed content into indexable segments and retains appropriate metadata.

### Embedder

Produces dense vector representations through `IAiEmbeddingModel`.

### Vector index

Persists retrieval index items through `IRetrievalIndex`.

## Generic resources

MissionBay includes reusable resources such as:

```text
uploadstreamextractoragentresource
structuredobjectparseragentresource
semanticchunkeragentresource
nochunkeragentresource
configuredembeddingmodelagentresource
configuredvectorstoreagentresource
embeddingcacheagentresource
```

Host packages can contribute domain-specific extractors and parsers without changing `AiIndexingNode`.

## Embedding cache

`EmbeddingCacheAgentResource` can avoid repeated provider embedding calls when the same normalized input is encountered. Cache storage and cleanup policy are separate from the indexing node itself.

## Host-specific flow construction

MissionBay intentionally does not decide how a host enumerates content, claims work or enforces source ACL. MissionBayIlias, for example, builds an ILIAS-specific flow around the generic indexing node and generic configured embedding/vector resources.

This keeps source-system lifecycle concerns out of the generic MissionBay package.
