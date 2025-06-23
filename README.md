# MissionBay – AgentFlow Runtime

MissionBay is a modular flow engine for the BASE3 Framework, enabling declarative agent flows based on reusable nodes, connections, and a runtime memory context.

## Overview

* **Flow Structure**: JSON-based definition of nodes and connections
* **Node Types**: HTTP, JSON, OpenAI, Delay, Switch, Loop, Context, etc.
* **Execution Context**: Shared context with memory and runtime variables
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

## 🪩 Project Structure

```
MissionBay/
├── Agent/           # AgentContext, AgentFlow, AgentNodePort
├── Api/             # Interfaces (IAgentNode, IAgentMemory, ...)
├── Memory/          # Memory backends (NoMemory, ...)
└── Node/            # Node implementations (HttpGetNode, ...)
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

## Runtime Architecture

* `AgentFlow::fromArray(...)` – Loads a flow from JSON using `IClassMap`
* `AgentFlow::run(...)` – Executes connected nodes in dependency order
* Terminal node outputs are collected and returned

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
* ⚧️ Subflows, validation and visual tools in progress

## 📜 License

LGPL License

## 🤝 Contributing

Ideas and issues welcome.

Have a node idea? Feel free to propose a PR!

---

© BASE3 Framework

