# MissionBay Agent Stage Pipeline

## Purpose

MissionBay keeps only semantic, replaceable orchestration steps as configured `IAgentStage` components. Guards, cache operations, validation, resume handling, and control-flow bookkeeping are internal services or orchestrator checkpoints.

## Active default pipeline

The ordered default is defined in:

```text
MissionBay\MissionBayPlugin::DEFAULT_AGENT_STAGE_IDS
```

The standard pipeline is:

```text
model-decision
  -> action-policy
  -> tool-execution
  -> context-compaction
  -> tool-observation
  -> semantic-verification
```

A node may still provide an explicit `stages` list to select another configured orchestration profile.

## Stage responsibilities

| Stage | Responsibility |
|---|---|
| `model-decision` | Calls the model and chooses tool calls or a terminal tool-phase decision. |
| `action-policy` | Evaluates proposed actions. Its review service suspends exact mutations that require approval or input. |
| `tool-execution` | Owns the complete execution boundary: cache lookup, tool budget projection, invocation, result validation, and cache storage. |
| `context-compaction` | Always assesses context growth and conditionally compacts oversized tool output before it reaches the next model call. |
| `tool-observation` | Commits verified tool results to observations and model messages. |
| `semantic-verification` | Checks whether terminal evidence is sufficient and applies the continue/answer/clarify decision. |

`final-answer-regenerate` remains registered as an optional specialist stage and is not part of the default pipeline.

## Internal orchestration services

These concerns are intentionally not visible stages:

| Service/checkpoint | Placement |
|---|---|
| action resume | Orchestrator entry before a new model decision |
| action review | Inside `action-policy` |
| model/final/tool budget checks | Orchestrator checkpoints and execution boundary |
| tool cache lookup/store | Inside `tool-execution` |
| structural result verification | Inside `tool-execution` |
| context assessment | Inside `context-compaction` |
| loop-progress detection | Orchestrator loop control |
| continuation decision | Inside `semantic-verification` |

This keeps safety boundaries enforced without presenting implementation plumbing as independently configurable reasoning steps.

## Approval flow

```text
model-decision
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

## Criteria for future stages

Add a stage only when the step is:

- semantically meaningful to an orchestrator profile;
- independently replaceable or optional;
- able to consume and produce stable agent state;
- useful in the visible execution trace.

Use a service, adapter, decorator, or checkpoint for infrastructure mechanics that must remain coupled to one execution boundary.

## Compatibility

The previous small guard/cache/verification stage classes remain in the source tree for compatibility with explicit custom pipelines. They are no longer registered in the MissionBay default composition.
