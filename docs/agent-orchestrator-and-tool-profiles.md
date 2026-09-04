# MissionBay Orchestrator and Tool Profiles

## Purpose

MissionBay separates operator-facing agent configuration from expert orchestration configuration.

A normal agent record selects:

```text
LLM
system prompt
orchestrator profile
tool profiles
separate memory and context profiles
```

The complex details stay in dedicated administration displays. This keeps the agent form suitable for platform operators who do not need to understand stage ordering, tool-selection limits, component presets, or MCP wiring.

## Orchestrator profiles

An orchestrator profile controls:

- the orchestration mode;
- maximum tool loops;
- optional semantic stages;
- the explicit capability-selection stage and its limits.

The core stage order is not user-configurable. MissionBay constructs the effective pipeline from a canonical sequence.

Required stages:

```text
model-decision
  -> action-policy
  -> tool-execution
  -> tool-observation
```

Optional stages can only be inserted at their canonical positions:

```text
capability-discovery
capability-selection OR ai-capability-selection
context-compaction
semantic-verification
```

The administration UI therefore uses checkboxes instead of drag-and-drop ordering. `capability-selection` and `ai-capability-selection` are mutually exclusive alternatives at the same canonical position. `AgentStagePipelineResolver` validates both the order and this exclusivity again at runtime, so malformed or manually edited flow data cannot combine or reorder them.

### Built-in profiles

MissionBay always exposes seven read-only profiles:

| Profile | Intended use |
|---|---|
| `simple` | One bounded tool loop for small, direct tool tasks. |
| `native-tool-loop` | Neuron-like provider-native tool loop: tool calls continue through MissionBay, while a normal terminal assistant response is streamed directly without a control signal or separate final-response model call. |
| `standard` | General multi-step tool orchestration with discovery, deterministic hybrid selection, compaction, and verification. |
| `large-catalog` | Uses the explicit `ai-capability-selection` stage for large, heterogeneous tool catalogs with deterministic fallback. |
| `large-catalog-native` | Uses AI selection of complete tool sources, then the same native streaming decision semantics as `native-tool-loop`. |
| `deliberate` | Evidence-oriented orchestration with concise typed planning and a smaller loop limit. |
| `governed` | Full orchestration for agents that may execute approved mutations. |

Built-in profiles can be duplicated into custom profiles. They cannot be overwritten or deleted.

### Model-decision semantics

MissionBay supports controlled and native model-decision semantics inside the same stable stage pipeline.

All model-decision strategies share the same domain-neutral quality contract. They must follow registered tool contracts, establish tool-owned facts instead of inventing them, continue prerequisite and verification calls while material resolvable gaps remain, avoid equivalent repeated work, and treat an action as complete only after a successful tool result proves it. A plausible partial result is not a terminal condition by itself. Tool-specific workflows remain in the tool descriptions and schemas rather than in MissionBay core prompts.

Controlled strategies terminate the tool phase explicitly and create the visible answer afterwards:

```text
simple-model-decision
ai-guarded-model-decision
```

`simple-model-decision` is retained only for persisted compatibility profiles that still use the textual `TOOL_PHASE_COMPLETE` sentinel. No built-in profile uses it, and new profiles should use `ai-guarded-model-decision` or `native-model-decision`. `ai-guarded-model-decision` uses the structured MissionBay control tool and remains the controlled default for the existing built-in profiles.

The native strategy uses the provider's normal tool-calling contract:

```text
model response contains tool calls
  -> action policy
  -> tool execution
  -> tool observation
  -> next model-decision iteration

model response contains no tool calls
  -> reuse the normal assistant content as the final visible answer
```

The `native-tool-loop` profile uses this native contract directly. If an event sink is available, terminal assistant deltas are published through the existing execution event channel while the same complete content is collected as the canonical final assistant response. The assistant layer recognizes that this content has already been delivered and does not publish or generate it again. Without an event sink, the same native response remains buffered and is returned normally; the model is still called only once for that decision.

