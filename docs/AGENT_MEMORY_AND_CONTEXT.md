# MissionBay Conversation Memory and Context Contributors

## Purpose

MissionBay distinguishes two concerns that were previously represented by the same `IAgentMemory` contract:

```text
conversation memory
  visible user/assistant history that is loaded and written

context contribution
  run-local system instructions or contextual facts that are read for a new turn
```

The distinction is important for resources such as user preferences, focus state, current time, page context, and sub-agent descriptions. Those resources may contribute useful model context, but they are not chat-history stores and must not receive every visible user or assistant message.


## Terminology boundary

MissionBay uses three separate concepts:

```text
Conversation memory / history
  recent visible user and assistant messages passed into later turns

Context contributor
  run-local system-prompt additions such as current time, preferences, or page context

Knowledge / Skills tool
  explicit agent-owned storage accessed only through tool calls
```

The Knowledge / Skills tool is not conversation memory and is not a context contributor. A failed or unused Knowledge tool must never affect whether the visible chat history is retained.

Assistant-turn routing must not classify user intent through phrase-specific regular expressions. Recent visible history is supplied for every task; the model and capability selection receive that history regardless of the wording or language of the current request.

## Foundation contracts

`AssistantFoundation` exposes two explicit contracts.

### `IAgentConversationMemory`

```php
interface IAgentConversationMemory extends IAgentMemory {
}
```

This is a backward-compatible marker on the existing chat-history API. Implementations load visible dialog messages and receive new visible messages through:

```text
loadNodeHistory()
appendNodeHistory()
setFeedback()
resetNodeHistory()
```

Typical implementations are session memory, volatile memory, database memory, and no-memory adapters.

### `IAgentContextContributor`

```php
interface IAgentContextContributor extends IComponent {

    public function contribute(IAgentContext $context): iterable;

    public function getPriority(): int;
}
```

A contributor returns typed `AgentInstructionBlock` values. It does not expose chat-history write operations.

```php
new AgentInstructionBlock(
    id: 'user-preferences',
    content: 'Prefer compact answers.',
    priority: 20,
    source: 'user-prefs-primary',
    metadata: [
        'implementation' => 'userprefsagentresource'
    ]
);
```

Each block becomes a system-role message when a new turn is prepared. The typed object keeps identity, source, ordering, and diagnostics separate from the final provider message shape.

## Runtime preparation

A new turn is prepared in this order:

```text
base system instruction
  -> context-contributor blocks
  -> visible conversation history
  -> current user message
```

Conversation memories are ordered by their memory priority. Context contributors are ordered deterministically by:

```text
contributor priority
  -> instruction-block priority
  -> instruction-block id
  -> original sequence
```

The resolved blocks are also stored in the run context under:

```text
agent_context_contributions
```

This diagnostic value contains block metadata, not hidden reasoning.

## Resume boundary

Context contributors are resolved only when a new turn is prepared.

When an approved mutation resumes, MissionBay restores the frozen message set from the suspension. It does not re-read preferences, focus, page context, or other contributors during the same suspended turn.

```text
new user turn
  -> resolve current contributors

suspension + resume of that turn
  -> reuse frozen messages and reviewed action data
```

This keeps the approved action bound to the context that was shown and reviewed before suspension.

## Backward compatibility

`IAgentMemory` remains unchanged.

MissionBay resolves memory roles as follows:

```text
implements IAgentConversationMemory
  -> explicit conversation memory

implements IAgentContextContributor
  -> explicit context contributor

implements both
  -> both explicit roles

implements only legacy IAgentMemory
  -> conversation-compatible legacy memory
```

Legacy-only memory remains readable and writable so existing plugins continue to work. Effective-composition diagnostics mark it as `legacy-memory` and recommend adopting one of the explicit contracts.

MissionBay's built-in context-only resources implement only `IAgentContextContributor`. Their former no-op history methods have been removed. Legacy-only `IAgentMemory` remains supported for external plugins and is reported as such in diagnostics.

## Configured memory wrapper

`ConfiguredAgentMemoryResource` still provides the adapter used by component presets. It implements both new interfaces at the class level because the wrapped resource is selected at runtime.

The wrapper therefore also implements `IAgentMemoryRoleProvider`. Its effective role is delegated to the wrapped component:

```text
wrapped conversation memory
  -> load/write conversation history

wrapped context contributor
  -> contribute instruction blocks, never receive chat writes

wrapped legacy memory
  -> preserve legacy conversation behavior and report a diagnostic warning
```

## Dual-role components

A component may intentionally be both a tool and a context contributor.

`UserPrefsAgentResource` is the main example:

```text
tool facet
  list, set, and remove preferences

context-contributor facet
  inject current allowed preferences into the next new turn
```

This remains one configured component and one storage implementation. Tool and context wrappers point to the same preset resource in the effective flow. `AgentComponentFlowBuilder` creates one base resource per preset for a build, and context assembly de-duplicates identical resource objects within a turn.

The same pattern applies to focus and sub-agent resources. Knowledge / Skills remains an explicit tool and is not attached as a context contributor.

## Flow docks

Assistant nodes expose two relevant docks:

```text
memory
  IAgentMemory
  explicit conversation memories and backward-compatible adapters

contextcontributors
  IAgentContextContributor
  pure contributors that do not need legacy memory methods
```

Component presets continue to use the operator-facing `memory` capability for both conversation memory and context contribution. The configured wrapper accepts either kind through the common `IAgentResource` dock and exposes the effective role correctly. Pure contributors can also connect directly to `contextcontributors`.

## Effective composition diagnostics

The effective-composition display now reports:

```text
conversation-memory
context-contributor
legacy-memory
tool
```

For each configured preset it reports the independently resolved tool, conversation-memory and context-contributor roles. It also shows separate KPI counts for conversation memories and context contributors.

## Deliberate compatibility boundary

This change does not remove or redesign:

```text
IAgentMemory
IAgentContext::getMemory()
IAgentContext::setMemory()
```

Those APIs remain available while stable `AgentState` and `AgentResult` models are introduced later. Removing them in the same patch would mix a contract clarification with a much larger state migration.

## Memory/context profile layer

The operator-facing profile is implemented by `AgentMemoryProfileResolver` and `AgentMemoryProfileAdminDisplay`.

Conceptually:

```text
separate memory and context profiles
  -> component preset
  -> automatic or explicit role
  -> enabled
  -> deterministic priority
  -> conversation read/write flags where applicable
```

When a profile is selected, tool profiles resolve tool facets only and the separate memory and context profiles becomes authoritative for memory facets. Repeated preset IDs are merged before the flow is built, so a component such as user preferences remains one configured base resource even when it is both callable and context-producing.

Agents without a selected profile retain the previous compatible tool-profile and expert-component behavior.

See `AGENT_MEMORY_CONTEXT_PROFILES.md` for the full profile and administration contract.
