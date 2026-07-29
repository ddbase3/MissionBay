# MissionBay Conversation Memory and Context Contributors

## Purpose

MissionBay separates three concerns:

```text
Conversation memory
  visible user/assistant history and conversation metadata

Context contributor
  run-local system-context blocks such as time, preferences, or page data

Knowledge / Skills tool
  explicit agent-owned storage accessed through tool calls
```

Conversation history is independent from tools. A failed or unused Knowledge tool does not affect whether visible messages remain available in later turns.

## Conversation contract

A visible chat-history backend implements:

```text
AssistantFoundation\Api\IAgentConversationMemory
```

The interface extends `IAgentMemory` and adds explicit conversation operations:

```text
bindConversationScope()
listConversations()
getConversation()
getActiveConversation()
createConversation()
activateConversation()
renameConversation()
deleteConversation()
touchConversation()
```

The existing node-history methods continue to store and load the messages of the currently bound conversation.

A conversation scope contains:

```text
owner_key
channel_id
conversation_id
```

The memory backend determines the owner from the authenticated user or active session. The agent context provides only the stable `conversation_channel_id` and the optional `conversation_id`.

`channel_id` identifies one concrete chatbot or agent instance. Different chatbot instances therefore remain isolated even when they use the same agent and memory profiles.

## Canonical implementations

MissionBay provides exactly two conversation-memory implementations:

```text
MissionBay\Resource\SessionMemoryAgentResource
MissionBay\Resource\DatabaseMemoryAgentResource
```

`SessionMemoryAgentResource` stores the complete canonical conversation structure through `ISession`.

`DatabaseMemoryAgentResource` stores conversation metadata and messages in:

```text
base3_missionbay_conversation
base3_missionbay_conversation_message
```

Its `ensureTables()` method creates missing tables with `CREATE TABLE IF NOT EXISTS`. It never alters existing tables. It does not use transactions, `insertId()`, `affectedRows()`, or database error-state methods.

`ConfiguredAgentMemoryResource` is the configuration wrapper for one concrete conversation memory. It delegates the complete conversation contract and does not create another storage layer.

`NoMemory` is only a generic no-op `IAgentMemory`; it is not a conversation-history backend.

## Memory profiles

A Memory Profile stores its selected preset in the canonical field:

```text
memories
```

A valid Memory Profile contains exactly one enabled preset whose resource implements `IAgentConversationMemory`.

There is no priority-based choice between several writable histories and no implicit conversion of generic `IAgentMemory` implementations into conversation memory.

## Context profiles

A Context Profile stores its selected presets in the canonical field:

```text
contexts
```

Each selected resource must implement:

```text
AssistantFoundation\Api\IAgentContextContributor
```

Context contributors return typed `AgentInstructionBlock` values. They do not receive user/assistant history writes.

## Turn preparation

A new turn is assembled in this order:

```text
base system instruction
  -> context-contributor blocks
  -> visible conversation history
  -> current user message
```

The current user message is written to the active conversation memory before later capability discovery, action policy, tool execution, or model processing can fail.

## Suspension and resume

Context contributors are resolved once when a new turn starts. A suspended mutation resumes with the frozen reviewed message set rather than re-reading current preferences, page state, or other contributors.

```text
new turn
  -> resolve context contributors
  -> build messages
  -> possible suspension

resume
  -> restore frozen messages and reviewed action
```

## Docks

```text
memory
  IAgentConversationMemory

contextcontributors
  IAgentContextContributor

tools
  MissionBay\Api\IAgentTool
```

Knowledge / Skills is attached through `tools`, not through either memory dock.

## Diagnostics

Effective Composition reports the explicit roles:

```text
conversation-memory
context-contributor
tool
```

It also shows the contributing profile, concrete preset id, implementation, effective dock, priority, and redacted configuration.

## Suggestion turns

Suggestions are not a second conversation and are not stored as messages. The
request enters the existing assistant node with `mode=suggestions`. The node
loads the active conversation as read-only context, disables tools, and disables
both user and assistant memory writes. This keeps suggestions relevant to the
current conversation without introducing another history or scope.

## Runtime-neutral conversation access

Other plugins manage conversations through
`AssistantFoundation\Api\IAgentConversationService`. AssistantRuntime selects
the configured runtime and MissionBay resolves exactly one conversation memory
from the selected Memory Profile. The service never selects a different profile
or memory when the configuration is invalid.