Native model decision cannot be combined with `semantic-verification`. Semantic verification may reopen the loop after a terminal decision, but already visible native output cannot be recalled. MissionBay rejects this profile combination explicitly instead of silently changing strategies or delivery behavior.

Native mode does not bypass action policy, tool execution guards, approval, contract validation, budgets, observation, tracing, or mutation safeguards. Mutation handling starts only after the model emits a concrete mutation tool call. The mere presence of mutation operations in the active tool catalog does not switch strategies, add routing calls, or disable native streaming.

A mutation tool call is processed by the existing action-policy and execution path. Before approval, no mutation is executed and only the structured action-review interaction is authoritative. After a successful mutation, the next native terminal response may stream normally. If a concrete mutation attempt was rejected or failed, the terminal response remains buffered until the existing final-response guard has verified that it does not claim an unsupported success.

Native tool-call turns may stream brief plain-language progress text before the first tool call. That progress is transport output only and is not stored in the assistant tool-call history message, which remains empty so it cannot compete with structured approval or later final output. Once tool-call metadata has been observed, further content from that tool-call turn is suppressed. If content and tool-call metadata arrive in the same provider event, MissionBay processes the tool-call metadata first, so same-event content is not exposed as progress. Structured renderer blocks and final-answer formatting should only begin after required tools have returned.

If a provider stream is interrupted after visible output, MissionBay reports `native_stream_interrupted`, does not start a replacement final-response model call, and does not write the incomplete assistant response to conversation memory.

### Large-catalog native selection

The `large-catalog-native` profile combines the existing large-catalog stages with the same native model-decision implementation used by `native-tool-loop`:

```text
capability-discovery
  -> ai-capability-selection
  -> model-decision
  -> action-policy
  -> tool-execution
  -> context-compaction
  -> tool-observation
```

Unlike the existing controlled `large-catalog` profile, it disables semantic verification and reuses the terminal native model response directly. The existing `large-catalog` profile and all other built-in profiles keep their current behavior.

For this profile, AI capability selection chooses complete registered tool sources rather than isolated function definitions. A selected administration component exposes all of its registered functions to the following model decision. This preserves the relationship between lookup, detail, and mutation operations of mixed components such as WebDAV, plugin, and cron administration. It does not split those components into artificial read-only and mutation profiles.

The selector receives the canonical conversation messages already used by the orchestrator, including assistant tool calls and tool observations. It does not create a second routing history, a condensed continuation history, or a parallel memory representation. An empty source selection remains valid for ordinary conversation; selection only controls which tools are available and never forces a tool call.

Within one orchestration turn, `large-catalog-native` performs AI source selection once and keeps that bounded source-complete tool set for subsequent model decisions. The built-in profile exposes at most 16 functions to the native model. Small source pools bypass the AI router only when their complete functions still fit within that 16-function bound. Mutation handling still starts only when the native model emits a concrete mutation tool call. The existence of mutation functions in a selected source does not switch the decision strategy or introduce a controlled fallback.

After tool execution, the next native decision receives the existing authoritative execution ledger. Claims about state changes must match successful mutation calls from the current turn; approval, intent, prior conversation text, or a successful unrelated mutation is not proof that another requested action completed.

### Manual UI acceptance tests for native live mode

Use an existing configured LLM that supports streaming and normal provider tool calling. No provider-specific connector is required.

