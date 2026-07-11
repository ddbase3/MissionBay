# MissionBay Agent Stage Pipeline

## Purpose

MissionBay keeps only semantic, replaceable orchestration steps as configured `IAgentStage` components. Guards, cache operations, validation, durable resume handling, and control-flow bookkeeping are internal services or orchestrator checkpoints.

## Active default pipeline

The ordered default is defined in:

```text
MissionBay\MissionBayPlugin::DEFAULT_AGENT_STAGE_IDS
```

The standard pipeline is:

```text
capability-discovery
  -> capability-selection
  -> model-decision
  -> action-policy
  -> tool-execution
  -> context-compaction
  -> tool-observation
  -> semantic-verification
```

An orchestrator profile provides the selected canonical stage subset. A node may still carry an explicit `stages` list for compatibility, but `AgentStagePipelineResolver` rejects missing required stages, unknown core stages, duplicates, and free reordering.

## Stage responsibilities

| Stage | Responsibility |
|---|---|
| `capability-discovery` | Publishes and validates the explicitly configured run-local tools, providers, modules, resources, prompts, instructions, and stage mounts before model-facing selection. |
| `capability-selection` | Selects a bounded context-relevant subset from the run-specific agent capability catalog before each model decision. |
| `model-decision` | Calls the model with only the selected tool definitions and chooses tool calls or a terminal tool-phase decision. |
| `action-policy` | Validates model-generated input contracts, then evaluates proposed actions. Its review service suspends exact mutations that require approval or input. |
| `tool-execution` | Owns the complete execution boundary: validated read-only cache lookup, tool budget projection, final mutation commit validation, invocation, output-contract and structural result validation, and read-only cache storage. |
| `context-compaction` | Always assesses context growth and conditionally compacts oversized tool output before it reaches the next model call. |
| `tool-observation` | Commits verified tool results to observations and model messages. |
| `semantic-verification` | Checks whether terminal evidence is sufficient and applies the continue/answer/clarify decision. |

`final-answer-regenerate` remains registered as an optional specialist stage and is not part of the default pipeline.

## Internal orchestration services

These concerns are intentionally not visible stages:

| Service/checkpoint | Placement |
|---|---|
| action resume and durable handle claim | Orchestrator entry before a new model decision |
| action review | Inside `action-policy` |
| model/final/tool budget checks | Orchestrator checkpoints and execution boundary |
| tool cache lookup/store | Inside `tool-execution` |
| mutation authorization/version revalidation | Immediately before mutating `callTool()` inside `tool-execution` |
| configured capability resolution and module activation | `AgentCapabilityDiscoveryService` before pipeline construction; published by `capability-discovery` |
| exact-selection enforcement | Before action policy and again at the execution boundary |
| input/output tool-contract validation | Before policy and immediately after execution/cache lookup |
| structural result verification | Inside `tool-execution` |
| context assessment | Inside `context-compaction` |
| loop-progress detection | Orchestrator loop control |
| continuation decision | Inside `semantic-verification` |

This keeps safety boundaries enforced without presenting implementation plumbing as independently configurable reasoning steps.

## Approval flow

```text
capability-discovery
  -> capability-selection
  -> model-decision
  -> action-policy
       -> allow / deny / require interaction
       -> review service creates exact request and suspension
  -> return awaiting_approval or awaiting_input

resume request
  -> orchestrator resume service validates the suspended action
  -> approved/revised action re-enters action-policy
  -> tool-execution
```

Tools do not ask users directly.

## Tool-loop limit

The assistant node exposes the input:

```text
maxtoolloops
```

Its default is defined in `MissionBay/src/Node/Ai/AbstractAiAssistantNode.php` and is currently `10`. The same defensive default exists in `AgentAssistantTurnOptions` and `AgentToolOrchestrator`. The orchestrator stops when `iteration < maxLoops` is no longer true and returns the partial failure code `max_tool_loops`.

## Profile-controlled optional stages

`AgentOrchestratorProfile` never stores an arbitrary ordered stage array. It stores mode, limits, and optional-stage booleans. `getStageIds()` reconstructs the canonical sequence. The administration UI mirrors this with checkboxes and a read-only pipeline preview rather than drag-and-drop ordering.

See [AGENT_ORCHESTRATOR_AND_TOOL_PROFILES.md](AGENT_ORCHESTRATOR_AND_TOOL_PROFILES.md).

## Criteria for future stages

Add a stage only when the step is:

- semantically meaningful to an orchestrator profile;
- independently replaceable or optional;
- able to consume and produce stable agent state;
- useful in the visible execution trace.

Use a service, adapter, decorator, or checkpoint for infrastructure mechanics that must remain coupled to one execution boundary.


## Configured capability composition

The high-level agent configuration selects configured tool, provider, module, resource-provider, and prompt-provider component IDs. `AgentCapabilityDiscoveryService` resolves and activates them before the core pipeline is constructed. The visible `capability-discovery` stage validates and emits the resulting run-local composition, while `capability-selection` remains responsible only for the context-dependent model-facing reduction.

Module-provided stages are mounted run-locally through semantic slots. They are never registered globally. See [AGENT_CAPABILITY_PROVIDERS_AND_MODULES.md](AGENT_CAPABILITY_PROVIDERS_AND_MODULES.md).

## Compatibility

The previous small guard/cache/verification stage classes remain in the source tree for compatibility with explicit custom pipelines. They are no longer registered in the MissionBay default composition.

## Durable suspension boundary

The action-policy stage may stop the pipeline with an opaque `resume_handle`. The complete suspension stays server-side and is restored by the orchestrator entry checkpoint. See [AGENT_DURABLE_SUSPENSIONS.md](AGENT_DURABLE_SUSPENSIONS.md).

## Mutation commit boundary

Approved mutations carry a server-owned commit snapshot into `tool-execution`. `AgentMutationCommitGuardService` rechecks the exact action fingerprint and delegates authorization/version validation to `IAgentMutationGuardedTool`. Mutation calls never use the tool-result cache. See [AGENT_MUTATION_COMMIT_GUARD.md](AGENT_MUTATION_COMMIT_GUARD.md).

## Tool contract boundary

Model-generated arguments are validated before policy evaluation. Successful runtime or cached output is validated before it becomes an observation or cache entry. Contract mechanics remain services inside `action-policy` and `tool-execution`; no additional default stage is introduced. See [AGENT_TOOL_CONTRACT_VALIDATION.md](AGENT_TOOL_CONTRACT_VALIDATION.md).

## Capability selection boundary

The run-local catalog contains all functions assigned to the agent after profile filtering. `capability-selection` exposes only a bounded subset to each model call, and later stages reject calls outside that exact subset. See [AGENT_CAPABILITY_CATALOG_AND_SELECTION.md](AGENT_CAPABILITY_CATALOG_AND_SELECTION.md).
