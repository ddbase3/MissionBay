# Discoverable Service Drivers

## Purpose

MissionBay consumes the generic driver contracts from AssistantFoundation. It does not own a separate driver registry and does not require a core change when another plugin supplies a driver.

Shared contracts:

```text
AssistantFoundation\Api\IServiceDriverDefinition
AssistantFoundation\Api\IConnectionDriverDefinition
```

## Resolution

A configured service contains:

```text
service type
driver id
implementation-specific model and options
connection id
```

At runtime MissionBay:

1. discovers `IServiceDriverDefinition` instances through `IClassMap`;
2. selects the definition matching service type and driver id;
3. reads its implementation interface and implementation name;
4. resolves the named implementation through `IClassMap`;
5. passes values from the referenced connection and service configuration to that implementation.

There is no hardcoded map for OpenAI, Mistral, embedding, image, search, parser, vector-store, or speech drivers.

## Configuration boundary

Connection settings are maintained only through the connection configuration:

```text
base URL
authentication type
secret/API key resolver
HTTP header
default connection timeout
```

Service displays contain only:

```text
connection reference
driver
model
operation-specific options
enabled state
```

Service-driver schemas must not introduce fields for endpoint, API key, authentication, or secret storage. ServiceConfig also removes such keys from existing option records, and the administration rejects them in advanced option JSON.

## Built-in and external drivers

MissionBay currently ships built-in definitions for its existing OpenAI, OpenAI-compatible, Mistral, Qdrant, Docling, and Unstructured integrations. These classes implement the same AssistantFoundation contract used by external provider plugins.

A specialty plugin can therefore provide another driver without adding a branch to MissionBay. The plugin contributes its definition and runtime implementation under the technical names declared by the definition.

## Provider transport boundary

Provider transports implement `AssistantFoundation\Api\IAiProvider`. Model and service adapters depend on provider-neutral Foundation contracts wherever the exchanged DTOs are provider-neutral.

MissionBay-specific runtime interfaces remain in MissionBay only when their method signatures intentionally use MissionBay-owned domain types. Their service-driver definitions still use the single AssistantFoundation definition contract.


## Vector-search services

Simple similarity-search providers use service type `vectorsearch` and runtime interface:

```text
AssistantFoundation\Api\IConfigurableVectorSearch
```

MissionBay exposes them through `ConfiguredVectorSearchAgentResource` and settings group `service-vectorsearch`. This slot is intentionally separate from `vectorstore`: vector stores own persistence, collection management, upsert and deletion, while vector-search services only execute similarity searches.

The vector-search service stores a connection reference, driver, engine/model marker and provider-specific search options such as a collection name. Endpoint, authentication header and secret remain exclusively in the selected connection.
