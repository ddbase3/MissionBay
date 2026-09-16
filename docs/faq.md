# MissionBay FAQ

## Purpose

This FAQ describes the current MissionBay plugin as implemented in this source package. It focuses on runtime architecture, agent composition, tools, providers, memory, retrieval, MCP, administration, auditing, persistence, security boundaries, and extension points.

MissionBay is a large implementation plugin. Not every discoverable node, resource, provider adapter, or administration display is active in every installation. Runtime behavior is determined by the configured BASE3 services, MissionBay settings, component presets, profiles, and the concrete tools attached to an agent or flow.

For technical data-processing details, see [../PRIVACY.md](../PRIVACY.md).

## 1. What is MissionBay?

MissionBay is the BASE3 implementation plugin for configurable AI services, agent execution, AgentFlow processing, tools, retrieval, MCP integration, parsing, speech, administration, usage reporting, and runtime auditing.

It provides both:

- a general declarative AgentFlow runtime, and
- a staged assistant runtime that compiles configured agents and runs model and tool interactions under explicit orchestration rules.

MissionBay does not define a second application container or a second plugin registry. It uses the normal BASE3 container for known services, `IClassMap` for discoverable implementations, `ISettingsStore` for editable configuration, and `IStateStore` for operational runtime state.

## 2. Which contracts does MissionBay implement?

MissionBay implements and consumes stable contracts from BASE3 and AssistantFoundation. Important runtime contracts include agent execution, conversation access, text tasks, stages, action policies, chat models, embedding models, image generation, retrieval, parsing, speech, and tool-result caching.

Reusable consumers should depend on those interfaces where replacement is expected. MissionBay-specific composition services remain inside MissionBay.

## 3. How does MissionBay relate to AssistantRuntime?

AssistantRuntime can route generic AssistantFoundation requests to MissionBay through a runtime adapter. This keeps a consumer from depending directly on MissionBay implementation classes.

The three primary MissionBay runtime services are:

```text
AgentExecutionService
AgentConversationService
AgentTextTaskService
```

MissionBay still owns its own AgentFlow compilation and MissionBay-specific orchestration internals.

## 4. Does MissionBay depend on a particular host application?

No. MissionBay is host-neutral. Host-specific resources, authorization rules, indexing jobs, data schemas, and context providers belong in extension packages or project composition.

The generic MissionBay package should not contain assumptions about one specific host data model.

## 5. What happens in `MissionBayPlugin::init()`?

`MissionBayPlugin::init()` performs composition only. It registers lazy factories, configured-component definitions, service bindings, listeners, policies, stages, resolvers, and related infrastructure.

A dedicated `MissionBayPluginLazyInitTest` protects this rule. Plugin initialization must not perform request-specific work, provider calls, database queries, or eager runtime service resolution.

This is important in embedded BASE3 installations because plugin initialization happens during host bootstrap.

## 6. What are the main MissionBay settings groups?

MissionBay currently uses these major `ISettingsStore` groups:

| Group | Purpose |
| --- | --- |
| `agent` | Runnable agent definitions. |
| `agent-component-preset` | Reusable configured agent resources. |
| `agent-orchestrator-profile` | Stage pipelines and orchestration limits. |
| `agent-memory-profile` | Conversation-memory preset selection. |
| `agent-context-profile` | Context-contributor preset selection. |
| `tool-profile` | Bounded tool sets, including MCP exposure settings. |
| `connection` | Provider endpoints, authentication definitions, and connection options. |
| `service-llm` | Configured chat-model services. |
| `service-embedding` | Configured embedding services. |
| `service-image` | Configured image-generation services. |
| `service-search` | Configured provider-backed web-search services. |
| `service-vectorsearch` | Configured vector-search services. |
| `service-vectorstore` | Configured vector-index and inspection services. |
| `service-parser` | Configured document parser services. |
| `service-stt` | Speech-to-text services. |
| `service-tts` | Text-to-speech services. |
| `retrieval-collection` | Logical collection key to backend collection mapping. |
| `embedding-orchestrator` | Active embedding and vector-store composition. |

MissionBay also seeds settings used for its AI-usage reporting definitions.

## 7. What is an agent definition?

A record in the `agent` settings group describes one runnable agent composition. The current compiler consumes fields including:

```text
chatmodel
orchestrator_profile
memory_profile
context_profile
tool_profiles
agent_components
expert_overrides_enabled
capability_sources
capability_selection
```

A chat-model preset is required. Optional profiles and directly attached component presets extend the composition.

## 8. How is an agent compiled?

`AgentFlowCompiler` translates an agent settings record into an `AgentFlowCompilation`.

The compiler:

1. requires the configured chat-model preset;
2. creates a base flow with one `aiassistantnode`;
3. loads an optional orchestrator profile;
4. applies capability configuration;
5. resolves tool, memory, and context profile components;
6. adds direct `agent_components`;
7. makes sure the chat model is attached exactly once; and
8. returns the effective flow definition plus warnings.

The result can be inspected through the effective-composition administration view.

## 9. What is a component preset?

A component preset is a configured runtime instance of a discoverable MissionBay resource implementation. Presets live in the `agent-component-preset` group.

A preset can define:

- a resource `type`;
- configuration values;
- enabled state;
- capability declarations;
- dock references to other presets; and
- labels or metadata.

