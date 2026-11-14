# MissionBay Administration Displays

## Purpose

This document maps the current MissionBay administration displays to the settings or diagnostics they manage.

All displays are discoverable BASE3 display classes. Their stable technical names are listed below.

## Agent configuration

| Display name | Responsibility |
| --- | --- |
| `agentadmindisplay` | named agents in the `agent` group |
| `agentcompositionadmindisplay` | inspect effective compiled agent composition |
| `agentcomponentpresetadmindisplay` | reusable component presets |
| `agentcomponentpresettestadmindisplay` | materialize/test component presets |
| `agentorchestratorprofileadmindisplay` | orchestrator profiles |
| `toolprofileadmindisplay` | tool profiles |
| `agentmemoryprofileadmindisplay` | memory profiles |
| `agentcontextprofileadmindisplay` | context profiles |
| `agentinfotopicprovidertestadmindisplay` | info-topic provider tests |
| `agenttoollogadmindisplay` | tool execution/audit logs |
| `knowledgeagentmemoryadmindisplay` | knowledge-memory administration |
| `userprefdefadmindisplay` | agent-related user preference definitions |

### Agent file resources

The existing `agentadmindisplay` editor creates a durable, disabled agent draft with a cryptographically random UUID v4 as its canonical `agent_id` when **Add agent** is selected. This enables file uploads immediately, without assigning a separate temporary upload identity. The technical ID stays immutable. Existing stored IDs and plugin-defined `IAgentDefinition::getId()` identities are never rewritten.

When the BASE3 composition provides `IManagedFileStorageService`, `MissionBayPlugin` supplies `agentAdminDisplayData` containing endpoint URLs resolved through the active `ILinkTargetService`. The shared runtime form mounts the existing ClientStack FileManager in `collection` mode. When the storage contract is not bound, the upload UI remains disabled, while agent administration stays available. The editor binds each opened agent to the managed file owner `('agent', agent_id, 'collection')`. It destroys the prior manager on agent switching and dialog closing, and waits for pending uploads before saving. Drafts remain disabled until explicitly saved/enabled; abandoning an editor leaves an editable, disabled draft which can be deleted normally.

`Base3\MissionBay\Output\AgentFileManagerOutput` is the agent-owner HTTP entry point. It enforces the existing `agentadminsettingstab` feature authorization, verifies a trusted host actor and the canonical existing agent ID, and delegates file operations to the existing `Base3\ClientStack\FileManager\FileManagerHttpService`. ClientStack uses the active `Base3\ResourceFoundation\Api\IManagedFileStorageService`; MissionBay never implements a parallel upload service or file store. In ILIAS this binding is supplied by Base3Ilias; other hosts can provide their own implementation without MissionBay changes. The existing ILIAS chatbot upload endpoint remains untouched. `AgentConfigurationService` remains the sole configuration owner, and first discards unfinished FileManager sessions and then deletes managed storage before removing a non-fixed agent record. The configured storage backend must dispatch the existing `ManagedFileStorageDeletingEvent` before deleting a collection so MissionBay can remove external provider file references; the supplied ILIAS backend does this. This event behavior is not declared by `IManagedFileStorageService` itself and must be checked for other host implementations. Fixed agents may manage their resource collection but cannot be deleted.

During execution, the manually started agent, `ScheduledAgentRunnerJob`, and configured/available sub-agent tools pass the actual target `agent_id` and `agent_config_group`. `ConfiguredChatModelAgentResource` resolves these keys before any inherited chatbot owner context. The chatbot continues using its original chatbot owner keys. Only PDF files are supported by the current provider-file-reference integration, and the chosen chat model must implement `IAiFileCapableChatModel` when files exist. This is not a new RAG or indexing pipeline.

### Agent editor save in embedded hosts

The agent editor uses one BASE3 save endpoint and the existing agent form validation.
It sends settings as `application/x-www-form-urlencoded`, not `multipart/form-data`:
file resources use the separate existing FileManager endpoint. The URL-encoded
request preserves PHP bracket field names for profiles, policy settings, and
capability overrides. The modal explicitly handles its own submit events and
validates required controls without allowing native page navigation. It closes
only after a successful JSON response with `mode=save`, `action=saved`, and the
matching `record.agent_id`. An error or unexpected response leaves the editor
open and shows a failure, rather than silently treating a grid response as a
save. These rules apply uniformly to standalone BASE3 and embedded hosts.

### Agent list with unavailable runtimes

The Agent Admin grid uses persisted settings and the existing runtime registry only for optional summaries from installed runtimes. A stored agent referencing a runtime that is not installed on the current platform remains listed with its original runtime ID, even if the editor cannot load that runtime configuration. This is deliberately different from editing and execution: the strict selector still rejects an unavailable runtime, without changing the agent or selecting a substitute.

## Provider configuration

| Display name | Settings group |
| --- | --- |
| `connectionconfigdisplay` | `connection` |
| `llmconfigdisplay` | `service-llm` |
| `embeddingconfigdisplay` | `service-embedding` |
| `imageconfigdisplay` | `service-image` |
| `searchconfigdisplay` | `service-search` |
| `vectorsearchconfigdisplay` | `service-vectorsearch` |
| `vectorstoreconfigdisplay` | `service-vectorstore` |
| `parserserviceconfigdisplay` | `service-parser` |
| `speechtotextconfigdisplay` | `service-stt` |
| `texttospeechconfigdisplay` | `service-tts` |

The service displays share `AbstractServiceConfigDisplay` behavior so configuration, driver schemas and service tests are presented consistently.

## Retrieval and embedding

| Display name | Responsibility |
| --- | --- |
| `embeddingorchestratorconfigadmindisplay` | select embedding preset, vector-store preset and logical collection key |
| `retrievalcollectionadmindisplay` | logical collection to backend collection mapping |
| `retrievalsearchadmindisplay` | test retrieval searches through configured presets |
| `retrievalvectorpointsadmindisplay` | inspect vector index points through the configured collection definition |

The administration layer uses the same repositories and interfaces as runtime code. It should not maintain a second mapping for collections or providers.

## AJAX and embedding hosts

Many MissionBay displays are used inside embedded administration shells and tab controls. Display actions should return their own response content and avoid assumptions that a full page reload is available unless the surrounding host integration explicitly provides one.

## Adding an administration display

Use the normal BASE3 display discovery model:

1. implement the appropriate display/output contract
2. use a stable lowercase `getName()`
3. receive services through constructor DI
4. keep the display responsible for UI orchestration, not provider business logic
5. reuse settings repositories and service test services already used by runtime code
6. resolve static assets through `IAssetResolver`
