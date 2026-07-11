# MissionBay Agent Orchestration Services

## Purpose

This document records the boundary between configurable stages and internal orchestration services.

## Rule

```text
Stage = semantic orchestration step.
Service = reusable mechanism used by a stage or orchestrator checkpoint.
```

A service does not become a stage merely because it changes context. It becomes a stage only when an agent profile should be able to select, order, replace, or omit that semantic operation.

## Current services

MissionBay registers the following services through the BASE3 container:

```text
AgentActionResumeService
AgentActionReviewService
AgentBudgetGuardService
AgentContextAssessmentService
AgentContinuationDecisionService
AgentLoopProgressService
AgentResultVerificationService
AgentSemanticVerificationService
AgentToolResultCacheService
```

### Action lifecycle

`AgentActionReviewService` is called by `AgentActionPolicyStage`. It creates exact interaction requests and a serializable suspension before any reviewed action reaches execution.

`AgentActionResumeService` is called by `AgentToolOrchestrator` before the normal loop. It validates the suspension and structured responses, restores state, and returns approved, denied, or revised actions to policy evaluation.

### Execution boundary

`AgentToolExecutionStage` wraps:

```text
cache lookup
  -> tool budget projection
  -> tool invocation
  -> normalized result verification
  -> cache storage
```

These operations must remain atomic from the pipeline's point of view. Exposing them as separately reorderable stages allowed unsafe or nonsensical combinations.

### Context boundary

`AgentContextCompactionStage` always invokes `AgentContextAssessmentService`. Model-based compaction is conditional on the measured threshold. Assessment is telemetry and input to the semantic compaction decision, not a separate reasoning stage.

### Terminal decision

`AgentSemanticVerificationStage` invokes `AgentSemanticVerificationService` and then `AgentContinuationDecisionService`. The verifier produces evidence; the continuation service translates that evidence into the next phase. Both form one semantic terminal-verification step.

### Orchestrator checkpoints

Budget checks and loop-progress detection are enforced by `AgentToolOrchestrator`. They protect model calls, final generation, and loop continuation regardless of the selected stage profile.

## Extension guidance

A custom implementation should normally replace one of these services through project composition rather than add another top-level stage. A new stage is justified when a profile needs a genuinely different semantic sequence, for example explicit planning or memory writeback.