One implementation class can therefore be reused by multiple independently configured presets without adding a second service container.

## 10. What is the difference between `getName()` and a component preset ID?

`getName()` is the stable technical name of a discoverable implementation class.

A component preset ID identifies one configured runtime instance of that implementation.

For example, one resource implementation can be used by several presets with different endpoints, model references, filters, namespaces, or docked resources.

## 11. Which stage pipeline is active by default?

When no explicit stage list is supplied, MissionBay uses this ordered pipeline:

```text
capability-discovery
capability-selection
model-decision
action-policy
tool-execution
context-compaction
tool-observation
semantic-verification
```

Discoverable stages such as `ai-capability-selection` and `final-answer-regenerate` also exist, but availability does not make them automatically active.

## 12. What does capability discovery do?

Capability discovery builds the eligible capability catalog for the current agent composition. It is constrained by what has actually been attached to the agent.

Discovery does not create new permissions. It identifies available capabilities within the configured composition.

## 13. What does capability selection do?

Capability selection narrows the already eligible catalog for the current request. It can use deterministic selection rules and, when explicitly configured, AI-assisted routing.

Selection does not grant capabilities that were not already eligible. AI-produced names are accepted only when they exist in the filtered candidate set.

## 14. Does an AI capability selector decide authorization?

No. Capability routing and authorization are separate concerns.

A model or selector can choose only among capabilities made available by server-side composition. Domain authorization, mandatory retrieval filters, mutation approval, and commit-time checks remain server-side responsibilities.

## 15. What happens if AI-assisted capability selection fails?

The documented AI selector path validates returned names against the eligible candidate set. Invalid output, provider failure, or an unavailable routing model does not create new capabilities.

The runtime can continue with its defined deterministic selection behavior for that stage. This is selection behavior inside the existing capability boundary, not a provider fallback chain for configured AI services.

## 16. Does MissionBay automatically try another provider if the configured provider fails?

No. `ConfiguredServiceRuntimeResolver` resolves exactly the configured service driver and connection.

A missing connection, invalid driver, disabled service, or invalid configuration is an error. MissionBay does not silently route the request to another provider.

## 17. What is AgentFlow?

AgentFlow is MissionBay's declarative flow runtime. A flow consists of nodes, resources, connections, initial values, and dock assignments.

Current discoverable flow implementations are:

```text
dynamicaiflow
strictflow
```

Flows are created through `IAgentFlowFactory`. Obsolete static construction patterns are not the current runtime boundary.

## 18. What kinds of AgentFlow nodes are included?

The current source tree contains AI, control-flow, core, data, HTTP, and message nodes.

AI nodes include:

```text
aiassistantnode
aiembedtextnode
aiindexingnode
deepltranslatenode
openairesponsenode
simplellamanode
simpleopenainode
```

Control-flow nodes include:

```text
conditionalpassnode
delaynode
foreachnode
ifnode
loopnode
noactionnode
subflownode
switchnode
```

Core and data nodes include:

```text
getconfigurationnode
getcontextvarnode
setcontextvarnode
testinputnode
arraygetnode
arraysetnode
jsontoarraynode
tryarraygetnode
```

HTTP and message nodes include:

```text
httpgetnode
httprequestnode
loggernode
staticmessagenode
stringreversernode
telegramsendmessage
```

Each node is opt-in through a concrete flow definition.

## 19. How are tools exposed to an assistant?

Tools implement MissionBay tool contracts and publish structured tool definitions. Tool definitions can include:

- function name;
- description;
- JSON input schema;
- output schema;
- label and category;
- read-only or mutation annotations;
- destructive, idempotent, or open-world hints;
- approval metadata; and
- commit-guard requirements.

Configured wrappers preserve these semantics when a tool is attached through a component preset.

## 20. How are tool inputs validated?

MissionBay has explicit tool-contract validation and structural result verification in the execution path. Tool calls are not treated as arbitrary free-form strings.

The execution path can validate the function name, arguments, expected schema, output structure, and tool-specific safety metadata before and after execution.

## 21. What decisions can an action policy return?

The action-policy layer can produce outcomes such as:

```text
allow
deny
require_approval
require_clarification
require_dry_run
```

The policy decision occurs before the actual mutation execution path.

## 22. How does human approval work?

When an action requires review, MissionBay does not execute it immediately. The review service persists an exact server-owned suspension and returns:

- a public interaction request; and
- an opaque `resume_handle`.

The current HTTP or streaming request then ends. A later request submits the handle and the user's response.

The client never sends a serialized suspension back to the server.

## 23. Can users approve actions in natural language?

Yes. A resume can contain natural-language `response_text` instead of normalized API responses.

MissionBay asks the active chat model to classify the response against the exact pending interaction requests. It accepts only validated structured decisions:

```text
approve
deny
submit
unclear
```

There is no fixed consent word list or regular expression that directly authorizes a mutation.

## 24. What happens when an approval response is unclear?

The pending action is not executed.

The resume claim is released, the original server-side suspension remains available while its TTL is valid, and the same interaction can be presented again. The handle is consumed only after a complete and valid response set has been accepted.

## 25. How is approval bound to the exact action?

`AgentActionFingerprint` binds the review to the action type, tool name, and canonical input. Modified arguments require a new review.

