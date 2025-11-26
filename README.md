# MissionBay – AgentFlow Runtime

MissionBay is a modular flow engine for the BASE3 Framework, enabling declarative agent flows based on reusable nodes, connections, and a runtime memory context.

## Overview

* **Flow Structure**: JSON-based definition of nodes and connections
* **Node Types**: HTTP, JSON, OpenAI, Delay, Switch, Loop, Context, RAG, Embedding, etc.
* **Execution Context**: Shared context with memory and runtime variables
* **Resources**: Reusable services like logging, memories, extractors, parsers, chunkers, vector stores, configured independently
* **Docking**: Nodes can "dock" to external resources (e.g. logger, embedder, vector store)
* **Per-Node Config**: Nodes and Resources accept structured configuration
* **Integration**: Fully integrated with the BASE3 Framework (DI, configuration, class map)

## ⚙️ JSON-based Flows

Flows can be fully described in JSON:

```json
{
  "nodes": [
    { "id": "get", "type": "httpgetnode", "inputs": {
        "url": "https://example.com/api"
    }},
    { "id": "json", "type": "jsontoarraynode" }
  ],
  "connections": [
    { "from": "get", "output": "body", "to": "json", "input": "json" }
  ]
}
```

Then execute with:

```php
$context = new AgentContext(new NoMemory());
$flow = AgentFlow::fromArray($json, $classMap);
$results = $flow->run([], $context);
```

## 🌐 Example: AI Integration

```json
{
  "nodes": [
    {
      "id": "cfg", "type": "getconfigurationnode",
      "inputs": { "section": "openai", "key": "apikey" }
    },
    {
      "id": "ai", "type": "simpleopenainode",
      "inputs": {
        "prompt": "Tell me a Chuck Norris fact",
        "model": "gpt-3.5-turbo",
        "temperature": 0.7
      }
    }
  ],
  "connections": [
    { "from": "cfg", "output": "value", "to": "ai", "input": "apikey" }
  ]
}
```

## 🧬 Context & BASE3 Integration

The `AgentContext` provides:

* Global runtime variables via `setVar()` / `getVar()`
* Memory access through an `IAgentMemory` implementation (e.g. `NoMemory`)
* Optional DI services like `ILogger` via context injection

The `GetConfigurationNode` accesses BASE3 config (e.g. to supply OpenAI keys).
`AgentFlow` uses `IClassMap` to dynamically resolve node types.

## 🧱 Resource System (Shared Services)

AgentFlows support **resources** – reusable, dockable service objects that nodes can use.

Resources are defined separately and can be connected (via `docks`) to any number of nodes:

```json
"resources": [
  {
    "id": "log-res",
    "type": "loggerresource",
    "config": {
      "scope": { "mode": "fixed", "value": "MyApp" }
    }
  }
],
"nodes": [
  {
    "id": "telegram",
    "type": "telegramsendmessage",
    "inputs": {
      "message": "Hello!"
    },
    "docks": {
      "logger": ["log-res"]
    }
  }
]
```

### 🔌 Docks

* Each node can define **dock ports** (e.g. `"logger"`)
* These are matched with `resources` by ID
* Any node can retrieve the resource(s) for its dock at runtime

### 🔧 Resource Config

Resources can have custom configuration via the `"config"` block.
Example for a logger resource:

```json
{
  "id": "log-res",
  "type": "loggerresource",
  "config": {
    "scope": {
      "mode": "fixed",
      "value": "MySubsystem"
    }
  }
}
```

#### LoggerResource supports:

* `"mode"`:

  * `"fixed"` – always use the given value as scope
  * `"default"` – use the value only if no scope is passed
  * `"inherit"` – use whatever is passed to `log()` by the calling node
  * `"context"` – resolve scope from context via `$context->getVar(value)`
  * `"env"` – resolve scope from environment variable
  * `"config"` – resolve scope from BASE3 application config
* `"value"`: the actual string used for resolution (e.g. `"LOGGER_SCOPE"`, `"log.scope"` or a context variable name)

This is resolved by the `AgentConfigValueResolver`, which supports the following resolution logic:

| `mode`    | Description                                                                 |
| --------- | --------------------------------------------------------------------------- |
| `fixed`   | Always use `value` as-is                                                    |
| `default` | Use `value` if the calling code does not provide a value                    |
| `context` | Use `$context->getVar(value)` (e.g. `value: "currentScope"`)                |
| `env`     | Read from `getenv(value)` (e.g. `value: "LOGGER_SCOPE"`)                    |
| `config`  | Read from BASE3 `$configuration->get(value)` (e.g. `value: "logger.scope"`) |
| `inherit` | Use the value provided by the node at runtime (if any)                      |

