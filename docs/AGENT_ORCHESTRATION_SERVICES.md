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
IAgentSuspensionRepository
AgentActionResumeService
AgentActionReviewService
AgentBudgetGuardService
AgentCapabilityDiscoveryService
AgentCapabilityCatalogBuilder
IAgentCapabilitySelector
AgentCapabilitySelectionGuardService
AgentContextAssessmentService
AgentContinuationDecisionService
AgentLoopProgressService
AgentMutationCommitGuardService
AgentResultVerificationService
AgentSemanticVerificationService
AgentToolContractValidationService
AgentToolResultCacheService
JsonSchemaValidator
```


### Capability boundary

`AgentCapabilityDiscoveryService` resolves only the component IDs explicitly selected by the agent configuration. It activates configured capability providers and modules before profile filtering and pipeline construction, and returns the run-local tools, resource providers, prompt providers, instructions, and stage mounts. Infrastructure resolution remains a service because it must happen before the pipeline can be assembled. The semantic `capability-discovery` stage publishes and validates that result inside the visible trace.

`AgentCapabilityCatalogBuilder` normalizes the complete tool-function pool assigned to one agent run and rejects duplicate operational names. `IAgentCapabilitySelector` is the replaceable ranking slot used by the semantic `capability-selection` stage.

`AgentCapabilitySelectionGuardService` is not another stage. It enforces the exact selection before policy evaluation and immediately before execution, so custom stage profiles cannot accidentally turn ranking metadata into an unenforced hint.

### Action lifecycle

`AgentActionReviewService` is called by `AgentActionPolicyStage`. It creates exact interaction requests, stores the complete suspension through `IAgentSuspensionRepository`, and returns only an opaque resume handle.

`AgentActionResumeService` is called by `AgentToolOrchestrator` before the normal loop. It claims the handle, restores the server-owned suspension, validates the structured responses, consumes the claim, and returns approved, denied, or revised actions to policy evaluation.

The default repository is `StateStoreAgentSuspensionRepository`. It uses `IStateStore` for TTL state and atomic claim creation. `UnavailableAgentSuspensionRepository` fails closed when no runtime-state backend is configured.

### Execution boundary

`AgentToolExecutionStage` wraps:

```text
validated cache lookup
  -> tool budget projection
  -> final mutation commit guard
  -> tool invocation
  -> output-contract validation
  -> normalized structural verification
  -> cache storage
```

These operations must remain atomic from the pipeline's point of view. Exposing them as separately reorderable stages allowed unsafe or nonsensical combinations.

`AgentMutationCommitGuardService` runs directly before mutating tool invocation. It binds execution to the exact approved action, restores the server-owned commit snapshot, asks guarded tools to revalidate authorization and resource versions, and blocks stale or unauthorized writes. Mutation calls bypass the result cache. This checkpoint belongs inside the execution boundary, not in the public stage list.


### Tool contract boundary

`AgentToolContractValidationService` uses `JsonSchemaValidator` to validate model-generated arguments before action policy evaluation and successful outputs immediately after execution. Cache hits are validated before acceptance; stale entries that violate the current schema are deleted and treated as misses.

The mechanism records `AgentToolContractValidation` diagnostics and returns correctable failed tool observations without exposing rejected values. It is deliberately split across the action and execution boundaries rather than represented as another reorderable stage.

### Context boundary

`AgentContextCompactionStage` always invokes `AgentContextAssessmentService`. Model-based compaction is conditional on the measured threshold. Assessment is telemetry and input to the semantic compaction decision, not a separate reasoning stage.

### Terminal decision

`AgentSemanticVerificationStage` invokes `AgentSemanticVerificationService` and then `AgentContinuationDecisionService`. The verifier produces evidence; the continuation service translates that evidence into the next phase. Both form one semantic terminal-verification step.

### Orchestrator checkpoints

Budget checks and loop-progress detection are enforced by `AgentToolOrchestrator`. They protect model calls, final generation, and loop continuation regardless of the selected stage profile.

## Extension guidance

A custom implementation should normally replace one of these services through project composition rather than add another top-level stage. A new stage is justified when a profile needs a genuinely different semantic sequence, for example explicit planning or memory writeback.
