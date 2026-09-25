# MissionBay Privacy and Data Processing

> This document describes the technical data-processing behavior visible in the current MissionBay source package. It is not a legal privacy notice and does not determine the legal basis, controller, processor, retention obligation, or contractual terms of a concrete deployment. Provider behavior, hosting locations, logging infrastructure, storage backends, and host access controls must be documented for the actual installation.

## 1. Scope

MissionBay is a configurable AI and agent runtime. It can process prompts, model messages, tool arguments and results, retrieved content, documents, embeddings, images, search queries, audio, speech text, MCP payloads, user preferences, focus state, knowledge entries, conversation history, provider usage metadata, and administrative configuration.

Not every installation uses every capability. Data processing depends on the configured agent, component presets, provider services, tools, memory backend, retrieval collection, MCP profiles, and host composition.

MissionBay also contains administration and diagnostic surfaces. These surfaces can expose sensitive configuration, runtime content, logs, retrieval results, vector payloads, and provider diagnostics and must be protected as privileged interfaces.

For functional questions, see [docs/faq.md](docs/faq.md).

## 2. Main technical data-flow model

A typical interactive agent turn can cross several distinct boundaries:

```text
user or calling service
  -> Assistant runtime / MissionBay execution request
  -> conversation memory
  -> context contributors
  -> selected model provider
  -> optional tool calls
       -> local application services
       -> retrieval/vector store
       -> parser service
       -> web or external API
       -> remote MCP server
  -> tool observations
  -> model provider
  -> assistant result
  -> conversation memory
  -> tool audit / provider usage audit
  -> calling service
```

A concrete request may use only a subset of these steps.

MissionBay does not imply that all configured data must be sent to every provider. Each agent composition should expose only the model, context, tools, and retrieval projection required for its purpose.

## 3. Categories of data MissionBay can process

Depending on configuration and user input, MissionBay can process the following categories.

### 3.1 User and model messages

This can include:

- user prompts;
- assistant responses;
- system instructions;
- conversation titles and opening messages;
- model-facing context blocks;
- prior visible conversation messages;
- structured model output; and
- natural-language approval or clarification responses.

Free text can contain personal data, confidential business information, credentials pasted by users, health or other sensitive information, or content that was not intended for external provider processing.

### 3.2 Identity and session information

MissionBay can receive or derive:

- authenticated user IDs;
- login names;
- session IDs or session-derived identities;
- request context;
- conversation owner hashes;
- user- or session-scoped preference identities; and
- user- or session-scoped focus or knowledge identities.

Different features intentionally use different identity representations. For example, database conversation memory stores a SHA-256 owner key, while the AI usage and tool audit tables contain explicit user IDs and login names.

### 3.3 Tool arguments, results, and errors

Tool calls can contain arbitrary domain data. This can include:

- identifiers;
- names;
- search terms;
- record data;
- report data;
- user-provided values;
- mutation inputs;
- external API results;
- error messages; and
- structured metadata.

The database-backed tool audit listener can persist the full arguments and results.

### 3.4 Retrieval and knowledge content

MissionBay can process:

- document chunks;
- retrieval references;
- vector payloads;
- projected agent context;
- metadata and filters;
- embeddings;
- knowledge entries;
- tags and entity references; and
- technical indexing metadata.

Host-specific ACL or tenancy data can also exist in vector payloads. Such fields should remain server-side unless the collection definition explicitly projects them into model context.

### 3.5 Documents and parser input

Configured parser services can receive:

- raw text;
- file bytes;
- stream data;
- filename or file path metadata;
- content types;
- parser options; and
- provider-returned structured document data.

### 3.6 Image-generation input and output

Image generation can process:

- textual prompts;
- provider and model options;
- requested size/format/quality;
- provider-returned image bytes or base64 data;
- provider-hosted URLs;
- revised prompts; and
- provider usage metadata.

### 3.7 Speech data

Speech-to-text can transmit audio to a configured provider. Text-to-speech can transmit text and voice configuration to a configured provider.

Audio may contain biometric, identifying, confidential, or other sensitive information depending on what the speaker says and how the deployment uses the feature.

### 3.8 MCP data

Inbound and outbound MCP can process:

- JSON-RPC request and response bodies;
- tool definitions;
- tool arguments and results;
- resources and resource content;
- prompts and prompt arguments;
- server instructions;
- session identifiers;
- endpoint URLs;
- authentication material; and
- HMAC metadata.

### 3.9 Operational and diagnostic metadata

MissionBay can record:

- provider and model names;
- request IDs;
- token counts;
- request durations;
- finish reasons;
- tool names and statuses;
- trace IDs and call IDs;
- request contexts;
- timestamps; and
- provider-specific metadata.

## 4. Persistent settings and configuration

MissionBay primarily stores editable configuration through BASE3 `ISettingsStore`.

Important settings groups include:

```text
agent
agent-component-preset
agent-orchestrator-profile
agent-memory-profile
agent-context-profile
tool-profile
connection
service-llm
service-embedding
service-image
service-search
service-vectorsearch
service-vectorstore
service-parser
service-stt
service-tts
retrieval-collection
embedding-orchestrator
```

Reporting definitions are also seeded into MissionBay-specific settings groups when the AI usage reporting seeder is active.

The concrete persistence location of `ISettingsStore` depends on the BASE3 runtime composition. It can be database-backed or use another implementation.

### 4.1 Configuration can itself contain sensitive data

Settings can contain:

- provider endpoints;
- model names;
- internal service identifiers;
- tool and agent instructions;
- fixed credentials;
- references to environment variables or secret files;
- remote MCP endpoints;
- remote MCP headers;
- tool allowlists/denylists;
- system prompts; and
- scheduled-agent prompts.

