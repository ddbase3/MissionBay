# MissionBay Configured Services

## Purpose

This document explains the provider connection and service-driver architecture used for LLMs, embeddings, images, search, vector services, parsers and speech.

## Model

```text
connection settings
  endpoint / provider connection / secret definition

service settings
  service type / connection / driver / model / options

service driver definition
  discoverable declaration of the concrete runtime implementation

ConfiguredServiceRuntimeResolver
  validates and materializes the selected implementation
```

## Exact selection

`ConfiguredServiceRuntimeResolver` does not try a list of alternative drivers.

It resolves the configured service record, then resolves the referenced connection record, then finds the exact `IServiceDriverDefinition` matching the configured service type and driver. It asks the class map for the implementation defined by that driver, creates it with its required dependencies and applies configured options.

An invalid driver or missing connection is a configuration error. It should not silently route to a different provider.

## Service groups

| Settings group | Typical foundation/runtime contract |
| --- | --- |
| `service-llm` | `IAiChatModel` |
| `service-embedding` | `IAiEmbeddingModel` |
| `service-image` | `IImageGenerationModel` |
| `service-search` | MissionBay `ISearchService` |
| `service-vectorsearch` | `IVectorSearch` / configurable search resource |
| `service-vectorstore` | `IRetrievalIndex`, `IRetrievalIndexInspector` |
| `service-parser` | `IFileParserService` / MissionBay parser service |
| `service-stt` | `ISpeechToTextService` |
| `service-tts` | `ITextToSpeechService` |

## Connections

Connections are stored under `connection`. They separate provider connectivity and secret material from per-service model choices.

A single connection can therefore support multiple service presets when the provider exposes several capabilities.

## Current built-in driver definitions

MissionBay currently contains driver definitions for:

### Chat

```text
mistralchatservicedriverdefinition
openaichatservicedriverdefinition
openaicompatiblechatservicedriverdefinition
```

### Embedding

```text
openaiembeddingservicedriverdefinition
openaicompatibleembeddingservicedriverdefinition
```

### Image

```text
mistralimageservicedriverdefinition
openaiimageservicedriverdefinition
openaicompatibleimageservicedriverdefinition
```

### Web search

```text
mistralwebsearchservicedriverdefinition
openaiwebsearchservicedriverdefinition
```

### Vector store

```text
qdrantvectorstoreservicedriverdefinition
```

### Parser

```text
doclingparserservicedriverdefinition
unstructuredparserservicedriverdefinition
```

### Speech

```text
mistralspeechtotextdriverdefinition
mistraltexttospeechdriverdefinition
openaispeechtotextdriverdefinition
openaitexttospeechdriverdefinition
```

The complete current catalog is in [component-catalog.md](component-catalog.md).

## OpenAI-compatible services

OpenAI-compatible does not mean that every provider supports every OpenAI request shape. MissionBay keeps a separate driver definition so provider-specific options and compatibility constraints stay at the service boundary.

## Service tests

Administration displays can test configured provider services. Parser testing is additionally exposed through `IParserServiceTestService`.

A successful service test proves that the current connection, driver, model and secret can perform the test operation. It does not prove that an agent or profile points to that service preset.

## Extension

A provider extension normally adds:

1. a service implementation behind the appropriate AssistantFoundation or MissionBay contract
2. an `IServiceDriverDefinition` with a stable technical name
3. connection-driver metadata if the provider needs a new connection type
4. schemas/options needed by the generic service configuration UI

Do not modify `ConfiguredServiceRuntimeResolver` with provider-specific switch branches when a service-driver definition can express the selection.
