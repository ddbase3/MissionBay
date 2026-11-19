# Unified Agent Human Approval: Phase 1 Baseline

## Scope and source

This is the phase 1 contract and diagnosis baseline against the provided BASE3 snapshot from 2026-11-18. It is not a completed implementation of interactive approvals for all consumers, nested agents, or scheduled execution. Runtime behavior is intentionally unchanged.

Source order for this assessment: BASE3 project documentation, the uploaded framework and AssistantFoundation contracts, and the supplied plugin sources. The effective deployed bindings and production behavior have not been tested.

## Single ownership

| Responsibility | Existing owner | Boundary |
| --- | --- | --- |
| Select configured agents and execution runtime | `IAgentConfigurationService`, `IAgentExecutionService`, `AssistantRuntime` | All consumers call the same Foundation execution interface |
| Decide whether a tool call needs approval | MissionBay `AgentActionPolicyStage` and configured `IAgentActionPolicy` | Evaluate after contract validation, before execution |
| Build reviewed interaction requests | MissionBay `AgentActionReviewService` | Preserve server-owned action fingerprints and snapshots |
| Persist and consume suspended actions | `IAgentSuspensionRepository`, configured by AssistantRuntime | Opaque handles, one-time claims, TTL and existing state backend |
| Resolve decisions and resume | MissionBay `AgentActionResumeService` | Exact request set; no implicit approval |
| Recheck mutations at execution time | MissionBay `AgentMutationCommitGuardService` and native `IAuthorizationService` | Approval never replaces authority or resource version checks |
| Present pending interactions | Each existing caller or UI | Must not invent an alternative agent executor or authorization rule |
| Run scheduled agents | MissionBay `ScheduledAgentRunnerJob` | Job timing is not human approval and must not imply execution success |

No second approval engine, storage copy, router, delegated executor or user authorization system is permitted.

## Normative result contract

`AgentExecutionStatus::AWAITING_APPROVAL` and `AgentExecutionStatus::AWAITING_INPUT` are normal suspended outcomes. `AgentResult::isSuspended()` is true and `hasFailure()` is false when no actual failure is recorded. No final assistant message is required for either suspended result.

A caller must inspect the typed result status *before* treating a missing assistant message as an error. An output object may contain a public `resume_handle` and `interaction_requests` without a message. Neither the absence of a message nor a finished HTTP request implies that the action executed.

The unmodified Chatbot integration already demonstrates the intended presentation contract through `AbstractChatbotService` and `ChatbotTurnResponder`. The existing status and DTO tests in AssistantFoundation remain authoritative. The phase 1 check `AgentExecutionStatusDiagnosticCheck` is an additional read-only operational assertion for these DTO semantics. It does **not** exercise tools, validate real suspension persistence or test callers.

## Source-verified consumer inventory

| Consumer | Current source observation | Target behavior | Follow-up phase |
| --- | --- | --- | --- |
| Existing Chatbot | `Chatbot/Service/AbstractChatbotService` detects `AgentResult::isSuspended()` and emits an interaction result | Preserve as regression baseline | Every phase |
| Agent Admin | `MissionBay/Display/AgentAdminDisplay::buildRunResponse()` reports a missing assistant message as flow error even for a suspended execution | Display the interaction and resume the same run | Phase 2 |
| Configured sub-agent tool | `MissionBay/Resource/AgentTool/Agent/RunConfiguredAgentTool` expects a terminal child answer | Parent run suspends without interpreting an interaction as tool output | Phase 3 |
| Available sub-agent tool | `MissionBay/Resource/AgentTool/Agent/RunAvailableAgentTool` likewise expects a terminal child answer | Parent run preserves the child continuation without re-executing prior actions | Phase 3 |
| Scheduler | `MissionBay/Job/ScheduledAgentRunnerJob::go()` calls `markRun()` after `execute()` without checking the result status | Only successful completion satisfies the job policy; suspended runs are not restarted | Phase 4 |
| Jarvis API | `Base3XrmWebsite/Agent/JarvisAgentApi` returns HTTP 502 for a missing final assistant message even if an agent is suspended | Return the shared interaction envelope; accept secure resume input | Phase 2 |
| Jarvis terminal and browser clients | Existing CLI and Chrome/Edge/Firefox clients use the Jarvis API | Present explicit decisions; no separate approval policy | Phase 5 |

These are inspection findings, not claims of comprehensive runtime testing. The initial evidence from AgentAdmin shows `awaiting_approval`, a valid interaction request, an opaque resume handle and zero executed tool calls. That confirms an intended pause, not a successful tool execution.

## Additional requirement: approval duration and scope