If no value can be resolved, the final fallback is to whatever is passed to the method (e.g. from the node).
Each resource may define **its own default behavior** when no `config` is given at all.

---

## ⚙️ Node Config Blocks

Nodes can also receive a structured `"config"` object:

```json
{
  "id": "logger",
  "type": "loggernode",
  "config": {
    "level": "info",
    "includeTimestamp": true
  },
  "inputs": {
    "message": "Test log message"
  }
}
```

In the node implementation:

```php
public function setConfig(array $config): void {
        $this->logLevel = $config['level'] ?? 'debug';
}
```

This allows flexible per-node behavior **independent of inputs** (e.g. static parameters or execution flags).

Node configs can also use the same **structured resolution format** (`mode`, `value`) as resources if supported by the node.
It is up to the individual node to decide how to resolve config values using the `IAgentConfigValueResolver`.

## 🪩 Project Structure

```
MissionBay/
├── Agent/           # AgentContext, AgentFlow, AgentNodePort
├── Api/             # Interfaces (IAgentNode, IAgentMemory, ...)
├── Memory/          # Memory backends (NoMemory, ...)
├── Node/            # Node implementations (HttpGetNode, ...)
├── Resource/        # Resource implementations (LoggerResource, ...)
└── Flow/            # Flow engine (StrictFlow, AbstractFlow, etc.)
```

## ➕ Extending

New nodes can be created easily using `IAgentNode` or `AbstractAgentNode` with full input/output metadata:

```php
class UpperCaseNode extends AbstractAgentNode {
        public static function getName(): string { return 'uppercasenode'; }
        public function getInputDefinitions(): array {
                return [new AgentNodePort(name: 'text', type: 'string')];
        }
        public function getOutputDefinitions(): array {
                return [new AgentNodePort(name: 'result', type: 'string')];
        }
        public function execute(array $inputs, AgentContext $ctx): array {
                return ['result' => strtoupper($inputs['text'] ?? '')];
        }
}
```

To support config and docks:

```php
public function setConfig(array $config): void {
        $this->prefix = $config['prefix'] ?? '';
}

public function execute(array $inputs, array $resources, AgentContext $ctx): array {
        $loggers = $resources['logger'] ?? [];
        foreach ($loggers as $logger) {
                if ($logger instanceof ILogger) {
                        $logger->log('node', $this->prefix . $inputs['text']);
                }
        }
        return ['result' => strtoupper($inputs['text'])];
}
```

## Runtime Architecture

* `AgentFlow::fromArray(...)` – Loads a flow from JSON using `IClassMap`
* `AgentFlow::run(...)` – Executes connected nodes in dependency order
* Terminal node outputs are collected and returned
* Resources and node config are injected automatically before execution

---

## 🧠 RAG, Embedding, and Vector Pipelines

MissionBay also supports RAG-style pipelines through dedicated nodes and resources:

* **AiAssistantNode** – sends user prompts to a docked chat model, with memory and tool calls
* **AiEmbeddingNode** – full embedding pipeline node (extract → parse → chunk → embed → store)
* **Content Extractor Resources** – fetch raw content from DB, HTTP, files, uploads
* **Parser Resources** – select the right parser by priority (e.g. Docling, NoParser)
* **Chunker Resources** – chunk parsed text into embedding-friendly segments
* **Embedding Resources** – implement `IAiEmbeddingModel` (e.g. OpenAI embeddings)
* **Vector Store Resources** – implement `IAgentVectorStore` for read/write similarity search
* **LoggerResource** – used throughout for debug and audit logs

Typical embedding flow:

1. Cron or another trigger executes a flow containing `aiembeddingnode`.
2. One or more extractors collect raw content items.
3. Parsers decide which content they support and normalize it into `{ text, meta }`.
4. Chunkers split the text into chunks with IDs and metadata.
5. An embedding resource batches texts and returns vectors.
6. A vector store upserts vectors plus metadata.
7. Duplicate detection is performed early via a content hash and `existsByHash()` on the vector store.

Example resources used for testing:

* `DummyExtractorAgentResource` – simple static text list
* `NoParserAgentResource` – pass-through parser for plain text
* `NoChunkerAgentResource` – single-chunk strategy
* `MemoryVectorStoreAgentResource` – in-memory vector store (non-persistent)
* `OpenAiEmbeddingModelAgentResource` – OpenAI embedding API adapter

