# Agent Harness Legacy Cleanup

## Status

The bounded cleanup and repair program is complete.

```text
Open cleanup items: 0
Open repair items: 0
```

This file is the frozen completion ledger, not a backlog.

## Completed repairs

### M-02 — Conversation history and Knowledge tool

Completed in patches 16–17:

- removed phrase-specific regular expressions and phrase classifiers from assistant-turn routing;
- supplied recent visible conversation history to model input and capability selection;
- persisted the current user message before later orchestration can fail;
- moved session memory fully behind `ISession`;
- isolated configured memory stores by concrete preset and conversation scope;
- constructed separate runtime resources for separate presets of the same implementation;
- kept Knowledge / Skills as an explicit tool rather than conversation memory or context contribution;
- removed approval/commit-guard requirements from internal Knowledge writes;
- introduced the mandatory regex inventory/control script.

### M-01 — Memory/context UI and runtime separation

Completed in patch 18:

- Memory Profiles select concrete configured conversation-memory presets;
- Context Profiles select concrete configured context-contributor presets;
- Tool Profiles remain tool-only;
- combined roles and contributor read/write switches were removed;
- several presets of one implementation remain independently selectable;
- runtime docks are separated into `memory` and `contextcontributors`;
- one configured base resource is shared when a preset exposes tool and context facets.

## H-01 — Ownership, documentation, and freeze

Completed in patch 19:

- audited every interface in `AssistantFoundation/src/Api`;
- confirmed that each retained interface has a concrete cross-plugin extension, replacement, consumer, or adapter use case;
- kept MissionBay-only contracts under `MissionBay/Api`;
- documented every retained Foundation interface with requirements, implementation example, registration, and reference implementation;
- corrected outdated combined memory/context documentation;
- froze the remaining compatibility paths as intentional supported behavior rather than unresolved TODOs;
- set all migration and cleanup counters to zero.

The normative extension guide is:

```text
MissionBay/docs/ASSISTANTFOUNDATION_EXTENSION_POINTS.md
```

## Foundation ownership rule

An interface belongs in `AssistantFoundation` only when another plugin is expected to implement, replace, or consume it independently of MissionBay internals.

An interface belongs in `MissionBay/Api` when its lifecycle and meaning are owned only by MissionBay.

Adding a Foundation interface requires, in the same patch:

1. ownership rationale;
2. implementation requirements;
3. example implementation;
4. registration instructions;
5. compatibility note where applicable;
6. a passing `CHECK_ASSISTANTFOUNDATION_DOCS.sh` report.

## Intentional compatibility support

The following paths remain supported by design:

- legacy direct `IAgentMemory` implementations;
- old combined memory/context profile records read through the split resolver;
- direct expert `agent_components` configuration;
- historical unadvertised Knowledge tool aliases needed by saved flows or old prompts.

These are not open cleanup tasks. Removing one requires a separate bounded migration that updates stored settings and supplies an explicit delete list.

## Regex control

Every patch continues to ship `CHECK_AGENT_REGEX.sh`.

The script lists all PHP/JavaScript regex uses and fails when:

- regex appears in assistant-turn orchestration or AI node code;
- known phrase-specific conversation-routing markers reappear.

Technical regex for parsing, identifiers, protocols, and validation remains allowed outside that boundary and is still inventoried.

## Final counters

```text
Repair countdown: 0 / 2 open
Harness cleanup countdown: 0 / 1 open
Total open migration items: 0
```