The current `AgentActionResumeService` and `AgentActionPolicyStage` use `PREAPPROVED_ACTIONS` for an **exact single call** with the reviewed `AgentActionFingerprint`. There is no source-verified reusable approval grant for later calls or for jobs. Do not treat that one-time map as a durable grant.

The agreed UX must distinguish:

1. **This action**: existing exact action fingerprint, once only.
2. **This tool or MCP provider in this chat**: a limited reusable grant bound to the trusted user, authority and instance, conversation channel and ID, and the selected stable tool or MCP identity. Tool-level and provider-wide selections must be separate, visible choices; authorizing one function must not silently authorize every function on a remote server.
3. **This tool for this configured agent, including scheduled runs**: an explicitly granted, revocable approval associated with a stable agent identity and its authorized scheduled execution context. It is not inherited from a chat-wide grant simply because the agent ID happens to match.

The third choice allows a user to test an agent manually and explicitly authorize the selected tool for subsequent scheduled execution. The grant must be recorded and evaluated by the **existing MissionBay action-policy owner**; storage choice must respect existing BASE3 configuration, settings and state ownership. No new authorizer is justified. The approval record must be tied to a verified actor and reviewed under the agent's actual execution principal. Granting approval does not lend an administrator's own rights to a worker.

Before a grant is applied, verify the exact effective tool identity and provider, relevant reviewed risk classification, applicable operation and argument restrictions, current authorization and commit guard, expiry or revocation, and current tool policy. Changes to the tool implementation, remote endpoint, applicable permissions or safety classification must not silently broaden the grant. A high-risk or provider-wide permanent approval needs an explicit, informative scope choice and may be disallowed by the authoritative policy. For delegated agents, grant evaluation remains at the actual tool owner inside the child invocation, not in parent tool output.

Neither `IAgentSuspensionRepository` nor an agent conversation is a general-purpose approval grant store. Persist no reusable grant in a copied suspension snapshot or mirrored conversation context. The concrete persistence contract and revocation semantics belong to the later approved MissionBay change after inspecting the full native identity and authorization boundaries.

## Diagnostic integration in phase 1

`AgentExecutionDiagnosticScope` adds one new discoverable diagnostics area using the existing `IDiagnosticScope`, `IDiagnosticCheck`, `IDiagnosticRunner`, `IClassMap` and `DiagnosticScopeDisplay`. The runtime code adds no bootstrap registration, HTTP route or diagnostic runner.

`AgentExecutionStatusDiagnosticCheck` verifies only that both awaiting statuses can be represented as nonfailed suspended `AgentResult` values, even with an empty `AgentExecutionResult` output, and that ordinary statuses are not classified as suspended. It does not read or write any agent state, connect to an MCP endpoint, start an agent, inspect user data or create approval grants.

A passing check is **not** evidence that AgentAdmin, the Jarvis API, sub-agent chaining or jobs are HITL-ready. The defects listed in the inventory remain open until their owner is fixed and dedicated behavior tests pass. A running diagnostic can be unavailable when a required module is missing or uninstantiable; discovery coverage must be reported separately rather than claiming complete installation coverage.

## Acceptance gates for subsequent phases

- Completed runs still return their normal assistant message; suspended runs keep their status, requests and opaque handle without a fabricated error.
- Exactly the reviewed action executes once on approval. Denial, unclear input, expired handles, replay, concurrent resumes and changed action arguments never execute the reviewed tool.
- Chat-wide grants cannot leak into another conversation or user, across authorities/tenants or into scheduled execution.
- Explicit agent-scoped grants apply to scheduled runs only under their recorded policy scope; revoked or changed permissions block execution. No uncontrolled "allow all" or implicit job actor privileges.
- Nested A -> B -> C suspension preserves all already completed parent and child tool actions and returns to the original caller after an explicit decision.
- Scheduled jobs do not count or mark a suspended run as successful; retried workers do not duplicate the run.
- Jarvis API continues using the existing personal credential service and authorization. All four clients preserve normal responses and implement the same approved/denied/resume contract.
- Changes are validated through PHPUnit/integration, contract checks, negative and concurrency tests, translations, privacy notes, and verified rollback. The baseline must remain available throughout staged development.

## Verification evidence for this patch

Only the status diagnostic, its registration through classmap discovery, associated translations, unit test and documentation are introduced. No production execution semantics or approval decisions change. Syntax, translation catalog completeness and isolated read-only result evaluation can be verified locally; end-to-end suspended executions, concurrency, production jobs and actual interactive resumes remain outside the scope of phase 1.

## Phase 2 implementation: AgentAdmin and Jarvis API

Phase 2 extends the *existing* `AgentAdminDisplay` run response and the existing project-owned `JarvisAgentApi`. The already accepted phase 1 diagnostic and its status contracts remain unchanged. This section documents implemented source behavior; passing tests on the supplied snapshot do not prove a live installation.

