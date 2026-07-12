# MissionBay Conversation Memory and Context Contributors

## Purpose

MissionBay separates three concerns:

```text
Conversation memory
  visible user/assistant history loaded into later turns

Context contributor
  run-local system-context blocks such as time, preferences, or page data

Knowledge / Skills tool
  explicit agent-owned storage accessed through tool calls
```

Conversation history must work independently of tools. A failed or unused Knowledge tool does not affect whether visible messages remain available in later turns.

Assistant-turn routing does not use wording-specific regular expressions. Recent visible history is supplied regardless of the language or phrasing of the current request.

## Foundation contracts

### `IAgentConversationMemory`

`IAgentConversationMemory` extends the stable `IAgentMemory` API and marks a real conversation-history store.

Implementations provide:

```text
loadNodeHistory()
appendNodeHistory()
setFeedback()
resetNodeHistory()
getPriority()
```

Typical implementations are session, database, volatile, and no-memory stores.

### `IAgentContextContributor`

A Context Contributor returns typed `AgentInstructionBlock` values for a new turn:

```php
new AgentInstructionBlock(
    id: 'current-page',
    content: 'The current ILIAS object is ...',
    priority: 30,
    source: 'ilias-page-context'
);
```

It receives no user/assistant history writes.

The complete plugin extension contract and implementation example are documented in `ASSISTANTFOUNDATION_EXTENSION_POINTS.md`.

## Turn preparation

A new turn is assembled in this order:

```text
base system instruction
  -> context-contributor blocks
  -> visible conversation history
  -> current user message
```

Conversation stores are ordered by memory priority. Context blocks are ordered by contributor priority, block priority, block id, and original sequence.

The current user message is written to active writable conversation memory before later capability discovery, action policy, tool execution, or model processing can fail.

## Suspension and resume

Context Contributors are resolved once when a new turn starts. A suspended mutation resumes with the frozen reviewed message set rather than re-reading current preferences, page state, or other contributors.

```text
new turn
  -> resolve context contributors
  -> build messages
  -> possible suspension

resume
  -> restore frozen messages and reviewed action
```

## Backward compatibility

`IAgentMemory` remains supported. MissionBay resolves roles as follows:

```text
IAgentConversationMemory
  -> explicit conversation memory

IAgentContextContributor
  -> explicit context contributor

legacy IAgentMemory only
  -> conversation-compatible legacy memory with diagnostic warning
```

New context-only components must not implement `IAgentMemory` merely to inject system text. New conversation stores should implement `IAgentConversationMemory`.

## Configured presets and wrappers

A Memory Profile contains concrete configured Component Preset IDs. `ConfiguredAgentMemoryResource` wraps only conversation-memory presets and delegates read/write behavior to the configured resource.

A Context Profile contains concrete configured `IAgentContextContributor` presets. Contributors connect directly to the `contextcontributors` dock; there is no combined memory/context wrapper.

When one configured preset exposes both a tool and a context facet, the flow builder creates one base resource and attaches the corresponding tool wrapper and context dock to that same instance.

## Docks

```text
memory
  IAgentMemory / IAgentConversationMemory

contextcontributors
  IAgentContextContributor

tools
  MissionBay\Api\IAgentTool
```

Knowledge / Skills is attached through `tools`, not through either memory dock.

## Profiles

The normal agent configuration separates:

```text
Tool Profiles
Memory Profile
Context Profile
```

Profiles reference configured Component Presets, not implementation classes. Preset configuration such as namespace, retention limit, priority, credentials, or user scope is reused unchanged.

Older mixed profile records remain readable through the compatibility splitter. This is intentional supported compatibility and is not an open cleanup item. New and resaved configurations use the separated profile fields.

See `AGENT_MEMORY_CONTEXT_PROFILES.md`.

## Diagnostics

Effective Composition reports separately:

```text
conversation-memory
context-contributor
legacy-memory
tool
```

It also shows the contributing profile, concrete preset id, implementation, effective dock, priority, and redacted configuration.

## Stable boundary

`IAgentContext::getMemory()` and `setMemory()` remain for the shared compatibility contract. MissionBay-specific typed state access is provided by `MissionBay\Api\IAgentStateContext` and does not belong in AssistantFoundation.
