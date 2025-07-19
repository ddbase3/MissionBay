# MissionBay – AgentFlow Runtime

MissionBay is a modular flow engine for the BASE3 Framework, enabling declarative agent flows based on reusable nodes, connections, and a runtime memory context.

## Overview

* **Flow Structure**: JSON-based definition of nodes and connections
* **Node Types**: HTTP, JSON, OpenAI, Delay, Switch, Loop, Context, etc.
* **Execution Context**: Shared context with memory and runtime variables
* **Resources**: Reusable services like logging, configured independently
* **Docking**: Nodes can "dock" to external resources (e.g. logger)
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

## Available Node Types

| Node                 | Purpose                         |
| -------------------- | ------------------------------- |
| HttpGetNode          | Simple GET request              |
| JsonToArrayNode      | Decode JSON to PHP array        |
| ArrayGetNode         | Extract nested value by path    |
| ArraySetNode         | Build associative array         |
| StaticMessageNode    | Emit a static message           |
| SimpleOpenAiNode     | Basic OpenAI chat call          |
| OpenAiResponseNode   | Stateful OpenAI conversation    |
| SwitchNode           | Branch by value (like switch)   |
| IfNode               | Simple true/false branching     |
| ForEachNode          | Loop over a list of items       |
| LoopNode             | Generic repeat structure        |
| DelayNode            | Pause for N seconds             |
| SetContextVarNode    | Save value into context         |
| GetContextVarNode    | Read value from context         |
| HttpRequestNode      | Full HTTP client (GET/POST/...) |
| TestInputNode        | Return static or test value     |
| LoggerNode           | Write to the BASE3 logger       |
| GetConfigurationNode | Load BASE3 config values        |
| SubFlowNode          | Execute a nested sub-flow       |

## 📌 Requirements

* PHP 8.1 or higher
* PSR-4 Autoloading
* ClassMap service for dynamic node resolution

## ✅ Status

* ✔️ Stable architecture
* ✔️ JSON-based agent flows fully supported
* ✔️ Configuration, context, logging, memory integrated
* ✔️ Resource and docking system fully supported
* ⚧️ Subflows, validation and visual tools in progress

## 📜 License

LGPL License

## 🤝 Contributing

Ideas and issues welcome.

Have a node idea? Feel free to propose a PR!

---

© BASE3 Framework