The reviewed action and its commit snapshot remain server-side.

## 26. Where are durable suspensions stored?

MissionBay consumes the `IAgentSuspensionRepository` contract. In the normal shared runtime composition, AssistantRuntime provides a state-store-backed repository.

The documented default lifecycle is:

```text
suspension TTL: 900 seconds
claim lease: 30 seconds
replay marker: 86400 seconds
```

MissionBay does not maintain a second copy of suspension state.

## 27. What if no durable suspension repository is available?

MissionBay can be composed with an unavailable repository implementation. Approval flows that require durable suspension cannot safely proceed through that boundary.

MissionBay does not create an unrelated hidden persistence path to compensate for the missing service.

## 28. What is the mutation commit guard?

Approval proves that a user approved a specific reviewed action. It does not prove that authorization and resource state are still valid later.

Immediately before a guarded mutation, MissionBay can call `IAgentMutationGuardedTool` to:

1. verify that the approved fingerprint is still bound to the current call;
2. recheck current authorization;
3. compare reviewed and current resource versions or hashes; and
4. deny stale or no-longer-authorized writes before `callTool()` is invoked.

The tool remains responsible for using atomic backend writes where appropriate.

## 29. Are all mutations required to use the commit guard?

For mutation definitions, `commitGuardRequired` defaults to true in the guarded path. A legacy or deliberately low-risk mutation can explicitly opt out, but the owning tool must make that choice visible in its definition.

`UserPrefsAgentResource` is an intentional low-risk exception. Its user preference write functions are marked as mutations but use:

```text
requiresApproval=false
commitGuardRequired=false
```

## 30. Are mutation results cached?

No. Mutation calls are never served from or written to the tool-result cache, even when a cache rule accidentally matches them.

This keeps approval and commit validation on the real execution path.

## 31. How does the tool-result cache work?

Tool-result caching is explicit and opt-in. The assistant node accepts a cache configuration with scoped rules and positive TTL values.

The current `StateStoreAgentToolResultCache` stores serialized `AgentToolCacheEntry` values under keys prefixed with:

```text
missionbay.agent_tool_result_cache.
```

The visible cache key is hashed with SHA-256 before it becomes a state-store key. Cache entries must have a TTL greater than zero.

Because cached results can contain business or personal data, cache rules should be limited to data that is actually safe to reuse within the configured scope.

## 32. What is the difference between conversation memory, context, and knowledge?

MissionBay intentionally separates three concepts:

```text
conversation memory
  visible user and assistant history plus conversation metadata

context contributors
  run-local system context such as time, preferences, page state, or other instruction blocks

knowledge or skills tool
  explicit agent-owned persistent information accessed through tool calls
```

These are different lifecycle and storage boundaries.

## 33. Which conversation-memory implementations are included?

MissionBay provides two canonical conversation-memory resources:

```text
sessionmemoryagentresource
databasememoryagentresource
```

A valid memory profile selects exactly one enabled preset that implements `IAgentConversationMemory`.

## 34. How does session conversation memory work?

`SessionMemoryAgentResource` stores the canonical conversation structure through the active BASE3 `ISession` implementation.

It serializes the conversation structure, base64-encodes it, and stores it in scalar chunks under session keys including:

```text
base3_missionbay_conversation_memory_format
base3_missionbay_conversation_memory_chunk_count
base3_missionbay_conversation_memory_chunk_...
```

The concrete persistence duration therefore depends on the active session backend and its lifetime policy.

## 35. How does database conversation memory work?

`DatabaseMemoryAgentResource` stores conversation metadata and messages in:

```text
base3_missionbay_conversation
base3_missionbay_conversation_message
```

Conversation metadata includes the owner scope, channel, namespace, conversation ID, title, title source, optional opening message, and timestamps.

Message rows include node ID, message ID, role, content, optional serialized payload metadata, and creation time.

Deleting a conversation cascades deletion of its message rows.

## 36. How are conversation owners identified?

Database memory prefers the current authenticated user ID. It stores a SHA-256 owner key derived from that user identity.

If no authenticated user identity is available, it derives the owner key from the active session ID.

The raw session ID is not stored in the conversation table as the owner key.

## 37. Does database conversation memory automatically expire old conversations?

No time-based retention job is implemented in `DatabaseMemoryAgentResource` itself.

The resource supports explicit conversation deletion and optional message trimming when trimming is enabled. Installations that require a time-based conversation retention policy need to implement it at the owning application or storage lifecycle boundary.

## 38. When is the current user message written to memory?

A normal new turn writes the current user message before later capability discovery, action policy, tool execution, or model processing can fail.

This means a failed downstream turn can still leave the user's visible message in the active conversation history.

## 39. Are suggestion requests stored as conversation messages?

No. Suggestion mode loads the active conversation as read-only context, disables tools, and disables both user and assistant memory writes.

Suggestions do not create a second conversation history.

## 40. Are context contributors re-read after a suspended action resumes?

No. Context contributors are resolved when the new turn begins. A suspended mutation resumes with the frozen reviewed message set rather than re-reading potentially changed preferences, page state, or other contributors.

This keeps the reviewed action bound to the context that produced it.

## 41. What persistent agent knowledge storage exists?

`KnowledgeAgentResource` uses `AgentKnowledgeService`, backed by the table:

