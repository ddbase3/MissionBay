# MissionBay Remote MCP Client Agent Resource

## Purpose

`McpClientAgentResource` connects one remote Model Context Protocol server to the existing MissionBay agent component preset and tool profile system.

It supports these MCP server primitives:

```text
tools
resources
resource templates
prompts
server instructions
```

The implementation uses the MCP Streamable HTTP request/response transport. It does not introduce another profile type, another component registry, or another credential store.

The architecture remains:

```text
Agent Component Preset
  -> McpClientAgentResource
     -> IMcpClientFactory
        -> IMcpClient
           -> IMcpTransport
              -> McpStreamableHttpTransport

Tool Profile
  -> existing preset id
  -> normal internal-agent and/or MCP exposure
```

The configured preset owns one remote server connection. The same materialized resource instance exposes all of its tool, resource, prompt, and optional context capabilities.

## Requirements

The runtime requires:

```text
PHP 8.1 or newer
PHP cURL extension
outbound HTTP or HTTPS access to the MCP endpoint
```

TLS certificate and host verification are enabled by default.

## Resource type

The discoverable resource implementation name is:

```text
mcpclientagentresource
```

It implements:

```text
MissionBay\Api\IAgentTool
MissionBay\Api\IConfirmableAgentTool
MissionBay\Api\IAgentResourceProvider
MissionBay\Api\IAgentPromptProvider
AssistantFoundation\Api\IAgentContextContributor
Base3\Api\ISchemaProvider
Base3\Api\IOutputSchemaProvider
```

The Component Preset administration derives the effective capabilities from these contracts. A normal preset therefore exposes:

```text
tool
context
```

The `context` capability is used for explicitly configured server instructions, resources, or prompts. Resource and prompt provider contracts are also retained when the preset is materialized for an MCP profile.

## Create a Component Preset

Open **Agent Component Presets**, create a preset, and select:

```text
Resource type: mcpclientagentresource
```

The minimum connection fields are:

```text
endpoint
Auth type
token, unless Auth type is none
```

A bearer-token example:

```json
{
	"id": "github-mcp",
	"label": "GitHub MCP",
	"type": "mcpclientagentresource",
	"enabled": true,
	"capabilities": [
		"context",
		"tool"
	],
	"config": {
		"endpoint": "https://api.githubcopilot.com/mcp/",
		"auth_type": "bearer",
		"token": "github_pat_...",
		"headers": {
			"X-MCP-Readonly": "true",
			"X-MCP-Toolsets": "repos,issues,pull_requests"
		}
	},
	"docks": {},
	"meta": {}
}
```

The administration UI renders `token` as a password field. Existing literal tokens are represented by an internal configured-secret marker in responses. Saving that unchanged marker preserves the stored value; the actual token is not returned in preset JSON, logs, or client diagnostics.

No second secret record is created. The secret remains part of the existing `agent-component-preset` dataset.

## Config value definitions

Connection fields are resolved by `IAgentConfigValueResolver`. Generic BASE3 config value definitions are therefore supported.

Environment-variable example:

```json
{
	"token": {
		"mode": "env",
		"name": "GITHUB_MCP_PAT"
	}
}
```

File example:

```json
{
	"token": {
		"mode": "file",
		"path": "/run/secrets/github_mcp_pat"
	}
}
```

Additional header values may also use config value definitions:

```json
{
	"headers": {
		"X-Customer-Id": {
			"mode": "env",
			"name": "MCP_CUSTOMER_ID"
		}
	}
}
```

The following fields accept resolved config values:

```text
endpoint
auth_type
token
username
auth_header_name
headers and each header value
label
protocol_version
connect_timeout
request_timeout
max_response_bytes
max_pages
max_items
verify_tls
tool_allowlist
tool_denylist
include_server_instructions
context_resources
context_prompts
context_priority
```

## Authentication

Supported authentication types:

| `auth_type` | Resulting request authentication |
|---|---|
| `none` | No authentication header. |
| `bearer` | `Authorization: Bearer <token>` |
| `api_key` | `<auth_header_name>: <token>` |
| `basic` | `Authorization: Basic <base64(username:token)>` |

Rules:

- `token` is required for `bearer`, `api_key`, and `basic`.
- `username` is required for `basic`.
- `auth_header_name` defaults to `X-API-Key`.
- An endpoint must be an absolute HTTP or HTTPS URL.
- Embedded URL credentials are rejected.
- URL fragments are rejected because they are not part of the HTTP request target.
- Custom headers cannot override protocol-controlled headers, `Authorization`, or the configured MCP session headers.
- With `api_key`, the configured API-key header must not also appear in `headers` under any casing.
- Header names, custom header values, and bearer/API-key tokens are validated for HTTP control characters before the first request.