Settings storage must therefore be protected as configuration data, not treated as harmless UI metadata.

## 5. Provider connections and secret handling

MissionBay separates connection definitions from service presets.

A connection contains endpoint and authentication information. Service presets refer to the connection and select the concrete service driver and model.

### 5.1 Supported secret-definition patterns

Connection authentication secrets can use BASE3 config-value definitions such as:

```text
fixed
env
configuration
file
```

A fixed secret is stored directly in the settings record. Environment, configuration, and file modes store a reference that is resolved later.

The appropriate secret mode depends on the deployment. A deployment that requires secret separation should avoid storing a long-lived production secret as a fixed value in a general settings backend.

### 5.2 Runtime secret materialization

`ConfiguredServiceRuntimeResolver` resolves the configured secret at runtime and provides it to the selected service implementation as runtime options such as `apikey` and `auth_secret`.

Resolved secrets should remain in process memory only for the time required by the active request or service object and should not be copied into unrelated settings or diagnostic output.

### 5.3 Connection administration masking

`ConnectionConfig::toDisplayArray()` masks the value of a fixed authentication secret. The administration response indicates that a secret is configured but does not return the fixed secret value through that display path.

References such as environment variable names, configuration group/key references, or secret-file paths can still be visible to an administrator because they are not the secret value itself.

## 6. Component-preset secrets

Some component presets contain their own secret-bearing configuration, particularly remote MCP client presets and direct provider resources.

The component-preset administration uses configured-secret markers for recognized secret fields so an existing literal secret can be preserved without returning it to the browser in normal preset JSON.

This masking applies to that component-preset administration boundary. It must not be assumed for every MissionBay settings group.

## 7. Inbound MCP fixed bearer tokens

Inbound MCP profiles are stored in the `tool-profile` settings group and can contain a fixed Bearer token in the `token` field.

This token is a shared technical credential.

The list response from `ToolProfileAdminDisplay` exposes only whether a token is configured. The current detailed record response, however, includes the stored `token` value and a profile JSON representation used by the administration form.

Consequences:

- the tool-profile administration endpoint is secret-bearing;
- responses must not be cached or exposed to unauthorized users;
- browser access to that page must be tightly controlled;
- reverse proxies and diagnostic tooling should not record the response body; and
- installations that do not need fixed Bearer tokens should prefer personal credential authorization through the credential boundary.

This behavior is different from the connection display, which masks fixed connection secret values.

## 8. Conversation memory

MissionBay provides session-backed and database-backed conversation memory.

### 8.1 Session conversation memory

`SessionMemoryAgentResource` stores the complete canonical conversation structure through the active BASE3 `ISession` implementation.

The data is serialized, base64-encoded, split into chunks, and stored under keys including:

```text
base3_missionbay_conversation_memory_format
base3_missionbay_conversation_memory_chunk_count
base3_missionbay_conversation_memory_chunk_...
```

The session data can include:

- conversation IDs;
- titles and title source;
- opening messages;
- timestamps;
- node histories;
- message IDs;
- roles;
- message content;
- feedback; and
- arbitrary extra message metadata.

Retention follows the active session implementation and session lifetime. MissionBay does not independently know how long the host keeps session data.

### 8.2 Database conversation memory

`DatabaseMemoryAgentResource` stores data in:

```text
base3_missionbay_conversation
base3_missionbay_conversation_message
```

`base3_missionbay_conversation` contains:

- `conversation_key`;
- `owner_key`;
- `channel_id`;
- `memory_namespace`;
- `conversation_id`;
- `title`;
- `title_source`;
- `opening_message`;
- creation/update timestamps; and
- last-active timestamp.

`base3_missionbay_conversation_message` contains:

- message key;
- conversation key;
- node ID;
- message ID;
- role;
- full message content;
- optional serialized payload metadata; and
- creation time.

The payload can include feedback or other message metadata supplied by the surrounding assistant integration.

### 8.3 Conversation owner representation

Database memory derives `owner_key` as a SHA-256 hash from either:

- the authenticated user ID, or
- the active session ID when no authenticated user identity is available.

This avoids storing the raw session ID in the conversation owner column. The hash is still a stable scoped identifier and should not be considered anonymous data in the presence of the underlying identity source.

### 8.4 Conversation deletion and retention

Deleting a conversation removes its conversation row and cascades deletion to its message rows.

`DatabaseMemoryAgentResource` does not implement a general time-based retention job. Optional trimming can limit per-node history when explicitly enabled, but trimming is not a substitute for an installation-wide retention policy.

A concrete deployment should define:

- maximum conversation age;
- whether users can delete their own conversations;
- whether deletion must also remove copies from logs, backups, or external providers;
- how inactive anonymous-session conversations are handled; and
- whether conversation export is required by the host application.

## 9. Conversation write timing and failure behavior

For a normal new turn, the current user message is written to the active conversation memory before later capability discovery, action policy, tool execution, or model processing can fail.

A provider or tool failure can therefore leave the user's submitted message in conversation history even when no normal assistant answer was completed.

Suggestion turns are different. Suggestion mode reads the active conversation as context but disables user and assistant message writes.

## 10. Context contributors

Context contributors produce run-local system instruction blocks. Examples can include time, user preferences, current page or application context, or other configured data.

Context is resolved at the start of a new turn. When a mutation is suspended, resume restores the frozen reviewed message set rather than re-reading current context contributors.