```text
base3_agent_knowledge
```

The schema supports task, episodic, semantic, and procedural memory types plus title, content, summary, tags, entity references, metadata, source, scope, identity fields, mutability flags, priority, confidence, validity windows, expiration, last access, and created/updated identity fields.

Knowledge expiration is represented in the data model. The current source does not define a general automatic physical purge job for expired knowledge rows.

## 42. What does the focus resource store?

`FocusAgentResource` can persist user- or session-scoped focus text in:

```text
base3_missionbay_focus_state
```

The row includes scope, identity, optional user/session references, resource ID, focus text, and timestamps.

Focus should be treated as potentially sensitive because the text can reflect the user's current task, object, or business context.

## 43. What do user preferences store?

`UserPrefsAgentResource` uses:

```text
base3_missionbay_userpref_def
base3_missionbay_userpref_value
```

Definitions describe allowed preference keys, descriptions, templates, value types, allowed values, default scope, sort order, and enablement.

Values can be user- or session-scoped and contain the effective preference value plus identity information.

## 44. Which provider service types can be configured?

The configured-service runtime currently supports these service groups:

```text
service-llm
service-embedding
service-image
service-search
service-vectorsearch
service-vectorstore
service-parser
service-stt
service-tts
```

A service preset references a connection and a discoverable service-driver definition.

## 45. Which configured service drivers are included?

The current source includes driver definitions for:

Chat:

```text
mistralchatservicedriverdefinition
openaichatservicedriverdefinition
openaicompatiblechatservicedriverdefinition
```

Embeddings:

```text
openaiembeddingservicedriverdefinition
openaicompatibleembeddingservicedriverdefinition
```

Image generation:

```text
mistralimageservicedriverdefinition
openaiimageservicedriverdefinition
openaicompatibleimageservicedriverdefinition
```

Provider-backed web search:

```text
mistralwebsearchservicedriverdefinition
openaiwebsearchservicedriverdefinition
```

Vector store:

```text
qdrantvectorstoreservicedriverdefinition
```

Document parsing:

```text
doclingparserservicedriverdefinition
unstructuredparserservicedriverdefinition
```

Speech:

```text
mistralspeechtotextdriverdefinition
mistraltexttospeechdriverdefinition
openaispeechtotextdriverdefinition
openaitexttospeechdriverdefinition
```

## 46. What is stored in a connection definition?

A connection can contain:

- ID and label;
- connection type and driver;
- base URL;
- authentication type;
- optional authentication header name;
- timeout;
- scope;
- enabled state;
- connection options; and
- an authentication secret definition.

The secret is resolved only when the configured runtime service is materialized.

## 47. Which secret-definition modes can a connection use?

MissionBay uses the BASE3 config-value resolver boundary. Connection secrets can use definitions such as:

```text
fixed
env
configuration
file
```

A fixed secret is stored as part of the connection settings record. Environment, configuration, and file modes store a reference that is resolved at runtime.

The connection administration response masks a fixed secret value and exposes only that a value is configured.

## 48. Are connection secrets copied into every service preset?

They should not be. Service presets reference a connection. At runtime, `ConfiguredServiceRuntimeResolver` resolves the connection secret and injects it into the concrete service options.

This keeps connectivity and credential configuration at the connection boundary.

## 49. Does MissionBay contain provider-specific resources in addition to configured service drivers?

Yes. The source tree also contains direct or legacy-style agent resources for providers and compatible APIs, including resources for Anthropic, DeepSeek, Fireworks, Gemini, Grok/xAI, Groq, Mistral, OpenAI, OpenRouter, Perplexity, generic chat models, and routing models.

These resources are discoverable components. Their presence does not mean that an installation uses all of them.

New general provider integrations should prefer the configured service-driver architecture when the provider fits an existing service contract.

## 50. What data is sent to a configured chat model?

The exact request depends on the active model and orchestration stage. For a normal assistant turn, model-facing messages can include:

- base system instructions;
- context-contributor blocks;
- visible conversation history;
- the current user message;
- tool definitions selected for the turn;
- tool observations from completed calls; and
- orchestration instructions required by the active stages.

Only configured capabilities should be exposed to the model.

## 51. How does image generation work?

MissionBay provides configured image-generation models for OpenAI, OpenAI-compatible endpoints, and Mistral.

The prompt and generation options are sent to the configured provider. Results can include image bytes encoded in provider responses, provider-hosted image URLs, and provider metadata such as revised prompts or usage information, depending on the driver.

MissionBay does not automatically create a durable media object for every returned provider URL. A consumer that needs durable ownership must store the resulting media through its own storage boundary.

## 52. How does provider-backed web search work?

The `service-search` group can select OpenAI or Mistral search services. The search query and configured options are sent to the selected provider.

Options can include external web access behavior, allowed or blocked domains, search context size, token budgets, and provider tool choices.

Provider-backed search is separate from local retrieval over a configured vector collection.

## 53. How does speech-to-text work?

Configured STT drivers send audio data and transcription options to the selected OpenAI or Mistral endpoint.

Realtime speech-to-text can establish a provider session and return a client/session credential required by the realtime protocol. Service tests intentionally do not display the returned client token.

MissionBay itself does not define a persistent database table for uploaded or realtime audio.

## 54. How does text-to-speech work?

