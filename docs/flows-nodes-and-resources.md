# MissionBay Flows, Nodes, and Resources

## Purpose

This document explains the declarative AgentFlow runtime independently from the higher-level assistant profile compiler.

## Core contracts

The main MissionBay contracts are:

```text
IAgentFlow
IAgentFlowFactory
IAgentNode
IAgentNodeFactory
IAgentResource
IAgentResourceFactory
```

`IAgentFlow` is a graph runtime. `IAgentNode` is one executable graph unit. `IAgentResource` is a reusable object that may be docked into nodes.

## Flow creation

Use `IAgentFlowFactory` rather than constructing or statically parsing flows yourself.

```php
$flow = $flowFactory->createFromArray(
    'strictflow',
    $definition,
    $context
);

$output = $flow->run($inputs);
```

Available flow implementations are discovered by `getName()`.

Current built-in flow names:

```text
strictflow
dynamicaiflow
```

## Definition shape

A normal flow definition contains:

```json
{
  "nodes": [],
  "resources": [],
  "connections": []
}
```

Nodes may contain static `inputs`, `config` and `docks`.

Resources may contain `config` and may themselves have dock relationships when built through the component-preset expansion path.

## Connections

A graph connection identifies:

```text
from node id
from output port
to node id
to input port
```

The special source node `__input__` represents initial flow inputs.

Example:

```json
{
  "connections": [
    {
      "from": "__input__",
      "output": "prompt",
      "to": "assistant",
      "input": "prompt"
    }
  ]
}
```

## StrictFlow execution

`StrictFlow` resolves required inputs and graph connections deterministically. It tracks available node inputs, runs ready nodes, propagates output ports, uses the active output path when applicable and stops when no further node can execute.

The implementation includes a hard loop guard of 1000 iterations. Runtime exceptions are converted into flow error output rather than allowing an unbounded graph loop.

## Node contract

An `IAgentNode` exposes:

* one flow-local ID
* a stable implementation `getName()`
* human-readable description
* input definitions
* output definitions
* dock definitions
* runtime execution

The port definitions are expressed through `AgentNodePort`. Dock definitions use `AgentNodeDock`.

## Node families

Current built-in node areas are:

```text
Ai
Control
Core
Data
Http
Message
```

Representative examples:

| Name | Purpose |
| --- | --- |
| `aiassistantnode` | staged assistant execution |
| `aiembedtextnode` | text embeddings |
| `aiindexingnode` | extraction, parsing, chunking, embedding and vector indexing |
| `conditionalpassnode` | conditional control flow |
| `foreachnode` | iterative control flow |
| `subflownode` | execute a subflow |
| `getcontextvarnode` | read context state |
| `setcontextvarnode` | write context state |
| `httprequestnode` | generic HTTP request |
| `loggernode` | log messages |

The complete list is in [component-catalog.md](component-catalog.md).

## Resource contract

Resources are reusable runtime objects with their own stable `getName()` and flow-local resource ID. A resource can expose one or more interfaces or agent capabilities.

Examples include:

* chat models
* embedding models
* vector stores
* parser services
* memory resources
* context contributors
* agent tools
* MCP client tools
* loggers
* extractors and chunkers

## Resource implementation layout

Concrete resources are stored under `src/Resource/<primary capability>/<implementation>/`.
The folder identifies the resource's main responsibility, not the complete list of interfaces it implements.
For example:

```text
src/Resource/
	AbstractAgentResource.php
	AbstractConfiguredServiceAgentResource.php
	AgentContext/Focus/FocusAgentResource.php
	AgentContext/UserPrefs/UserPrefsAgentResource.php
	AgentMemory/Database/DatabaseMemoryAgentResource.php
	AgentMemory/Time/TimeMemoryAgentResource.php
	AgentTool/Weather/WeatherAgentTool.php
	AgentTool/Retrieval/RetrievalAgentTool.php
	ChatModel/Configured/ConfiguredChatModelAgentResource.php
	EmbeddingModel/Cache/EmbeddingCacheAgentResource.php
	Mcp/McpClientAgentResource.php
```

`FocusAgentResource` and `UserPrefsAgentResource` are context contributors and callable tools.
`ConfiguredSearchServiceAgentResource` is both a search service and a tool, and
`McpClientAgentResource` exposes tools, resources, prompts and context.
Each retains one implementation class in its primary domain. No parallel resource
registration or role-specific proxy is necessary.

The abstract base classes remain at the root of `src/Resource/` for existing
plugin extensions. The published `getName()` value is independent of the PHP
namespace and remains the identifier in saved presets and AgentFlow definitions.
`AgentResourceFactory` resolves these names through the existing `IClassMap`.
See [source-map.md](source-map.md) for every implementation path.

## Docks

A node declares which resource interface it needs at each named dock. A flow definition associates resource IDs with the dock.

Example conceptual shape:

```json
{
  "id": "indexing",
  "type": "aiindexingnode",
  "docks": {
    "extractor": ["extractor"],
    "parser": ["parser"],
    "chunker": ["chunker"],
    "embedder": ["embedding"],
    "vectordb": ["vectorstore"],
    "logger": ["logger"]
  }
}
```

Docks are the resource dependency mechanism inside a flow. They are not a replacement for constructor DI in normal runtime services.

## Context

Every flow receives an AssistantFoundation `IAgentContext`.

MissionBay's context factory can construct contexts by discoverable context implementation name. Runtime variables belong to the context; configured service dependencies belong to resources or normal constructor DI.

## Config values

A node or resource may choose to interpret configuration entries through `IAgentConfigValueResolver`. This is explicit per implementation. A raw config array is not automatically resolved recursively by the flow runtime.

## Extending the flow runtime

To add a node:

1. implement `IAgentNode` or extend the appropriate MissionBay abstract base
2. use a stable lowercase `getName()`
3. declare accurate ports and docks
4. keep constructor dependencies injectable
5. place it below the plugin `src/` tree so `IClassMap` can discover it

To add a resource, follow the same discovery rule and implement the interfaces the consuming docks expect.

Do not add a central switch statement for new node or resource types. Discovery already provides that extension boundary.

MissionBay contains the `Configured`, `Generic` and `Routing` chat-model resources. Ten direct provider-specific resources are discoverable from optional MissionBayLab under their existing technical names. Configured LLM service drivers remain in MissionBay. Direct resource configurations require the module containing the corresponding implementation.