Context contributors can therefore cause data that was not typed directly into the current prompt to become model-visible. Each contributor should disclose and minimize the information it contributes.

## 11. User preferences

MissionBay can persist preference definitions and values in:

```text
base3_missionbay_userpref_def
base3_missionbay_userpref_value
```

Preference definitions contain configuration such as key, description, templates, value type, allowed values, default scope, sorting, and enablement.

Preference values contain:

- scope;
- stable identity;
- optional user ID;
- optional session identifier;
- preference key;
- preference value; and
- timestamps.

A preference value can itself be personal or sensitive depending on what the deployment allows administrators to define as a preference.

User preference writes are a deliberate low-risk mutation path and are not approval-bound in the current tool definition. Host-specific high-impact preferences should not be modeled as ordinary low-risk preferences if they require stronger authorization semantics.

## 12. Focus state

`FocusAgentResource` can store current focus in:

```text
base3_missionbay_focus_state
```

The table contains scope, identity, optional user/session references, resource ID, focus text, and timestamps.

Focus text can reveal what a user is currently working on. No general time-based cleanup for focus rows was identified in this source snapshot.

## 13. Knowledge memory

`KnowledgeAgentResource` and `AgentKnowledgeService` can persist agent-managed knowledge in:

```text
base3_agent_knowledge
```

The schema includes:

- memory type and subtype;
- key and status;
- title and full content;
- summary;
- tags;
- entity references;
- metadata;
- source;
- scope and scope reference;
- identity, user, and session fields;
- lock and LLM mutability/deletability flags;
- soft-deletion flag;
- priority and confidence;
- validity and expiration timestamps;
- last-accessed time;
- created/updated identity; and
- creation/update timestamps.

The model supports logical expiration and soft deletion. A general automatic physical purge of expired or soft-deleted knowledge rows was not identified in the current package.

Administrators should define whether expired knowledge is eventually deleted physically and how that deletion interacts with backups and external vector indexes.

## 14. Embedding cache

`EmbeddingCacheAgentResource` uses a database table whose default name is:

```text
base3_embedding_cache
```

The table stores:

- SHA-256 hash of normalized input plus model/salt/dimension scope;
- model;
- vector dimension;
- vector JSON;
- creation timestamp;
- last-access timestamp; and
- hit count.

The original text is not stored in this table. The embedding is still derived from that text and can encode information about it, so it should not automatically be treated as non-sensitive.

No automatic TTL or deletion job for this database-backed embedding cache was identified in the current source package.

## 15. Tool-result cache

The runtime tool-result cache is separate from the embedding cache.

`StateStoreAgentToolResultCache` stores serialized successful tool results under the state-store prefix:

```text
missionbay.agent_tool_result_cache.
```

Properties of the current cache boundary:

- caching is opt-in;
- a positive TTL is required;
- the state key includes a SHA-256 hash of the calculated cache key;
- structurally invalid cached entries are deleted when read;
- mutation calls are never served from or written to the cache; and
- the cached result can contain the full successful tool output.

A cache rule must therefore consider whether the result is safe to retain and reuse for the selected scope. Short TTL alone does not make an incorrectly scoped cache safe.

## 16. Durable approval suspensions

Human-in-the-loop review uses the `IAgentSuspensionRepository` contract. In the normal shared runtime composition, the state-store-backed implementation is provided by AssistantRuntime.

The server-owned suspension can contain:

- frozen model messages;
- pending actions;
- exact tool name and arguments;
- action fingerprints;
- public interaction requests;
- mutation commit snapshots; and
- orchestration state required for deterministic resume.

A commit snapshot should contain only stable information needed to revalidate authorization and resource state. It should not be used as a general-purpose copy of the domain object.

Documented default lifecycle values for the normal repository are:

```text
suspension TTL: 900 seconds
claim lease: 30 seconds
replay marker: 86400 seconds
```

The client receives only an opaque resume handle, not the serialized suspension.

## 17. Human-in-the-loop response data

Natural-language approval replies are themselves user content. When MissionBay must interpret such a reply, it sends the current interaction request and response text to the active chat model for structured classification.

An installation that needs to avoid model processing for approval decisions can use explicit normalized programmatic responses instead of free-form natural language.

An unclear or invalid interpretation never executes the pending mutation.

## 18. Mutation commit snapshots

For tools implementing `IAgentMutationGuardedTool`, the suspension can contain a server-owned `AgentMutationCommitSnapshot`.

A snapshot can include:

- authorization subject or tenant identity;
- required permission or scope;
- target resource identifiers;
- expected versions, revisions, hashes, or ETags; and
- domain data required to render the review.

The snapshot is not returned as client-editable suspension data. It can still contain personal or business data and is retained for the lifetime of the suspension state.

## 19. Tool lifecycle audit table

The database-backed tool listener creates and writes:

```text
base3_missionbay_tooluse
```

The table contains:

| Data | Notes |
| --- | --- |
| Turn/node/call IDs | Correlation and trace identifiers. |
| Chatbot/config identifiers | Identifies the active configured assistant context. |
| User ID and login | Direct user identifiers. |
| `prompt_text` | Can contain complete user prompt text. |
| `meta_json` | Arbitrary execution metadata. |
| Tool name/label/iteration/status | Operational metadata. |
| `arguments_json` | Full structured tool arguments. |
| `result_json` | Full structured tool result. |
| Error message/type/code | Can expose domain or provider details. |
| Timestamps | Start/update/finish timing. |

This is a high-sensitivity audit table. It is not limited to token counts or redacted analytics.