These resources live under `MissionBay\Resource\...` and all extend `AbstractAgentResource`.

---

## 🌐 MCP Endpoint (MissionBay Control Point)

MissionBay provides a **single OpenAI-compatible JSON endpoint** that allows external systems (including ChatGPT) to discover and execute agent functions via standard OpenAPI 3.1.

This is useful for:

* **Chatbot integration with function calling**
* **Automated workflows**
* **External systems triggering structured agent actions**

The endpoint is typically available at:

```
https://example.com/missionbaymcp.json
```

### `GET` Request – OpenAPI 3.1 Spec

Returns a full OpenAPI 3.1 specification including all available functions:

```json
{
  "openapi": "3.1.0",
  "info": {
    "title": "MissionBay MCP API",
    "version": "1.0.0",
    "description": "OpenAI-compatible Agent Function Interface"
  },
  "paths": {
    "/functions/reverse_string": {
      "post": {
        "summary": "Reverses a given string",
        "operationId": "reverse_string",
        "requestBody": {
          "required": true,
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "properties": {
                  "text": {
                    "type": "string",
                    "description": "Text to reverse"
                  }
                },
                "required": ["text"]
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "Successful response",
            "content": {
              "application/json": {
                "schema": {
                  "type": "object",
                  "properties": {
                    "reversed": {
                      "type": "string",
                      "description": "The reversed text"
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  }
}
```

This is **fully compatible with GPT function calling** and OpenAPI-aware tools.

### `POST` Request – Function Call

Invoke a specific function:

```
POST /mcp/functions/reverse_string
Authorization: Bearer <TOKEN>
Content-Type: application/json
```

Request body:

```json
{
  "text": "Hello"
}
```

Response:

```json
{
  "reversed": "olleH"
}
```

Internally, the server uses an `AgentContext`, logs all calls, resolves agents via the BASE3 ClassMap, and ensures token-based access protection for secure use in both backend systems and AI agents.

### MCP Server Configuration

For the BASE3 context and routing add the following to your .htaccess:

```
# Rewrite MCP function calls: /mcp/functions/<name> → index.php?name=missionbaymcp&out=json&function=functions/<name>
RewriteRule ^mcp/functions/(.+)$ index.php?name=missionbaymcp&out=json&function=$1 [L,QSA]
# MCP documentation: /mcp → index.php?name=missionbaymcp&out=json
RewriteRule ^mcp$ index.php?name=missionbaymcp&out=json [L,QSA]
```

---

## Available Node Types

| Node                 | Purpose                                                  |
| -------------------- | -------------------------------------------------------- |
| HttpGetNode          | Simple GET request                                       |
| JsonToArrayNode      | Decode JSON to PHP array                                 |
| ArrayGetNode         | Extract nested value by path                             |
| ArraySetNode         | Build associative array                                  |
| StaticMessageNode    | Emit a static message                                    |
| SimpleOpenAiNode     | Basic OpenAI chat call                                   |
| OpenAiResponseNode   | Stateful OpenAI conversation                             |
| AiAssistantNode      | Tool-enabled assistant with memory and logging           |
| AiEmbeddingNode      | Embedding pipeline (extract, parse, chunk, embed, store) |
| SwitchNode           | Branch by value (like switch)                            |
| IfNode               | Simple true/false branching                              |
| ForEachNode          | Loop over a list of items                                |
| LoopNode             | Generic repeat structure                                 |
| DelayNode            | Pause for N seconds                                      |
| SetContextVarNode    | Save value into context                                  |
| GetContextVarNode    | Read value from context                                  |
| HttpRequestNode      | Full HTTP client (GET/POST/...)                          |
| TestInputNode        | Return static or test value                              |
| LoggerNode           | Write to the BASE3 logger                                |
| GetConfigurationNode | Load BASE3 config values                                 |
| SubFlowNode          | Execute a nested sub-flow                                |

## 📌 Requirements

* PHP 8.1 or higher
* PSR-4 Autoloading
* ClassMap service for dynamic node resolution

## ✅ Status

* ✔️ Stable architecture
* ✔️ JSON-based agent flows fully supported
* ✔️ Configuration, context, logging, memory integrated
* ✔️ Resource and docking system fully supported
* ✔️ RAG and embedding pipelines available
* ⚧️ Subflows, validation and visual tools in progress

## 📜 License

GPL 3.0 License

## 🤝 Contributing

Ideas and issues welcome.

Have a node idea? Feel free to propose a PR!

---

© BASE3 Framework
