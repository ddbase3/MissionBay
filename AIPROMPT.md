# AIPROMPT.md

This file collects useful prompt templates for setting up a Custom GPT that fully supports the MissionBay plugin system — particularly for developing new nodes and assembling agent flows.

Each section contains a short explanation and a ready-to-use prompt block.

---

## 🧠 Initial GPT Setup Prompt

**Purpose:** This is the foundational system prompt for setting up a Custom GPT tailored to support MissionBay node and flow development.

```text
You are a helpful assistant that supports development within the BASE3 Agent system "MissionBay". You help create new nodes and assemble executable agent flows using the MissionBay plugin framework.

Guidelines:
- All code and comments must be written in English.
- Use PHP.
- Use tabs (not spaces) for indentation.
- Output complete, minimal working examples.
- Use the class naming convention: getName() must return the lowercase class name.
- Each node must implement getInputDefinitions(), getOutputDefinitions(), getDescription(), and execute().
- Flows are defined in JSON using nodes and connections.
- Always ask for the current node list from https://.../agentnodes.json as context input.
```

---

## 🔹 1. Request Node List from Hosted Project

**Purpose:** This prompt asks the user to paste the latest node list from their web project. Since direct URL access may be unreliable, the GPT requests the raw JSON content explicitly.

```text
To get started, ask the user to copy and paste the full contents of your node list from:
https://[your-domain]/agentnodes.json

This file describes all available node types with names, classes, inputs, outputs, and descriptions. Once you've pasted it here, I will understand your current node environment.
```

---

## 🔹 2. Example Node Definition (PHP)

**Purpose:** Provides a minimal and valid Node implementation for GPT to use as a pattern when generating new ones.

```text
Here is an example of a complete node implementation:

<?php declare(strict_types=1);

namespace MissionBay\Node;

use MissionBay\Api\IAgentNode;
use MissionBay\Agent\AgentContext;

class StringReverserNode extends AbstractAgentNode {

	public static function getName(): string {
		return 'stringreversernode';
	}

	public function getInputDefinitions(): array {
		return ['text'];
	}

	public function getOutputDefinitions(): array {
		return ['reversed'];
	}

	public function execute(array $inputs, AgentContext $context): array {
		$text = $inputs['text'] ?? '';
		$reversed = strrev($text);
		return ['reversed' => $reversed];
	}

	public function getDescription(): string {
		return 'Reverses the given input string and returns the result. Useful for string manipulation, testing, or flow demonstrations.';
	}
}
```

---

## 🔹 3. Example Flow as JSON

**Purpose:** Sample agent flow in raw JSON format, to serve as reference or template for creating new flows programmatically or manually.

```json
{
  "nodes": [
    {
      "id": "cfg",
      "type": "getconfigurationnode",
      "inputs": {
        "section": "openaiconversation",
        "key": "apikey"
      }
    },
    {
      "id": "http",
      "type": "httpgetnode",
      "inputs": {
        "url": "https://api.chucknorris.io/jokes/random"
      }
    },
    {
      "id": "json",
      "type": "jsontoarraynode"
    },
    {
      "id": "get",
      "type": "arraygetnode",
      "inputs": {
        "path": "value"
      }
    },
    {
      "id": "originalmsg",
      "type": "staticmessagenode"
    },
    {
      "id": "ai",
      "type": "simpleopenainode",
      "inputs": {
        "system": "You are a sarcastic Chuck Norris fan.",
        "model": "gpt-3.5-turbo"
      }
    }
  ],
  "connections": [
    { "from": "http", "output": "body", "to": "json", "input": "json" },
    { "from": "json", "output": "array", "to": "get", "input": "array" },
    { "from": "get", "output": "value", "to": "ai", "input": "prompt" },
    { "from": "get", "output": "value", "to": "originalmsg", "input": "text" },
    { "from": "cfg", "output": "value", "to": "ai", "input": "apikey" }
  ]
}
```

---

## 🔹 4. Flow Execution in PHP with `AgentFlow::fromArray()`

**Purpose:** Show how a flow defined in JSON can be parsed and executed at runtime in MissionBay.

```
Usage of a JSON flow:

$context = new AgentContext(new NoMemory());
$json = file_get_contents('path/to/flow.json');
$data = json_decode($json, true);
$flow = AgentFlow::fromArray($data, $classMap);
$outputs = $flow->run([], $context);
```

This enables dynamic, programmable execution of agent flows defined externally.

---

## 🔹 5. Agent Types and Integration Contexts

**Purpose:** Explain how agents are integrated into the BASE3 framework and executed based on their interface.

```text
Agents can be triggered in different ways depending on their interface:

1. Base3\Api\IOutput:
   - Executed via HTTP route, CLI, cron, or webhook
   - Suitable for interactive agents and developer tools

2. Base3\Worker\Api\IJob:
   - Executed via queue workers or background schedulers
   - Best for scalable, asynchronous flows or heavy tasks

Choose the appropriate interface depending on how and where the flow will be run.
```

---

## ✅ Summary

With the above prompts, a Custom GPT can:

- Understand the available nodes
- Help generate new Node classes in PHP
- Create and analyze JSON-based agent flows

These prompts provide a fallback mechanism if direct access to the node list URL is not possible.

