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

The administration display lists only concrete configured presets whose runtime resource implements `IAgentConversationMemory` or a supported legacy `IAgentMemory` implementation. Preset values such as `namespace`, `max`, and `priority` are reused unchanged; the profile never creates another default configuration.

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

Tool profiles remain the selection boundary for callable tool presets. When a selected preset declares additional `context` or `memory` capabilities, tool-profile resolution attaches those capabilities automatically. The flow builder creates one configured base resource and connects every required wrapper and dock to that same instance.

For example, selecting the `userprefs` preset in a Tool Profile attaches it as both `tool` and `context`. It does not need to be selected again in a Context Profile.

Memory and Context Profiles remain available for presets that should be attached without also being selected as callable tools.

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

No new combined profiles are created. The compatibility reader remains an intentional supported boundary for existing stored settings. Removing it requires a separate stored-settings migration and an explicit delete list; it is not an open harness-cleanup item.