Configured TTS drivers send text and speech options such as model, voice, response format, and provider-specific instructions to the selected provider and return audio data through the shared speech result contracts.

The submitted text can contain personal or confidential information and must be treated as provider-bound content.

## 55. How do document parser services work?

MissionBay includes configured parser services for Docling and Unstructured-compatible endpoints.

The parser accepts text, files, or streams. File parsing can upload the actual local file bytes as multipart content to the configured parser service.

For stream input, MissionBay creates a temporary file under the system temporary directory and removes it in a `finally` path after the parser request completes.

Parser metadata can include the source file location. Consumers should avoid exposing local filesystem paths beyond the intended trusted processing boundary.

## 56. What happens when a parser provider returns an error?

Parser exceptions can include provider error details. The parser implementation limits raw provider error-body excerpts, but an exception surfaced into logs or administration still may contain provider-returned text.

Operational logs and error displays should therefore be treated as potentially sensitive.

## 57. What is the retrieval collection model?

MissionBay separates a logical collection key from a physical backend collection name.

Logical mappings are stored in:

```text
retrieval-collection
```

The active embedding composition is stored in:

```text
embedding-orchestrator/default
```

with:

```text
embedding_preset
vector_store_preset
collection_key
```

## 58. What does `IRetrievalCollectionDefinition` control?

The collection definition owns the semantic contract for one retrieval domain. It can define:

- logical collection keys;
- backend collection names;
- dense and sparse representations;
- payload schema;
- payload validation;
- agent-visible filter schema;
- agent-context projection;
- grouping and ordering; and
- encoding choices.

This is the boundary between technical stored payload and the data an agent is allowed to filter or receive.

## 59. Are model-requested retrieval filters authorization rules?

No. Agent-requested filters are data, not authority.

Mandatory ACL, tenant, source, ownership, or other security constraints must be injected server-side by the collection definition, retrieval filter providers, or backend integration. The model must not be allowed to remove mandatory filters.

## 60. Does MissionBay send the complete vector-store payload to the model?

It should not. The retrieval backend can store technical fields required for indexing, deletion, ACL, or bookkeeping. The collection definition decides which fields are projected into agent context.

The agent-visible filter surface is also explicitly smaller than the full technical payload schema where appropriate.

## 61. Which vector store is built in?

MissionBay includes a Qdrant vector-store driver.

Qdrant connectivity is configured as a normal service and connection. MissionBay's generic retrieval layer still addresses logical collections through the collection definition instead of embedding physical Qdrant collection names in every agent setting.

## 62. What is stored in the embedding cache?

`EmbeddingCacheAgentResource` uses a configurable table whose default name is:

```text
base3_embedding_cache
```

It stores:

- a SHA-256 hash derived from normalized input text plus model/salt/dimensions;
- model;
- vector dimension;
- the embedding vector as JSON;
- creation and last-access timestamps; and
- hit count.

The original input text is not stored in this cache table, but the embedding itself remains data derived from the input and can be sensitive.

## 63. Does the embedding cache have an automatic TTL?

The database-backed embedding cache implementation does not define an automatic TTL cleanup in this source snapshot.

Retention has to be handled by the installation or by an explicit future cache lifecycle mechanism.

## 64. What is inbound MCP in MissionBay?

MissionBay exposes a profile-based MCP server through a host-provided endpoint.

The profile is selected by a `profile` query parameter and comes from the `tool-profile` settings group. It controls which tool presets are exposed and which authentication paths are enabled.

## 65. Which inbound MCP transport is supported?

The current inbound server supports:

```text
POST application/json
```

It does not provide a GET-based SSE server channel. Non-POST methods are rejected.

Supported protocol versions are:

```text
2025-11-25
2025-06-18
2025-03-26
```

A missing protocol version is tolerated for compatibility after initialization rules are applied.

## 66. Which inbound MCP methods are supported?

The current server supports:

```text
initialize
ping
notifications/initialized
notifications/cancelled
tools/list
tools/call
resources/list
resources/read
resources/templates/list
prompts/list
prompts/get
```

Cancellation notifications are accepted, but the synchronous PHP server cannot interrupt tool code that is already executing.

## 67. How can an inbound MCP profile be authenticated?

A profile can enable:

- a fixed shared Bearer token stored in the tool-profile record;
- personal credentials through `CredentialFoundation`; or
- both.

Personal credential authorization uses the stable service ID:

```text
missionbay:mcp:<profile-id>
```

Profile IDs are therefore immutable after creation.

## 68. What inbound MCP HTTP protections are implemented?

The current server includes:

- POST-only enforcement;
- `Accept` validation;
- same-host `Origin` checking;
- protocol-version validation;
- a 1 MiB request-body limit based on `Content-Length`;
- fixed-token comparison with `hash_equals`;
- optional personal Bearer or HMAC credentials;
- per-profile credential service grants;
- profile-bounded tools; and
- MCP call audit logging.

Rate limiting and OAuth client registration are not implemented in this MCP server version.

## 69. Who owns approval for inbound MCP tool calls?

The MCP client or host owns approval before it sends `tools/call`.

MissionBay publishes tool safety annotations and then executes an authorized MCP tool call directly after endpoint and profile authorization. It does not create a second MissionBay pending-confirmation flow for inbound MCP.

