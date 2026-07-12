# Agent Harness Legacy Cleanup

## Purpose

This document is the finite cleanup ledger for the current agent-harness migration. It is not an open-ended feature backlog.

New interfaces, DTOs, models, or production classes are introduced only for a real replacement boundary or new behavior. Internal MissionBay contracts belong in `MissionBay/Api`; only plugin-to-plugin extension slots belong in `AssistantFoundation`.

## Repair freeze

The repair freeze is complete. The conversation-memory defect was closed before the profile split was finalized.

### M-02 — Conversation history and Knowledge tool — DONE in patches 16–17

Done means:

- phrase-specific regular expressions and phrase classifiers have been removed from assistant-turn routing;
- every normal task receives recent visible conversation history for model input and capability selection, independent of the wording of the current prompt;
- the current user message is appended to conversation memory before capability discovery, policy evaluation, or tool execution can fail;
- `SessionMemory` and `SessionMemoryAgentResource` use `ISession`, not `$_SESSION`;
- configured session-memory presets are isolated by concrete resource/preset id and conversation scope;
- the resource factory constructs a fresh runtime resource for every flow resource, so several presets of the same implementation cannot overwrite each other;
- `KnowledgeAgentResource` is an explicit tool only and is not a conversation memory or context contributor;
- Knowledge writes are internal tool operations without user approval or mutation commit guard annotations;
- the patch contains `CHECK_AGENT_REGEX.sh` and a complete generated regex inventory.

### M-01 — Memory/context UI separation and migration — DONE in patch 18

Done means:

- a memory profile contains only concrete configured conversation-memory Component Presets;
- a separate context profile contains only concrete configured `IAgentContextContributor` presets;
- tool profiles contain tools, including Knowledge / Skills;
- combined memory/context roles (`auto`, `both`, read/write on contributors) are removed;
- three presets of one implementation appear as three distinct selectable options;
- existing combined profiles are read through a bounded compatibility splitter until they are resaved;
- the combined role switch is removed from runtime and administration;
- every patch includes a complete file-delete list, even when the list is empty.

## Regex control

Every patch from patch 16 onward must ship `CHECK_AGENT_REGEX.sh`.

The script:

1. prints every detected PHP or JavaScript regex use in MissionBay, Base3IliasLab, and Chatbot source/template trees;
2. fails if regex use exists in `MissionBay/src/Service/Assistant` or `MissionBay/src/Node/Ai`;
3. fails if known phrase-specific conversation-routing markers reappear.

Regex remains valid for technical parsing, validation, identifiers, protocols, and text processing outside assistant-turn intent routing. All occurrences are nevertheless listed for review.

## Foundation ownership rule

Interfaces belong in `AssistantFoundation` only when another plugin is expected to provide, replace, or extend the contract. MissionBay-internal runtime contracts belong in `MissionBay/Api`.

`IAgentStateContext` was moved to `MissionBay\Api`. Installations that applied patch 14 must have removed:

```text
AssistantFoundation/src/Api/IAgentStateContext.php
```

## Current repair countdown

```text
0 / 2 open
```

| Nr. | Task | Status |
|---|---|---|
| M-02 | Conversation history and Knowledge tool | DONE — patches 16–17 |
| M-01 | UI/runtime separation, migration, delete list | DONE — patch 18 |

The countdown is not extended.
