# Memory and Context Profiles

MissionBay keeps conversation history and system-context contributors separate in both runtime composition and administration.

## Memory Profiles

A memory profile is stored in `agent-memory-profile` and contains a list of concrete, already configured Component Preset IDs:

```json
{
  "id": "main-chat-history",
  "label": "Main Chat History",
  "enabled": true,
  "memories": ["iliassessionmemory-main"]
}
```

The administration display lists only presets whose runtime resource implements `IAgentConversationMemory` or a compatible legacy `IAgentMemory` implementation. Preset values such as `namespace`, `max`, and `priority` are reused unchanged.

Memory profile entries are attached only to the assistant node's `memory` dock. They are not context contributors and do not expose role switches.

## Context Profiles

A context profile is stored in `agent-context-profile` and contains concrete configured Component Preset IDs:

```json
{
  "id": "ilias-page-context",
  "label": "ILIAS Page Context",
  "enabled": true,
  "contexts": ["timememory", "iliasmemory", "userprefs"]
}
```

The administration display lists only presets whose runtime resource implements `IAgentContextContributor`. Their saved configuration is reused unchanged.

Context profile entries are attached directly to the assistant node's `contextcontributors` dock. They never receive conversation-history writes.

## Tool Profiles

Tool profiles remain independent and contain callable tool presets only. A resource such as user preferences may be selected by both a tool profile and a context profile. The flow builder creates one configured base resource and connects the appropriate tool wrapper and context dock to that same instance.

Conversation memory is never inferred from a tool profile.

## Agent Configuration

The normal agent form has three separate selections:

- Tool Profiles
- Memory Profile
- Context Profile

The values reference profile IDs. Profiles in turn reference concrete Component Preset IDs, not resource implementation names.

## Compatibility

Older `agent-memory-profile` records may contain mixed `entries` with roles. During the migration window:

- the memory resolver keeps only presets that actually implement conversation memory;
- the context resolver derives only presets that actually implement context contribution;
- saving a new memory or context profile writes the new simple preset-list format.

No new combined profiles are created. The compatibility reader is scheduled for removal after existing settings have been resaved in the split format.