The staged in-process MissionBay agent runtime continues to use its own action policy, durable suspension, and commit-guard path.

## 70. Is the fixed inbound MCP token masked by the tool-profile administration API?

The tool-profile page list exposes only whether a token is configured. However, the current record response includes the stored `token` value and a profile JSON representation used by the administration UI.

That means the detailed tool-profile administration response must be treated as secret-bearing and protected accordingly. This behavior is different from the connection display, which masks fixed connection secret values.

## 71. What is outbound MCP in MissionBay?

`McpClientAgentResource` represents one remote MCP Streamable HTTP server as a normal MissionBay component preset.

It can expose remote:

- tools;
- resources;
- resource templates;
- prompts; and
- server instructions.

The resource participates in the existing tool-profile and context-contributor architecture rather than introducing a second MCP-specific registry.

## 72. Which outbound MCP authentication types are supported?

The remote MCP client supports:

```text
none
bearer
api_key
hmac
basic
```

Connection fields and custom headers can use config-value definitions. Embedded URL credentials and URL fragments are rejected.

Custom headers cannot override protocol-controlled headers, `Authorization`, MCP session headers, or the reserved `X-BASE3-*` HMAC headers.

## 73. Are outbound MCP literal tokens returned by the component-preset administration API?

The MCP client resource schema marks its secret values so the component-preset administration can represent an existing literal token with an internal configured-secret marker.

The actual literal token is not returned in the preset JSON or client diagnostics through that path. This is distinct from the inbound MCP fixed token stored in a tool-profile record.

## 74. Does outbound MCP verify TLS?

TLS certificate and host verification are enabled by default. The resource has a `verify_tls` configuration option, so installations can alter this behavior explicitly.

The default should remain enabled for remote services.

## 75. What limits are applied to outbound MCP?

Current configurable defaults include:

```text
connect_timeout: 10 seconds
request_timeout: 60 seconds
max_response_bytes: 2097152
max_pages: 20
max_items: 500
verify_tls: true
```

The client sends POST requests, does not follow redirects, and redacts configured endpoint/credential/header secrets from propagated errors.

## 76. Can remote MCP resources or prompts become model context?

Yes, when explicitly configured through `context_resources`, `context_prompts`, or server-instruction settings.

Binary resource bytes are retained as MCP results but are not injected directly into model context. The model receives an omission marker for binary selected resources instead.

Remote content should be treated as untrusted external content even when transport authentication succeeds.

## 77. Are remote MCP safety annotations trusted as authorization evidence?

No. MissionBay maps remote annotations into local tool metadata, but those annotations are assertions made by the remote MCP server.

They can inform the local action policy, but they do not independently prove domain authorization or current remote resource state.

A generic remote MCP tool cannot provide the same local domain commit guard unless the remote system itself offers the required guarantees.

## 78. Which direct external-network tools and nodes exist?

Besides configured provider services and MCP, the current source includes optional components that can call external services directly, including:

- `webfetchtextagenttool` for public HTTP/HTTPS page fetches;
- `httpgetnode` and `httprequestnode` for generic HTTP operations;
- `deepltranslatenode` for DeepL translation;
- `telegramsendmessage` and `telegramagenttool` for Telegram messages;
- `weatheragenttool` using Open-Meteo;
- `currencyconvertagenttool` using Frankfurter; and
- direct provider resources for several AI APIs.

These components create external data-transfer boundaries only when a flow or agent actually uses them.

## 79. What protections does `webfetchtextagenttool` apply?

The built-in web fetch tool:

- accepts only HTTP and HTTPS URLs;
- rejects embedded URL credentials;
- blocks localhost and private/reserved IP targets;
- rechecks the effective URL after redirects;
- verifies TLS certificates and host names;
- limits redirects;
- uses connection and request timeouts;
- caps downloaded response data at 256 KiB; and
- caps returned plain text at 12,000 characters.

It returns extracted title, description, and plain text rather than raw HTML.

## 80. Does MissionBay log tool execution?

Yes, when the database-backed tool event listener is active. Tool lifecycle events are persisted in:

```text
base3_missionbay_tooluse
```

This table can contain highly sensitive data, including:

- user ID and login;
- prompt text;
- tool name and label;
- tool arguments;
- tool result;
- error message, type, and code;
- run metadata; and
- timestamps.

This log is an operational and audit surface, not a sanitized analytics-only table.

## 81. Does MissionBay log provider usage?

Yes. Normalized provider request-completion events can be persisted in:

```text
base3_missionbay_ai_usage
```

Fields include operation, source, provider, model, request ID, user ID/login, request context, token counts, duration, finish reason, provider timestamp, and JSON metadata fields.

There are no dedicated prompt or response-body columns in this usage table, but `details_json` and `extra_json` can contain provider-derived metadata and are treated as sensitive by the shipped reporting schema.

## 82. Does MissionBay provide AI usage reports?

Yes. MissionBay ships reporting definitions for a `missionbay_ai` scope. On the appropriate bootstrap hook, default source and report definitions can be seeded into settings when the reporting seeder service is active.

The shipped report definitions cover overall metrics, models, operations, requests, token timelines, and users.

The underlying usage schema marks `user_id`, `user_login`, `details_json`, and `extra_json` as sensitive fields.

## 83. Are action-audit events the same as the tool log?