### AgentAdmin manual execution

`AgentAdminDisplay` first verifies the authenticated operator and obtains the current feature authorization at the existing `getOutput()` boundary. Pending suspensions are retrieved through the already registered `IAgentSuspensionRepository::findPending()` using a deterministic scope for the server-trusted authority, operator ID and configured agent. A new manual prompt cannot silently re-execute an agent when a suspension already exists for that scope.

Both `awaiting_approval` and `awaiting_input` return `ok=true`, `action=interaction_required`, status, an opaque `resume_handle`, creation/expiry information and public `interaction_requests`. The runner dialog shows a separate explicit choice for each request and sends a single `AgentResume` request through the original `IAgentExecutionService`. The dialog never selects approval by default. Denial does not grant execution. Invalid, expired, missing or foreign pending handles cannot reach execution. Completed runs retain the original result format.

The new user-facing review projection includes the reviewed action, arguments, fingerprint, risk, summary and explanatory text, but never the repository's internal metadata or the full suspended orchestration snapshot. These remain on the server for verification and deterministic resume. Current single-use approval semantics remain unchanged.

### Jarvis personal JSON API

`JarvisAgentApi` retains the existing `/jarvisagentapi.json` operation, personal KeyHarbor authentication, native user verification, agent configuration service, `IAgentExecutionService` binding, original `agent-api:jarvis` memory channel and unchanged terminal message response. A paused result returns HTTP 200 and `type=interaction_required`, not HTTP 502. The authenticated client continues the same conversation by sending its opaque resume handle with explicit response decisions or `response_text`. The endpoint checks the pending suspension against a scope including the verified native XRM user ID before forwarding it to the existing runtime. A second message for that conversation while the suspension is active returns the same pending review rather than creating a new run.

The subject-qualified scope is implemented by adding an optional third argument to `AgentSuspensionScope::forConversation()`. Existing calls using two arguments retain their exact previous scope hash. `AgentActionReviewService` supplies this subject only when the caller has explicitly provided trusted `agent_suspension_subject` context. No new store, router, authorization decision service or runtime was introduced.

The bundled CLI and three browser clients still require their own interaction presentation changes in phase 5. Until then they may not display the valid `interaction_required` payload correctly. Phase 2 does not add reusable chat or durable agent approval: both stay with the Action Policy owner in the later agreed phase.

### Verification

Dependency-free smoke tests for the new endpoint and admin boundaries cover normal completion, two suspension outcomes, explicit approve/deny forwarding, pending retrieval without duplicate execution, unavailable handles, foreign users, invalid decisions and minimization of review metadata. Foundation DTO tests cover compatible subject-qualified scope hashes and redacted public interaction requests. The existing MissionBay action review PHPUnit test additionally asserts the subject scope on the persisted suspension. Existing browser/CLI live integration and real provider and tool execution were not performed in the supplied snapshot test environment.

## Phase 3 implementation: nested delegated agents

The phase 1 status diagnostic and the phase 2 AgentAdmin and Jarvis interfaces remain unchanged. `RunConfiguredAgentTool` and `RunAvailableAgentTool` now propagate typed child suspensions to MissionBay's existing tool-execution boundary. The same `AgentActionReviewService` stores the incomplete parent batch with a private child resume handle in `IAgentSuspensionRepository`. `AgentActionResumeService` consumes the outer handle and forwards the decision through the original tool and `IAgentExecutionService`; the child continues with its original runtime and policy. No alternate executor, repository or authorization service is created.

The dependency-free MissionBay integration scenario exercises a real A to B to C orchestration with both types of sub-agent tool, explicit approval and denial, two successive leaf approvals, repeated and concurrent handle claims, invalid interaction IDs, expired children and a previously finished parent tool that must not run again. These are local tests of the provided snapshot and do not establish deployment concurrency, real MCP network calls or production rollout. Chat-wide/agent-wide grants, scheduled-job behavior and Jarvis client presentation are still future phases.

## Phase 4 status (later implementation)

The initial inventory and baseline conclusions above are historical. Phase 4 implements status-aware scheduled execution, manual Admin review of suspended jobs, and explicitly opted-in durable tool approvals for the same configured agent. The separate tool-definition fingerprint invalidates changed tool definitions, including the effective MCP endpoint digest. Pending job flows remain in the existing suspension repository, while short operational run holds use the existing `IStateStore` rather than storing a second suspension. See [agent-action-approval-and-resume.md](agent-action-approval-and-resume.md) and [jobs.md](jobs.md). Chat-scoped and MCP-server-wide reusable approval remain unimplemented.