`MissionBayToolUseCleanupJob` deletes rows whose `created_at` and `updated_at` are both older than 24 hours. The job uses the BASE3 daily-window policy and runs at most once per day between 02:00 and 04:00 when the worker reaches the job. It is active by default and can be disabled through the normal `job` configuration.

## 20. AI usage table

The provider-usage listener creates and writes:

```text
base3_missionbay_ai_usage
```

The table contains:

- operation;
- source name;
- provider;
- model;
- provider request ID;
- user ID;
- user login;
- request context;
- input/output/total/cached/reasoning token counts;
- duration;
- finish reason;
- provider timestamp;
- `metrics_json`;
- `details_json`;
- `extra_json`; and
- occurrence time.

The table has no dedicated prompt or response-body column. Provider-specific JSON metadata can nevertheless contain values that are sensitive, so the shipped reporting schema marks `details_json` and `extra_json` as sensitive.

No automatic retention cleanup for the usage table was identified in this source package.

## 21. AI usage reporting

MissionBay publishes a reporting scope:

```text
missionbay_ai
```

The source definition exposes `base3_missionbay_ai_usage` through ResourceFoundation reporting contracts. Shipped report definitions include metrics, models, operations, requests, token timelines, and user-based usage views.

The source definition marks these fields as sensitive:

```text
user_id
user_login
details_json
extra_json
```

A compatible reporting frontend must not assume that a `sensitive` metadata flag by itself enforces authorization. The host reporting boundary remains responsible for who can access these reports.

## 22. Provider-bound model processing

MissionBay can send model requests to the configured provider endpoint.

Depending on the active agent stage, a request can contain:

- system instructions;
- context-contributor blocks;
- conversation history;
- current user message;
- selected tool schemas;
- tool results or observations;
- structured-response schema; and
- orchestration instructions.

The exact data sent depends on the concrete model adapter and request type.

For every external provider, the installation should document:

- provider and product;
- endpoint;
- model;
- processing region;
- contractual processor role;
- subprocessors;
- provider-side request logging;
- provider retention;
- training/product-improvement use;
- deletion controls; and
- international transfer mechanisms where applicable.

MissionBay cannot determine these provider-specific facts from the generic provider contract.

## 23. Built-in configured AI provider families

The current configured service-driver layer includes OpenAI, Mistral, and OpenAI-compatible drivers across different service types.

The source also contains discoverable direct resource adapters for additional provider APIs such as Anthropic, DeepSeek, Fireworks, Gemini, Grok/xAI, Groq, OpenRouter, and Perplexity.

Those source classes create a possible external network boundary only when they are actually configured and materialized.

An installation inventory should list only the providers that are enabled and reachable in the final runtime composition.

## 24. Image generation

Configured image-generation models send prompts and generation options to the selected provider.

Possible returned data includes:

- base64 image content;
- provider-hosted image URLs;
- revised prompt text;
- provider metadata; and
- usage metrics.

MissionBay does not automatically persist every returned image into a durable media repository. If the consumer needs durable storage, access control, deletion, or provenance, those responsibilities belong to the consuming media/storage boundary.

Provider-hosted URLs may expire or may remain under provider control. They should not be treated as durable application-owned storage.

## 25. Provider-backed web search

Configured web-search services send the search query and search options to the selected provider.

Options can include domain allow/deny lists and external web access behavior. Search terms can reveal user interests, internal project names, personal data, or confidential research topics.

Search-provider retention and downstream web crawling are provider-specific and must be documented for the chosen service.

## 26. Speech-to-text

STT sends audio to the configured OpenAI or Mistral service and receives transcription results.

Realtime STT can establish a provider session and receive a client/session credential. The configured service test intentionally reports that such a token was received without displaying the value.

MissionBay does not define a dedicated persistent audio table, but audio can still exist:

- in request memory;
- in temporary client/server transport buffers;
- at the external provider;
- in host upload storage before MissionBay receives it; or
- in logs if an integration incorrectly logs raw request bodies.

The host must define audio retention independently from MissionBay's lack of a database audio table.

## 27. Text-to-speech

TTS sends text and speech options to the configured provider and returns audio data.

Text submitted for speech generation can contain personal or confidential content even if the resulting audio is not stored by MissionBay.

The consuming application decides whether generated audio is streamed, cached, downloaded, or stored persistently.

## 28. Parser services

MissionBay includes configured parser drivers for Docling and Unstructured-compatible services.

For file parsing, the parser request can upload the full file bytes to the configured parser endpoint. API keys or proxy tokens are added through the configured connection/runtime options.

For stream parsing, MissionBay writes the stream to a temporary file under `sys_get_temp_dir()` and removes the temporary file in a `finally` path after the parser operation.

Parser metadata can include the local source path in the `location` field. Local filesystem paths should be treated as technical metadata and should not be exposed to untrusted clients or external providers unless required.

Provider-side parser retention, extracted text retention, and model use are external to MissionBay and must be documented per service.

## 29. Parser errors and previews

Parser provider failures can generate exceptions containing bounded excerpts or extracted provider error details. If those exceptions are written to a logger or shown in an administration interface, provider-returned content can become part of operational logs.

Parser test services return bounded previews to the administrator. Tests should use synthetic or non-sensitive content when the goal is only connectivity verification.

## 30. Retrieval and vector-store processing

MissionBay separates:

```text
logical retrieval collection
  -> collection definition
  -> physical vector-store collection
```

The collection definition owns the payload schema, validation, filter surface, and projection into agent context.

A vector store such as Qdrant can persist:

- vectors;
- chunk/document identifiers;
- text or context payloads;
- metadata;
- authorization fields; and
- indexing bookkeeping.

The exact payload depends on the active collection definition.