OAuth browser authorization is not represented as another `auth_type`. A correct OAuth implementation requires authorization-server discovery, PKCE, callback handling, and persistent token lifecycle management and is outside this resource version.

## Complete configuration reference

| Field | Default | Description |
|---|---:|---|
| `endpoint` | required | Absolute remote Streamable HTTP MCP endpoint. |
| `auth_type` | `bearer` | `none`, `bearer`, `api_key`, or `basic`. |
| `token` | empty | Bearer token, API key, or basic-auth password. Required unless authentication is `none`. |
| `username` | empty | Username for basic authentication. |
| `auth_header_name` | `X-API-Key` | Header used by `api_key`. |
| `headers` | `{}` | Additional validated HTTP headers. |
| `label` | preset resource id | Display label used in confirmation reviews. |
| `protocol_version` | `auto` | `auto`, `2025-11-25`, `2025-06-18`, or `2025-03-26`. |
| `connect_timeout` | `10` | Connection timeout in seconds, from 1 to 120. |
| `request_timeout` | `60` | Per-request timeout in seconds, from 1 to 600. |
| `max_response_bytes` | `2097152` | Maximum response body size. |
| `max_pages` | `20` | Maximum pages read from one list primitive. |
| `max_items` | `500` | Maximum total items read from one list primitive. |
| `verify_tls` | `true` | Verify TLS certificate and host name. |
| `tool_allowlist` | `[]` | Optional remote names or shell-style patterns that may be exposed. |
| `tool_denylist` | `[]` | Remote names or patterns that must not be exposed. Deny wins. |
| `include_server_instructions` | `true` | Add non-empty initialize instructions to the agent context. |
| `context_resources` | `[]` | Concrete remote resource URIs loaded into context. |
| `context_prompts` | `[]` | Remote prompt names, optionally with arguments, loaded into context. |
| `context_priority` | `35` | Context block priority from 0 to 1000. Lower values are loaded first. |

`tool_allowlist` and `tool_denylist` evaluate the remote MCP tool name.

## MCP lifecycle and transport

The client performs this sequence:

```text
initialize
  -> validate negotiated protocol version
  -> retain server capabilities, serverInfo, and instructions
  -> retain Mcp-Session-Id when supplied
  -> notifications/initialized
  -> primitive calls
```

`auto` offers supported versions in this order:

```text
2025-11-25
2025-06-18
2025-03-26
```

After initialization, requests include the negotiated `MCP-Protocol-Version`. When the server supplies `Mcp-Session-Id`, subsequent requests include that session id.

If an established HTTP session receives HTTP `404`, the client discards that session, initializes once again, and retries the interrupted operation exactly once. Other failures are not redirected into alternate transports or alternate client modes.

Each POST response may be:

```text
application/json
text/event-stream
```

The SSE parser accepts the JSON-RPC response carried by the POST event stream. This is not a persistent GET-based SSE channel.

The transport:

- sends POST requests only;
- does not follow redirects;
- requests `Accept-Encoding: identity` so the response-byte limit applies to the received body;
- enforces response size, timeout, page, and item limits;
- redacts endpoint, token, basic-auth credentials, and configured header values from propagated errors.

## Remote tools

`tools/list` definitions are mapped as follows:

```text
remote name        -> function name unchanged
title              -> label
description        -> function description
inputSchema         -> function parameters
outputSchema        -> MissionBay output schema and MCP outputSchema
annotations         -> MissionBay read/mutation metadata
```

The MCP resource does not add its own prefix or rewrite remote names. There is no MCP-specific `namespace` setting. External tool naming remains owned by the existing MissionBay tool attachment and `ConfiguredAgentToolResource` path.

### Tool filtering

Filtering is based only on remote MCP names:

```text
tool_denylist
  -> tool_allowlist
```

An empty allowlist permits all remote names that are not denied. Filtering never creates or removes the MissionBay preset prefix.

### MissionBay action metadata

The MCP adapter maps every accepted remote tool into the normal MissionBay tool-definition contract. It does not introduce a separate mutation policy.

```text
all four hints present and consistent
readOnlyHint=true
  -> mutation=false
  -> requiresApproval=false

readOnlyHint=false
  -> mutation=true
  -> requiresApproval=true
  -> commitGuardRequired=false

any hint missing, non-boolean, or contradictory
  -> conservative MCP defaults
  -> mutation=true
  -> requiresApproval=true
  -> risk=high
```