1. Open **Orchestrator Profiles** and inspect `native-tool-loop`. The effective pipeline must contain exactly `model-decision`, `action-policy`, `tool-execution`, and `tool-observation`. The strategy must be `native-model-decision`; decision repair and semantic verification must be disabled. No additional native profile is introduced.
2. Assign `native-tool-loop` to a test agent and send: `Write the numbers one to twenty, one number per line. Do not use a tool.` The answer must build incrementally, must not expose `TOOL_PHASE_COMPLETE`, and must come from the single model-decision call.
3. Send: `Reply exactly once with NATIVE-LIVE-ONCE. Do not use a tool.` The phrase must occur once in the chat. No duplicate response block may appear after the stream completes.
4. Ask a read operation, for example: `Use the appropriate tool to read the current WebDAV status and summarize the result.` The trace must show the tool call and observation, followed by a terminal native model-decision whose answer streams directly. There must be no separate final-response model generation.
5. Ask for an approval-protected mutation, for example: `Deactivate the ReadSpeaker plugin.` Before approval, the UI must show only the structured action-review card. No model-authored preface, confirmation question, or success claim may appear. The mutation must not run before approval.
6. Approve the structured request. The mutation must execute exactly once. The following native model-decision must produce the visible final answer directly; no separate final-response call may follow.
7. Repeat the mutation and reject it. No mutation may run. Any subsequent explanation must not claim success; MissionBay keeps that response buffered while the existing mutation final-response guard checks it.
8. With conversation memory enabled, send: `Reply exactly with MEMORY-NATIVE-LIVE.` Then ask: `What was your immediately previous answer?` The stored assistant message must equal the once-streamed visible response.
9. Repeat representative read and mutation tasks with `standard`. Its existing controlled two-phase behavior must remain unchanged.

### Manual UI acceptance tests for large-catalog native mode

1. Open **Orchestrator Profiles** and inspect `large-catalog-native`. Its effective stages must be `capability-discovery`, `ai-capability-selection`, `model-decision`, `action-policy`, `tool-execution`, `context-compaction`, and `tool-observation`. Its model-decision strategy must be native and semantic verification must be disabled.
2. Send `Hi`. AI capability selection may return an empty source list. No tool implementation may execute, and the native model response must stream directly.
3. With the real ILIAS administration tools, send: `What is the ReadSpeaker status and is there an Igor2 cron job?` Plugin Administration and Cron Administration must be selected as complete sources. The corresponding read tools must execute and the final answer must stream directly.
4. Send: `Deactivate ReadSpeaker and run Igor2Base.` The same two complete sources must remain available. Before approval, only the structured action-review interaction may be shown. After approval, each requested mutation must execute exactly once.
5. If only one requested mutation succeeds, the final response must identify the successful and unperformed actions separately. It must not infer success for one action from an unrelated successful mutation.
6. Ask `Check the ReadSpeaker status again.` The selector must use the canonical conversation messages and select Plugin Administration. The answer must be based on a new read-tool result, not only on previous assistant text.
7. Repeat the same tasks with `large-catalog`. Its existing controlled decision, semantic verification, and separate final-response behavior must remain unchanged.

### Release acceptance

The native orchestration release is complete when all of the following remain true in the same installed build:

- all existing controlled built-in profiles keep their previous stage and final-response semantics;
- `native-tool-loop` provides direct provider-native streaming without a separate final-response model call;
- `large-catalog-native` adds source-complete AI capability selection without introducing a second selector, routing history, or mutation fallback;
- capability selection controls availability only and never forces a tool call;
- mutation approval starts only after a concrete mutation tool call;
- visible output, returned assistant content, and conversation memory contain the same canonical final response;
- `TOOL_PHASE_COMPLETE` is never shown to users and is referenced only by the legacy compatibility strategy;
- no built-in profile uses `simple-model-decision`.

### Important boundary

Approval, durable resume, replay protection, contract validation, caching, budgets, mutation commit guards, and audit events are services/checkpoints. They are not optional stage checkboxes. A governed profile describes the intended use, while the actual mutation safety boundary remains enforced by the action policy and execution services.

## Tool profiles

Tool profiles group configured component presets. One profile can be enabled for:

- internal MissionBay agents;
- MCP exposure;
- both internal agents and MCP.

This reuses the existing MCP-oriented profile administration instead of creating a second grouping mechanism. The profile stores preset IDs; the runtime resolves those presets into the current AgentFlow.

Multiple selected tool profiles are merged. Repeated preset IDs are de-duplicated. Disabled, missing, or non-internal profiles fail closed when an internal agent is built.