## 31. Mandatory retrieval authorization

Agent-provided retrieval filters are not authorization.

Host-specific mandatory ACL, tenant, ownership, source-kind, or other restrictions must be injected server-side by the collection definition, filter providers, or backend integration.

The model should only be allowed to choose among filter fields explicitly approved by the collection definition.

This is particularly important because vector payloads may contain content from multiple users, groups, tenants, or access scopes.

## 32. Stored vector payload versus model context

A retrieval backend may need technical fields for indexing, deletion, authorization, or bookkeeping. Those fields do not automatically belong in the model context.

The collection definition must project only the fields needed for the answer.

Data minimization should therefore happen before model context is built, not after a complete technical payload has already been sent to the model provider.

## 33. Vector deletion and source lifecycle

When a source document is deleted, access rights change, or retention expires, the owning indexing integration should update or delete the corresponding vector-store content.

MissionBay's generic retrieval layer cannot infer the lifecycle of every host data source.

A deployment should define how source deletion propagates to:

- physical vector points;
- stored payload;
- embedding caches;
- retrieval indexes;
- backups; and
- any generated summaries or knowledge entries.

## 34. Inbound MCP data processing

The inbound MCP server accepts JSON-RPC over POST and can expose configured tools, resources, resource templates, and prompts.

Request data can include:

- profile ID in the query string;
- authorization header;
- MCP protocol headers;
- HMAC timestamp, nonce, and signature;
- tool arguments;
- prompt arguments;
- resource URIs; and
- JSON-RPC IDs.

The host web server or reverse proxy can log the endpoint URL and query string. The profile ID is therefore potentially present in access logs.

Authorization headers and HMAC signatures should never be logged by generic HTTP infrastructure.

## 35. Inbound MCP security controls

The current MCP server implements:

- POST-only transport;
- supported protocol-version validation;
- same-host `Origin` checking;
- `Accept` validation;
- request-body size checking with a 1 MiB maximum based on `Content-Length`;
- fixed Bearer comparison with `hash_equals`;
- optional personal Bearer or HMAC credentials through the credential boundary;
- per-profile credential grants;
- profile-limited tool exposure; and
- audit logging for MCP activity.

The current server version does not implement rate limiting or OAuth client registration. Those controls, if required, must be provided by the host, reverse proxy, credential service, or a future deliberate protocol implementation.

## 36. Inbound MCP approval ownership

For inbound MCP, the MCP client or host is responsible for reviewing tool annotations and obtaining user approval before `tools/call`.

MissionBay does not run a second local pending-confirmation flow for these calls.

This means the security model of an MCP deployment includes the external client's approval behavior. A client that ignores safety annotations can submit a write-capable tool call as long as it has valid endpoint/profile authorization.

Server-side domain authorization inside the tool remains mandatory.

## 37. Outbound MCP data processing

`McpClientAgentResource` can send data to a remote MCP server. Depending on configuration, this can include:

- initialize metadata;
- tool calls and arguments;
- resource URIs;
- prompt names and arguments;
- session IDs;
- protocol version;
- custom headers;
- Bearer/API-key/basic credentials; and
- KeyHarbor-compatible HMAC headers.

Remote resource content, prompts, server instructions, and tool results can then become model context or tool observations.

The remote MCP server is an external processing boundary and must be included in the installation's provider/integration inventory.

## 38. Outbound MCP secret handling

The MCP client resource supports config-value definitions for endpoint, token, HMAC secret, username, headers, and related fields.

For literal secrets managed through component presets, the administration UI uses a configured-secret marker and does not return the actual secret in normal preset JSON or client diagnostics.

The runtime still resolves the actual secret in server memory before sending the request.

## 39. Outbound MCP transport protections

The current Streamable HTTP client:

- requires an absolute HTTP/HTTPS endpoint;
- rejects embedded URL credentials;
- rejects URL fragments;
- validates headers for control characters;
- prevents custom headers from overriding protocol and authentication headers;
- verifies TLS by default;
- does not follow redirects;
- enforces response-byte, page, and item limits;
- applies connection and request timeouts; and
- redacts configured endpoint/credential/header values from propagated errors.

`verify_tls` is configurable. Disabling it weakens transport authenticity and should be treated as an explicit deployment exception.

## 40. Remote MCP annotations and trust

Remote tool annotations are provided by the remote server. MissionBay maps them into local tool metadata, but cannot independently prove that the remote server classified the operation correctly.

A remote tool that changes remote data also cannot automatically participate in a local domain-version commit guard unless the integration provides a corresponding trustworthy mechanism.

Remote MCP should therefore be configured with least-privilege credentials and appropriately narrow tool allowlists.

## 41. Direct web fetch tool

`webfetchtextagenttool` performs public HTTP/HTTPS fetches.

The implementation includes SSRF-oriented checks and limits:

- only HTTP/HTTPS;
- no URL user/password;
- localhost blocked;
- private/reserved IPv4 blocked;
- IPv6 loopback, ULA, and link-local ranges blocked;
- resolved IPv4 addresses checked;
- effective URL rechecked after redirect;
- TLS verification enabled;
- maximum 5 redirects;
- connection timeout 5 seconds;
- overall timeout 12 seconds;
- response-body cap 256 KiB; and
- returned text cap 12,000 characters.

The tool returns extracted text and metadata, not raw HTML.

Even with these controls, outbound web access should be enabled only when appropriate for the agent's purpose. Public URLs can still contain personal or copyrighted material, tracking endpoints, or content designed to manipulate an AI agent.

## 42. Generic HTTP nodes