No. MissionBay distinguishes:

```text
action audit
  approval and commit-state transitions

provider usage
  model/provider request metrics

tool lifecycle audit
  tool start, finish, failure, arguments, results, and errors
```

`MissionBayAgentActionAuditEvent` can emit transitions such as:

```text
approval_requested
approval_granted
approval_denied
commit_allowed
commit_blocked
commit_succeeded
commit_failed
```

A project can attach its own listener to persist action audit events.

## 84. Does MissionBay automatically delete old audit and usage rows?

No general automatic retention cleanup for `base3_missionbay_tooluse` or `base3_missionbay_ai_usage` was identified in this source package.

Installations should define retention according to their operational, security, and privacy requirements.

## 85. Which database tables can MissionBay create or use directly?

The current source contains these MissionBay-owned tables or default table names:

```text
base3_missionbay_conversation
base3_missionbay_conversation_message
base3_missionbay_tooluse
base3_missionbay_ai_usage
base3_missionbay_userpref_def
base3_missionbay_userpref_value
base3_missionbay_focus_state
base3_agent_knowledge
base3_embedding_cache
```

Some features create their tables lazily with `CREATE TABLE IF NOT EXISTS` when the feature is used.

Vector data can additionally be persisted in the configured external vector store, such as Qdrant.

## 86. Does every MissionBay installation create every table?

No. Several feature tables are created by the resource or listener that owns them. If a feature is never materialized or used, its table may never be created.

The final runtime composition also determines whether database-backed listeners and resources are active.

## 87. What does scheduled agent execution do?

`scheduledagentrunnerjob` is the discoverable MissionBay background job for configured agents.

It reads enabled agent definitions, applies the configured job execution policy, and runs agent prompts without requiring an interactive browser turn.

Scheduled runs can invoke the same provider and tool boundaries as interactive agents, subject to the agent's configured composition and the worker's runtime identity and services.

## 88. What context does a scheduled agent receive?

The job uses a stable conversation channel in the form:

```text
scheduled-agent:<agent-id>
```

It also passes the scheduled agent configuration into run context. Administrators should not place secrets or unrelated personal data into agent settings merely because the record is convenient to access from a scheduled job.

## 89. What administration displays are included?

The current source includes administration displays for agents, component presets, component tests, effective composition, orchestrator profiles, tool profiles, memory profiles, context profiles, info-topic tests, tool logs, connections, all configured provider service groups, embedding orchestration, retrieval collections, retrieval search, vector-point inspection, knowledge memory, and user preference definitions.

The exact technical names are listed in the current component catalog at the end of this FAQ.

## 90. Do MissionBay administration displays implement their own user authorization boundary?

The MissionBay display classes are UI and administration components, not a complete authentication system. In this source snapshot, they do not form a general per-display user authorization boundary themselves.

The host administration routing and access-control layer must ensure that only authorized administrators can reach configuration, test, log, retrieval, and mutation actions.

## 91. Do MissionBay administration displays implement a MissionBay-specific CSRF token layer?

No MissionBay-local CSRF token mechanism was found in the current source package.

Where an administration display accepts state-changing browser requests, the hosting administration framework must provide an appropriate request-integrity boundary if the deployment requires CSRF protection.

## 92. Can administration responses contain credentials or sensitive diagnostics?

Yes, depending on the screen.

Examples:

- connection administration masks fixed connection secret values;
- MCP client preset administration uses a configured-secret marker for protected preset secrets;
- inbound MCP tool-profile record responses currently include the stored fixed token;
- tool logs can contain prompts, arguments, results, and error messages;
- retrieval diagnostics can expose retrieved content or vector payloads; and
- provider tests can expose bounded result previews and provider errors.

Administration endpoints should therefore be treated as privileged data interfaces.

## 93. What is the recommended debugging order?

The MissionBay documentation recommends debugging along the actual composition path:

1. verify BASE3 dependencies and settings/state services;
2. verify connection configuration;
3. test the configured provider service;
4. test the component preset;
5. inspect tool, context, memory, and orchestrator profiles;
6. inspect effective agent composition;
7. inspect tool/audit logs and provider usage logs; and
8. only then debug an agent stage or tool implementation.

This avoids adding workaround paths that hide an incorrect configuration or contract.

## 94. How should a new tool be added?

A new tool should implement the appropriate MissionBay tool contract and expose a stable lowercase technical name.

The tool definition should provide an explicit input schema, output semantics, and truthful safety metadata. A mutating tool should declare mutation metadata and implement `IAgentMutationGuardedTool` when commit-time revalidation is required.

If multiple configured instances are needed, expose the tool through a component preset rather than creating hardcoded project-specific branches in the agent runtime.

## 95. How should a new provider driver be added?

A provider extension normally adds:

1. an implementation behind the appropriate AssistantFoundation or MissionBay service contract;
2. an `IServiceDriverDefinition` with a stable technical name;
3. connection-driver metadata if a new connection type is needed; and
4. schemas and options required by the generic service-configuration UI.

`ConfiguredServiceRuntimeResolver` should not gain a provider-specific fallback branch when discovery and a driver definition can express the integration.

## 96. How should a host-specific retrieval schema be added?

Provide a host-specific `IRetrievalCollectionDefinition` and related indexing/filter components in the host extension package.