The four MCP safety hints are preserved and evaluated directly:

```text
readOnlyHint
  default false

destructiveHint
  default true
  meaningful for non-read-only tools

idempotentHint
  default false
  meaningful for non-read-only tools

openWorldHint
  default true
```

No tool-name regex, description heuristic, or preset-maintained read-only list is used. Missing or contradictory safety metadata never hides a tool; it keeps the tool visible behind a high-risk MissionBay approval request. A fully annotated additive, idempotent, closed-world mutation uses medium risk. MCP annotations remain remote-server declarations rather than independently verified guarantees.

The client does not claim an `IAgentMutationGuardedTool` commit guard. A generic remote MCP connection cannot validate an external domain version or external authorization again immediately before the change. A declined action produces no remote `tools/call` request. Servers that support read-only operation should still be configured read-only whenever possible.

### Results

Remote tool results are projected onto the existing MissionBay tool return contract:

```text
structuredContent present  -> structured value
text-only content           -> plain text
non-text content            -> original MCP result envelope
isError=true                -> existing MissionBay tool exception boundary
```

Only the protocol-level boolean `isError=true` classifies a `tools/call` result as failed. MissionBay does not infer failure from text such as `Error`, `not found`, or `not indexed`. This allows remote services to return domain limitations as normal evidence when they intentionally leave `isError` unset or false.

When structured content exists, MissionBay validates that value against the remote `outputSchema`. Image, audio, resource-link, and embedded-resource blocks remain available when no structured result replaces them.

For `isError=true`, remote text content is propagated as the tool failure message. The failure remains available to the model as an observation, and the orchestration instruction requires tool-reported limitations, missing data, unavailable indexing, unsupported scope, and uncertainty to be explained in the final response instead of silently omitted.

## Resources and resource templates

The resource delegates:

```text
resources/list
resources/templates/list
resources/read
```

Concrete resource metadata and binary `blob` content are retained. Template URIs are matched only against definitions returned by the remote server.

Duplicate resource URIs or duplicate templates from one remote server are errors. Duplicate resource URIs across materialized profile providers are also rejected at the MCP catalog boundary.

Resources remain application-controlled. They are not converted into synthetic tools.

### Add selected resources to context

```json
{
	"context_resources": [
		"repo://current/readme",
		"schema://project/database"
	]
}
```

Text resources are converted into instruction blocks. Binary resources are represented by a short omission notice; their bytes are not injected into the model prompt.

## Prompts

The resource delegates:

```text
prompts/list
prompts/get
```

Prompt names are exposed unchanged:

```text
review_pull_request
```

Prompt arguments and non-text content blocks are retained for MCP clients.

Prompts remain user/application-selected. They are not converted into synthetic tools.

### Add selected prompts to context

A prompt without arguments:

```json
{
	"context_prompts": [
		"project_overview"
	]
}
```

A prompt with arguments:

```json
{
	"context_prompts": [
		{
			"name": "review_pull_request",
			"arguments": {
				"repository": "org/project",
				"pull_request": "42"
			}
		}
	]
}
```

Context configuration should use the original remote prompt name. Returned messages are converted into role-prefixed text blocks. Non-text message content is represented by an explicit omission marker.

## Server instructions

When `include_server_instructions=true`, the non-empty `instructions` value returned by `initialize` becomes an `AgentInstructionBlock`.

This is the only automatic remote context contribution. Resource contents and prompt results are loaded only when explicitly listed in the preset.

## Add the preset to a Tool Profile

After saving the Component Preset, add its preset id to an existing Tool Profile.

A Tool Profile may be enabled for:

```text
internal MissionBay agents
MissionBay MCP exposure
both
```

For an internal agent, select that Tool Profile in the normal agent configuration. The tool profile resolver preserves the preset's `tool` and `context` capabilities and attaches one shared configured resource instance to the required docks.

For MCP exposure, add the preset to an MCP-enabled Tool Profile and call MissionBay's existing inbound MCP endpoint for that profile. The remote server's tools, resources, and prompts are then available through the same profile materialization.

## Test server 1: DeepWiki

DeepWiki is suitable for the first live connection test because its Streamable HTTP endpoint is public and does not require authentication.

Preset configuration:

```json
{
	"endpoint": "https://mcp.deepwiki.com/mcp",
	"auth_type": "none"
}
```

The remote MCP tool names are returned unchanged:

```text
read_wiki_structure
read_wiki_contents
ask_question
```

The MCP resource returns these names unchanged. When the preset id is `deepwiki`, MissionBay's existing `ConfiguredAgentToolResource` publishes them as:

```text
deepwiki__read_wiki_structure
deepwiki__read_wiki_contents
deepwiki__ask_question
```

The wrapper translates the selected effective name back to the original remote name before `tools/call`. The MCP client resource itself does not create or parse that prefix.

A useful first request is:

```text
Show the documentation structure of the public GitHub repository modelcontextprotocol/servers.
```

Use the **Test** action in Agent Component Presets to inspect the materialized definitions and perform a selected tool call.

## Test server 2: MissionBay loopback

MissionBay's own inbound MCP server is the primary integration target because it covers the same tool contracts, resources, prompts, output schemas, and approval workflow used in production.

Create a server-side Tool Profile containing selected Base3IliasLab presets, for example:

```text
ILIAS Plugin Administration
ILIAS Cron Job Administration
ILIAS WebDAV Administration
General Information
```

Create a client preset:

```json
{
	"endpoint": "https://example.org/path/mcp.php?profile=ilias-admin-remote",
	"auth_type": "bearer",
	"token": {
		"mode": "env",
		"name": "MISSIONBAY_REMOTE_MCP_TOKEN"
	}
}
```

Do not include this client preset in the server-side `ilias-admin-remote` profile. That would create a recursive connection rather than a valid integration test.

Suggested checks:

```text
list remote tools
read one General Information resource
get one remote prompt
call a read-only plugin or cron inspection tool
request a WebDAV mutation
verify that no remote tools/call occurs before approval
accept and verify exactly one remote tools/call
repeat and decline; verify zero remote mutation calls
```

## Test server 3: GitHub remote MCP

Use a dedicated test repository and a minimally scoped token.

Preset configuration:

```json
{
	"endpoint": "https://api.githubcopilot.com/mcp/",
	"auth_type": "bearer",
	"token": {
		"mode": "env",
		"name": "GITHUB_MCP_PAT"
	},
	"headers": {
		"X-MCP-Readonly": "true",
		"X-MCP-Toolsets": "repos,issues,pull_requests"
	}
}
```

Start with server-side read-only mode. Use the preset **Test** action and confirm that the returned functions are published with the configured preset id prefix.

## Test server 4: official Everything server

The official Everything server is intended for MCP client interoperability tests and exposes tools, resources, resource templates, prompts, and multiple content forms.

Start its Streamable HTTP mode according to the server package documentation, for example:

```bash
npx -y @modelcontextprotocol/server-everything@latest streamableHttp
```

Use the MCP endpoint printed by the server process in an Agent Component Preset and execute the preset **Test** action. Do not hardcode a local endpoint that differs from the value printed by the installed server version.

## Runtime connection test

Remote connections are tested through the existing Agent Component Preset **Test** action. The test materializes the same configured resource and wrapper used by an agent run, so the displayed function names include the preset id prefix and selected calls use the normal MissionBay execution path.

The legacy paths under `scripts/` are intentionally disabled compatibility stubs. They contain no MCP test or network logic, cannot perform a remote call, and return immediately when loaded from another PHP process.

## Automated tests

The included tests cover:

```text
initialize ordering and initialized notification
protocol negotiation and session headers
one-time expired-session recovery
JSON and POST-SSE responses
pagination and limits
optional resource-template support
secret redaction
reserved and duplicate header rejection
remote tool naming and MissionBay action metadata
approval request generation
resources, templates, prompts, and configured context
binary and non-text MCP content
catalog collision rejection
single-instance preset materialization
remote output schema validation
typed non-text result projection
`isError=true` handling through the existing exception boundary
preset secret redaction and marker validation
```

## Supported boundary

Implemented:

```text
remote MCP over Streamable HTTP POST request/response
JSON responses
SSE responses to POST
none, bearer, API-key, and basic authentication
tools/list and tools/call
resources/list, resources/templates/list, and resources/read
prompts/list and prompts/get
server instructions
pagination
session ids
MissionBay approval for non-read-only remote tools
output schemas and typed MCP content
```

Not implemented in this version:

```text
local stdio process management
legacy separate HTTP plus SSE transport
persistent GET SSE channel
OAuth authorization-code flow
server-initiated sampling
server-initiated elicitation
MCP tasks
resource subscriptions
automatic list-changed notifications
```

These are explicit protocol or lifecycle features. They are not emulated through alternate profiles, synthetic tools, or hidden fallback paths.