`httpgetnode` and `httprequestnode` can create arbitrary configured HTTP requests inside AgentFlow.

Their use can transmit flow data to external endpoints. A flow author must treat endpoint configuration, request headers, body content, and response handling as an explicit integration boundary.

Generic HTTP nodes should not receive secrets or personal data merely because the data is available in flow context.

## 43. DeepL translation node

`deepltranslatenode` can send text to the DeepL API endpoint used by the node implementation.

Text sent for translation can contain personal or confidential information. The node is opt-in and is separate from the generic configured-service provider architecture.

Deployments using it should document the DeepL account, endpoint, region, and provider retention terms applicable to that use.

## 44. Telegram tools

`telegramsendmessage` and `telegramagenttool` can send message text and chat identifiers to the Telegram Bot API.

Message content can contain personal data. Bot tokens are credentials and must be protected. Telegram processing is an external communication boundary that exists only when these components are used.

## 45. Weather and currency tools

The built-in weather tool uses Open-Meteo geocoding and weather endpoints. The location string supplied by the user or model is sent to the geocoding service.

The currency conversion tool uses the Frankfurter API with amount and currency codes.

These tools are read-only, but their queries still leave the local infrastructure and can expose user interests or transaction context if used with sensitive values.

## 46. Direct provider resources

The current source also contains direct provider resources for several AI APIs. Default endpoints visible in the source include providers such as Anthropic, DeepSeek, Fireworks, Gemini, Grok/xAI, Groq, OpenRouter, Perplexity, OpenAI, and Mistral.

These resources should be included in the deployment inventory only when they are configured and materialized. The mere presence of source code does not mean data is sent to those providers.

## 47. Scheduled agents

`ScheduledAgentRunnerJob` can execute configured agents in a worker process without an interactive user request.

Scheduled execution can send configured system/user prompts and context to providers and can invoke configured tools.

The worker context includes a channel such as:

```text
scheduled-agent:<agent-id>
```

and can include the scheduled agent settings in run context.

Operational implications:

- the worker identity and permissions must be defined;
- scheduled prompts should not contain unnecessary secrets or personal data;
- tools should not assume that an interactive human is present;
- approval-requiring actions need a deliberate asynchronous review design rather than bypassing approval; and
- worker logs can capture returned exception text.

## 48. Administration and diagnostic surfaces

MissionBay includes administration displays for:

- agents;
- component presets;
- component tests;
- effective composition;
- orchestrator profiles;
- tool profiles;
- memory profiles;
- context profiles;
- provider connections;
- LLM, embedding, image, search, vector, parser, STT, and TTS service presets;
- retrieval collection mappings;
- retrieval search;
- vector points;
- tool logs;
- knowledge memory;
- user preference definitions; and
- other diagnostics.

These displays can expose configuration and production data. They must be treated as privileged administration interfaces.

## 49. Administration authorization boundary

MissionBay display classes do not themselves form a general user-authentication or role-authorization system.

The host administration shell, routing layer, and access-control composition must ensure that only authorized users can:

- read settings;
- view logs;
- inspect retrieval data;
- run service tests;
- change connections or secrets;
- modify agents and profiles;
- delete vector collections; or
- edit persistent knowledge and preference definitions.

An `IAdminDisplay`-style component should not be interpreted as automatic permission enforcement.

## 50. CSRF and browser request integrity

No MissionBay-local CSRF token mechanism was found in the current source package.

Several administration displays accept state-changing requests. A browser-hosted deployment should therefore verify whether the surrounding host administration framework supplies CSRF protection or an equivalent same-origin/request-integrity mechanism.

If no host-level protection exists, the responsible administration boundary should be fixed there rather than by adding unrelated per-feature routing workarounds.

## 51. Browser response sensitivity

Administration JSON responses can contain:

- configuration records;
- provider endpoint information;
- diagnostics;
- bounded provider-test previews;
- retrieval results;
- vector payloads;
- tool audit records;
- knowledge content;
- user preference definitions; and
- in the current inbound MCP tool-profile record response, the fixed Bearer token.

Production reverse proxies and debugging middleware should avoid recording privileged response bodies.

## 52. Logging

MissionBay uses the BASE3 logger in multiple places for operational diagnostics. Depending on the code path, log messages can contain:

- provider/model/source identifiers;
- service or preset IDs;
- parser errors;
- MCP profile IDs and rejection codes;
- request hosts or `Origin` values for rejected MCP requests;
- tool cache errors;
- generic tool-provided log content; and
- exception messages.

Some included nodes or tools are explicitly designed to log supplied values. Those components can intentionally place flow or agent data in logs.

The active logger backend, log location, access controls, rotation, and retention are BASE3 deployment choices and must be documented by the installation.

## 53. MCP security logging

`McpHttpGuard` logs certain rejected requests such as unsupported methods/headers, foreign origins, oversized content lengths, and unsupported protocol versions.

`McpProfileAuthorizer` logs profile authorization failure categories and the profile ID. Its failure logging does not intentionally include the credential token.

Generic web-server logs remain a separate concern and may capture URLs, status codes, client addresses, and other request metadata.

## 54. Provider usage failure logging

If AI usage persistence fails, the usage listener can log provider/model/source and exception details through the active logger.

Database or provider exceptions can expose operational details. Production logging should be access-controlled and should avoid unnecessary inclusion of request bodies or credentials at surrounding infrastructure layers.

## 55. Persistent database inventory

The following table summarizes the main MissionBay-owned persistent tables visible in the current source.