The host definition should own domain schema, mandatory authorization filters, payload projection, and domain-specific metadata. MissionBay remains generic.

## 97. How are config values resolved inside agent presets?

`IAgentConfigValueResolver` wraps the generic BASE3 config-value resolver and supports MissionBay-specific runtime modes where needed.

Generic fixed, environment, configuration, and file modes remain owned by BASE3. MissionBay-specific runtime modes currently include:

```text
inherit
random
uuid
```

Unknown generic modes should not be reimplemented inside MissionBay.

## 98. Which runtime state keys does MissionBay use?

Important state and session namespaces include:

| Prefix or key | Purpose |
| --- | --- |
| `missionbay.agent_tool_result_cache.` | TTL-bound tool-result cache entries. |
| `missionbay.agent.tool_loop.` | Tool-loop runtime state. |
| `missionbay.agent.tool_audit.current` | Run-local tool audit context key. |
| `base3_missionbay_conversation_memory_format` | Session conversation-memory format marker. |
| `base3_missionbay_conversation_memory_chunk_count` | Session conversation-memory chunk count. |
| `base3_missionbay_conversation_memory_chunk_...` | Session conversation-memory chunks. |

Durable agent suspensions use the shared `IAgentSuspensionRepository` boundary rather than a second MissionBay-owned suspension store.

## 99. What built-in resources and tools are discoverable?

The current source catalog includes these technical names:

```text
statictextcontextagentresource
timememoryagentresource
runavailableagenttool
runconfiguredagenttool
batchagenttool
currencyconvertagenttool
currenttimeagenttool
logagenttool
systemstatusagenttool
weatheragenttool
webfetchtextagenttool
anthropicchatmodelagentresource
blockchatbotagenttool
canvascloseagenttool
configuredagentmemoryresource
configuredagenttoolresource
configuredchatmodelagentresource
configuredembeddingmodelagentresource
configuredimagemodelagentresource
configuredparserserviceagentresource
configuredsearchserviceagentresource
configuredvectorsearchagentresource
configuredvectorstoreagentresource
crmproductxrmextractoragentresource
databasememoryagentresource
deepseekchatmodelagentresource
dummyembeddingmodelagentresource
dummyextractoragentresource
embeddingcacheagentresource
fireworkschatmodelagentresource
focusagentresource
geminichatmodelagentresource
generalinfoagenttool
genericchatmodelagentresource
grokchatmodelagentresource
groqchatmodelagentresource
helloworldcanvasagenttool
knowledgeagentresource
loggerresource
mcpclientagentresource
mermaidsyntaxagenttool
mistralchatmodelagentresource
nochunkeragentresource
noembeddingmodelagentresource
noparseragentresource
openaichatmodelagentresource
openaiembeddingmodelagentresource
openrouterchatmodelagentresource
perplexitychatmodelagentresource
productxrmextractoragentresource
qdrantvectorsearch
ragsearchagenttool
retrievalagenttool
routingchatmodelagentresource
semanticchunkeragentresource
sessionmemoryagentresource
structuredobjectparseragentresource
telegramagenttool
toolproxyagenttool
uploadstreamextractoragentresource
userprefsagentresource
xrmchunkeragentresource
```

Not every resource is intended for every production composition. Discovery only means the implementation exists.

## 100. Which administration display names are discoverable?

The current catalog includes:

```text
agentadmindisplay
agentcomponentpresetadmindisplay
agentcomponentpresettestadmindisplay
agentcompositionadmindisplay
agentcontextprofileadmindisplay
agentinfotopicprovidertestadmindisplay
agentmemoryprofileadmindisplay
agentorchestratorprofileadmindisplay
agenttoollogadmindisplay
connectionconfigdisplay
embeddingconfigdisplay
embeddingorchestratorconfigadmindisplay
imageconfigdisplay
knowledgeagentmemoryadmindisplay
llmconfigdisplay
parserserviceconfigdisplay
retrievalcollectionadmindisplay
retrievalsearchadmindisplay
retrievalvectorpointsadmindisplay
searchconfigdisplay
speechtotextconfigdisplay
texttospeechconfigdisplay
toolprofileadmindisplay
userprefdefadmindisplay
vectorsearchconfigdisplay
vectorstoreconfigdisplay
```

## 101. Which policies and jobs are discoverable?

Current action policies:

```text
allowallagentactionpolicy
mutationapprovalagentactionpolicy
```

Current MissionBay job:

```text
scheduledagentrunnerjob
```

The active action policy and job execution behavior still depend on final runtime composition and settings.

## 102. Where should I look for deeper technical documentation?

The MissionBay package already contains detailed subsystem documentation under `docs/`, including architecture, bootstrap, settings, configured services, component presets, stage pipeline, memory and context, action approval, durable suspensions, commit guards, retrieval, parsing, MCP, speech, administration, events, jobs, testing, API reference, source map, and component catalog.

This FAQ is intended as a broad operational and architectural reference. The subsystem documents remain authoritative for implementation-specific detail.

## 103. Where is privacy and data-processing behavior documented?

See [PRIVACY.md](../PRIVACY.md). It documents MissionBay's data categories, persistent stores, provider boundaries, credentials, audit tables, memory, retrieval, MCP, speech, parsing, external-network tools, retention considerations, and deployment controls in detail.
