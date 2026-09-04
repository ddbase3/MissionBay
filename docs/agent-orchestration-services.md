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

The shared default repository is `AssistantRuntime\Service\StateStoreAgentSuspensionRepository`. It uses `IStateStore` for TTL state and atomic claim creation. MissionBay depends only on `IAgentSuspensionRepository`, so hosts may replace the storage implementation without changing MissionBay.

`AgentActionReviewService` and `AgentActionResumeService` require that repository explicitly. The default action-policy component definition stores only instance configuration; runtime services are injected when the stage is materialized after plugin composition. MissionBay does not create a placeholder repository when the contract is missing.

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

### Evidence and action completion discipline

The model-decision strategies use one general tool contract. MissionBay core prompts do not encode retrieval, ILIAS, administration, or other domain-specific workflows. Tool descriptions, schemas, returned identifiers, limitations, and explicit next-step fields define the runtime contract for each capability.

The model is instructed to:

- establish tool-owned facts and identifiers through available authoritative tools instead of guessing;
- follow prerequisite and dependent calls when one result supplies values required by the next;
- distinguish a relevant lead from sufficient evidence for the requested scope;
- distinguish examples from definitions, individual results from complete sets, and intended actions from verified successful outcomes;
- continue materially useful tool work while a concrete resolvable gap remains;
- ask the user only when a required value cannot be established from the conversation or any available eligible tool;
- state remaining uncertainty instead of filling gaps from model knowledge.

The semantic verifier applies the same general boundary after a controlled model decision attempts to end the tool phase. A failed verification with a concrete resolvable gap reopens the loop. The verifier receives a compact view of the run-local capability catalog so it can decide whether a gap is tool-resolvable without inventing unavailable capabilities.

### Loop progress guard

`AgentLoopProgressService` protects long tool loops without reducing the configured loop limit. Legitimate multi-step workflows may therefore use many calls. The guard operates only on successful read-only calls marked as repeat-safe.

Two cases are distinguished:

1. An exact read call with equivalent arguments and unchanged output is an immediate deterministic stall.
2. A read call that only rephrases a query-like text argument and reproduces an already observed output is treated as no new evidence. One continuation warning is allowed before termination.

Different structured identifiers, filters, or other changed non-query arguments are not classified as the same work merely because their outputs happen to match. This keeps lookup and administration workflows usable while stopping search churn.

### Model-call failure diagnostics

A model exception during tool orchestration is stored under `model_raw_error` with its original exception detail for diagnostics. The public failure message now includes a sanitized concrete cause instead of only `Model call failed during tool orchestration.` Credentials in common authorization headers, token query parameters, and secret-like JSON fields are redacted before the cause is exposed.

If successful tool observations already exist when a later model-decision call fails, MissionBay may still produce a partial final response from those observations. That recovery response is explicitly restricted to the conversation and collected tool evidence and must state gaps rather than fill them from model knowledge.

## Extension guidance

A custom implementation should normally replace one of these services through project composition rather than add another top-level stage. A new stage is justified when a profile needs a genuinely different semantic sequence, for example explicit planning or memory writeback.

## Conversational continuity is not factual evidence

The orchestrator uses visible conversation history to preserve user intent, references, corrections and the active subject. This is intentionally different from using history as runtime evidence.

Previous assistant statements are not authoritative facts. In particular, an earlier assistant claim that a plugin is inactive, a job completed, or a setting has a value does not verify that state. When the user asks to check, verify, re-check or confirm a current runtime state and an authoritative read capability is available, the orchestrator must obtain fresh tool evidence.

Short, elliptical, misspelled or ambiguous follow-ups are resolved against the immediate active conversation topic before the agent invents a new domain or entity. This preserves conversational continuity without turning assistant text into a source of truth.

## Mutation truth states

State-changing work is described with distinct states: requested, awaiting approval, approved, attempted, succeeded and verified. Approval and intent do not mean execution. A successful mutation call supports only what its returned result actually establishes. A post-condition such as "the plugin is now inactive" must not be inferred when the tool result does not establish that condition.

If an already requested action remains incomplete after a failed, rejected or unsuccessful attempt, the original user intent remains active. The orchestrator continues the available workflow instead of asking whether the user still wants the same action, unless new approval, genuinely missing user input or a changed action requires another user decision.