| Table | Main content | Typical sensitivity | Automatic time cleanup found? |
| --- | --- | --- | --- |
| `base3_missionbay_conversation` | Conversation metadata and optional opening message. | Personal/conversational. | No. |
| `base3_missionbay_conversation_message` | Full user/assistant messages and extra payload metadata. | High. | No. |
| `base3_missionbay_tooluse` | Prompt, tool args/results/errors, user identity, trace data. | Very high. | Yes, 24-hour cleanup through `MissionBayToolUseCleanupJob`. |
| `base3_missionbay_ai_usage` | User identity, provider/model, token usage, provider metadata. | Medium to high. | No. |
| `base3_missionbay_userpref_def` | Preference definitions and templates. | Usually configuration, potentially sensitive. | No. |
| `base3_missionbay_userpref_value` | User/session preference values. | Personal. | No general time cleanup found. |
| `base3_missionbay_focus_state` | User/session focus text. | Personal/contextual. | No. |
| `base3_agent_knowledge` | Persistent knowledge content and identity metadata. | High. | Logical expiration/soft deletion, no general physical purge found. |
| `base3_embedding_cache` | Embedding vectors and hashes derived from input text. | Derived potentially sensitive data. | No. |

Vector-store data such as Qdrant collections is stored outside these SQL tables and has its own lifecycle.

## 56. Table creation model

Several MissionBay features use local `CREATE TABLE IF NOT EXISTS` calls when the feature is first used. This is private technical bootstrapping for the owning feature.

A table's existence therefore depends on runtime use. The absence of a table does not mean the corresponding source class is missing, only that the feature may not have been activated.

## 57. Retention summary

Current source behavior does not provide one global MissionBay retention policy.

Relevant lifecycles are split across features:

- session memory follows session lifetime;
- database conversations remain until explicit deletion or host cleanup;
- tool-result cache entries have explicit TTLs;
- normal durable suspensions have a short TTL in the shared runtime repository;
- replay markers outlive the suspension for the configured replay period;
- tool audit rows are removed by `MissionBayToolUseCleanupJob` after they have remained unchanged for 24 hours;
- AI usage rows have no automatic cleanup identified here;
- user preference values remain until changed/unset or host cleanup;
- focus rows have no automatic cleanup identified here;
- knowledge supports expiration/soft deletion but no general physical purge identified here;
- embedding-cache rows have no automatic TTL cleanup identified here;
- vector-store points remain until the indexing/source lifecycle removes them; and
- external provider copies follow the provider's own retention policy.

A production deployment should define explicit retention per category rather than applying one arbitrary duration to all data.

## 58. Deletion propagation

Deleting one MissionBay object does not automatically prove that all derived copies are deleted.

Examples:

- deleting a database conversation cascades its message rows but does not delete tool audit records that may contain the same prompt;
- deleting source content does not automatically prove that every vector point, embedding cache entry, model-provider log, or knowledge summary is gone;
- deleting a connection removes configuration but not provider-side request history;
- deleting a tool profile does not remove external client logs or credential records managed elsewhere; and
- deleting a user from the host does not automatically purge MissionBay usage/tool rows unless the host provides that lifecycle.

A complete deletion process must map all relevant data copies.

## 59. Backups

Database, settings, state, and session backends can be included in infrastructure backups.

Backups may retain MissionBay data after it has been deleted from the live system. The backup lifecycle, encryption, restore permissions, and expiry periods are deployment responsibilities.

The same applies to external Qdrant backups, reverse-proxy logs, provider account logs, and object-storage snapshots used by host extensions.

## 60. Data minimization for model calls

A production agent should be composed so that the model receives only what it needs.

Recommended technical boundaries already present in MissionBay include:

- tool profiles that bound the tool catalog;
- capability selection that narrows model-visible tools;
- context contributors that explicitly decide what system context is added;
- memory profiles that select one conversation history backend;
- retrieval collection definitions that project only approved payload fields;
- server-side mandatory retrieval filters;
- context compaction stages; and
- component presets that keep provider and tool configuration explicit.

These mechanisms do not automatically know which business fields are sensitive. Domain-specific minimization still belongs in the host/tool/retrieval implementation that owns the data.

## 61. Do not send technical authorization data to a model unnecessarily

Internal user IDs, session identifiers, permission IDs, ACL rows, tenant keys, backend collection names, secret paths, and similar technical values should not be included in model context unless the model actually requires them for the task.

Authorization decisions should be executed in server-side code. The model should request a semantic action, while the responsible tool resolves and validates the concrete authorized domain operation.

## 62. Retrieval minimization

Retrieval should return only the smallest set of chunks and projected fields needed for the answer.

Technical payload can stay in the backend for:

- filtering;
- indexing;
- deletion;
- ordering;
- ACL checks; and
- provenance.

Only the collection definition's agent projection should become model context.

## 63. Tool minimization

A tool should return the minimum structured result required for the agent's next decision.

Returning complete database rows, full user objects, unrestricted reports, or internal error traces increases the chance that data is copied into:

- model context;
- conversation replies;
- tool audit logs;
- cached tool results; and
- external provider requests.

The responsible tool boundary should reduce the result before it reaches the agent harness.

## 64. Approval data minimization

A mutation review should show the user enough resolved business information to make an informed decision, but the durable commit snapshot should contain only stable data needed for later revalidation.

Avoid storing unrelated domain objects or full repository state in the suspension merely because it is available at review time.

## 65. Scheduled execution minimization

Scheduled agents can run without an active interactive user. Their prompts and tool configuration should be reviewed as automation configuration, not treated as ordinary ephemeral chat input.

Avoid storing credentials directly in scheduled prompts or agent configuration. Use connection/config-value/credential boundaries for secrets.