## Dual tool and context components

Some resources intentionally expose a tool facet and a context-contributor facet. `UserPrefsAgentResource` is the main example:

- its tool facet lets the model list, set, and remove preferences;
- its context-contributor facet adds current preferences to a new turn without acting as chat history.

AssistantFoundation now distinguishes:

```text
IAgentConversationMemory
IAgentContextContributor
```

Session, volatile, and database histories implement `IAgentConversationMemory`. User preferences, focus, time, page context, and sub-agent descriptions implement `IAgentContextContributor`. Knowledge / Skills remains an explicit tool. Compatibility adapters may still implement `IAgentMemory`, but MissionBay resolves their explicit role and does not write user/assistant messages to context-only components.

Tool-profile resolution always requires the `tool` capability and automatically retains every additional capability declared by the selected preset. A preset with `tool` and `context` is therefore connected to both the tool wrapper and the `contextcontributors` dock. A preset with `tool` and `memory` is connected to both the tool wrapper and the conversation-memory dock.

```text
Tool Profile
  -> callable Component Presets
  -> automatic context/memory attachment when declared by those presets

Memory Profile
  -> conversation-memory presets not selected as tools

Context Profile
  -> context-contributor presets not selected as tools
```

There is no automatic/both role switch and no contributor read/write setting. Capabilities come from the concrete Component Preset contract. Component-specific storage, credentials, namespaces, priorities, and user scoping stay in that preset.

`UserPrefsAgentResource` is the normal dual-capability example. Selecting its preset in a Tool Profile creates one configured base resource, connects its tool wrapper, and also connects the same base resource to `contextcontributors`. A preference written through the tool facet is therefore available to the contributor on the next new turn without duplicating the preset in a Context Profile.

## Runtime resolution

```text
Agent settings
  -> resolve orchestrator profile
  -> write canonical stages and limits to assistant node
  -> resolve selected tool profiles
  -> resolve selected separate memory and context profiles
  -> merge repeated component presets
  -> preserve one shared base resource per preset
  -> build AgentFlow resources and docks
  -> capability discovery
  -> selected deterministic or AI capability-selection stage
  -> model decision
```

Legacy direct component and capability settings remain readable. They are shown only in the expert section and override profile defaults only when expert overrides are explicitly enabled.

## Administration

The following displays are intended for different audiences:

| Display | Audience |
|---|---|
| `AgentAdminDisplay` | Platform operators creating and assigning agents. |
| `AgentCompositionAdminDisplay` | Experts inspecting the effective read-only runtime composition of an agent. |
| `AgentOrchestratorProfileAdminDisplay` | Experts maintaining safe orchestration modes and limits. |
| `ToolProfileAdminDisplay` | Experts grouping configured component presets for internal agents and/or MCP. |
| `AgentMemoryProfileAdminDisplay` | Experts selecting configured conversation-memory presets. |
| `AgentContextProfileAdminDisplay` | Experts selecting configured context-contributor presets. |
| `AgentComponentPresetAdminDisplay` | Technical administrators configuring individual resource instances. |

`Base3IliasLab` registers Effective Composition, Orchestrator Profiles, Tool Profiles, Memory Profiles, Context Profiles, and Component Presets next to Agents. The composition display resolves actual tool names, memory facets, capability sources, module stage mounts, and final stages without adding these details back to the normal Agent form.

## Evidence discipline across turns

Agent profiles share one evidence rule regardless of the selected tool landscape: conversation history preserves intent, but previous assistant output is not factual verification. Current-state checks should use an authoritative read capability when one is available, even if the assistant stated the same state in an earlier turn.

The rule is deliberately tool-neutral. It applies equally to administration tools, retrieval, diagnostics, external services and future capability families.

Mutation profiles additionally keep the user's unresolved action intent across failed attempts. They distinguish approval from execution and execution from verified outcome. Tool success may be reported only to the extent supported by the corresponding tool result.