## 66. Security boundary between model and tools

A model output is not authorization evidence.

MissionBay separates:

```text
model decision
  -> action policy
  -> optional human review
  -> exact action fingerprint
  -> commit-time validation
  -> tool execution
```

Tools that own protected domain data remain responsible for enforcing their own permissions. A model deciding to call a tool cannot elevate the current user.

## 67. Security boundary for retrieval

A model-provided retrieval filter is not an ACL. Mandatory filters must be injected outside the model's control.

A host retrieval integration that cannot enforce access restrictions at the retrieval boundary should be fixed there rather than adding a second post-retrieval filtering path that still allows unauthorized content to reach the model.

## 68. Security boundary for outbound providers

Provider authentication proves that MissionBay can access the configured provider. It does not prove that the provider is allowed to receive a particular business dataset.

The deployment must separately decide which data categories may be sent to each configured endpoint.

## 69. Security boundary for administration

MissionBay administration displays can modify high-impact configuration and inspect sensitive data. The host must provide:

- authentication;
- appropriate administrator authorization;
- request-integrity controls;
- secure transport;
- safe response caching behavior; and
- access logging appropriate for privileged operations.

MissionBay's display discovery mechanism is not a substitute for those controls.

## 70. Production deployment checklist

Before enabling MissionBay in production, document at least the following:

### Providers and external endpoints

- Which LLM providers are enabled?
- Which embedding providers are enabled?
- Which image-generation providers are enabled?
- Which web-search providers are enabled?
- Which STT/TTS providers are enabled?
- Which parser services are enabled?
- Which Qdrant or other vector endpoints are enabled?
- Which remote MCP servers are enabled?
- Which direct HTTP/Telegram/DeepL/weather/currency tools are enabled?
- Which regions and subprocessors apply to each service?
- Does each provider store prompts, files, audio, or results?
- Are data used for provider training or product improvement?

### Credentials

- Where are connection secrets stored?
- Are production secrets fixed settings values, environment variables, configuration references, or files?
- Who can read the settings backend?
- Are inbound MCP fixed tokens enabled?
- Are personal MCP credentials available instead?
- Are secret-bearing administration responses protected from logs and caches?
- How are secrets rotated and revoked?

### Conversation and user data

- Is session or database conversation memory used?
- What is the conversation retention period?
- How are user deletions propagated?
- How are anonymous session conversations handled?
- Are preference, focus, or knowledge features enabled?
- What retention applies to each one?

### Auditing and logs

- Is `base3_missionbay_tooluse` enabled in production?
- What is its retention period?
- Who may inspect prompts, tool arguments, and results?
- Is `base3_missionbay_ai_usage` retained, and for how long?
- Are usage reports restricted to appropriate administrators?
- What BASE3 logger backend is active?
- Do reverse proxies or APM tools capture request/response bodies?

### Retrieval and indexing

- Which source data is indexed?
- Which mandatory access filters are enforced server-side?
- Which payload fields are projected to the model?
- How are permission changes propagated to the vector store?
- How are deleted documents removed from vectors and derived caches?
- Are embedding vectors treated as potentially sensitive derived data?

### MCP

- Which inbound profiles exist?
- Which tools does each profile expose?
- Is fixed Bearer or personal credential access enabled?
- Is host-level rate limiting required?
- Which outbound MCP endpoints receive local data?
- Are outbound tool allowlists narrow enough?
- Is TLS verification enabled?

### Administration

- Which host route protects MissionBay admin displays?
- Which roles can change providers, agents, tools, memory, retrieval, and MCP settings?
- Does the host provide CSRF or an equivalent request-integrity mechanism?
- Are privileged JSON responses excluded from proxy/browser caching?

### Retention and deletion

- Who owns cleanup for conversation rows?
- Who owns cleanup for tool audit rows?
- Who owns cleanup for AI usage rows?
- Who owns cleanup for focus, preferences, knowledge, and embedding cache?
- How are external provider copies deleted?
- How are vector points deleted when source data is removed?
- How long do backups retain deleted MissionBay data?

### Scheduled agents

- Which agents can run automatically?
- Under which worker identity and permissions?
- Can scheduled agents invoke write-capable tools?
- How are approval-requiring actions handled?
- Which logs contain scheduled-agent errors or results?

## 71. Installation-specific facts that cannot be inferred from MissionBay source

The following must be documented by the concrete deployment because MissionBay source alone cannot determine them:

- legal roles and legal bases;
- controller/processor identities;
- actual provider contracts;
- actual hosting regions;
- actual provider retention;
- actual model training settings;
- actual network egress rules;
- actual settings/state/session/database backends;
- actual log aggregation services;
- actual reverse-proxy logging;
- actual administrator roles;
- actual conversation retention;
- actual backup retention;
- actual user export and deletion workflows; and
- actual host-specific retrieval ACL implementation.

## 72. Summary

MissionBay is not only a text-generation wrapper. It is an orchestration platform that can combine conversation history, models, retrieval, tools, persistent knowledge, speech, parsing, MCP, background execution, and administration.

The main privacy implications are therefore distributed across several explicit architecture boundaries:

```text
settings and secrets
conversation memory
context contributors
provider requests
retrieval and vector storage
tool execution
human approval suspensions
MCP
persistent knowledge/preferences/focus
tool and usage auditing
administration
external network integrations
```

A secure deployment should configure, authorize, minimize, retain, and delete data at the boundary that owns it. MissionBay already separates these concerns technically. The deployment should preserve those boundaries rather than copying the same data or responsibility into parallel fallback paths.
