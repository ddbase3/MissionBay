# MissionBay MCP Server v1

MissionBay provides a profile-based inbound MCP server for exposing selected agent tools, resources and prompts to MCP clients.

This document covers the inbound server endpoint. The outbound remote MCP client resource is documented in [MCP_CLIENT_AGENT_RESOURCE.md](MCP_CLIENT_AGENT_RESOURCE.md).

This document describes the v1 boundary. OAuth, additional domain-specific resources/tools and native SSE-based elicitation are intentionally outside this MCP server completion step.

## Endpoint

The host system provides the public endpoint. In the ILIAS integration this is usually:

```text
https://example.org/path/mcp.php?profile=<profile-id>
```

The endpoint is selected by the `profile` query parameter. The profile controls which tool presets are available.

## Profile settings

MCP profiles are stored in the `tool-profile` settings group.

```json
{
	"id": "my-mcp-profile",
	"label": "My MCP Profile",
	"description": "MCP access for selected MissionBay tools.",
	"type": "mcp",
	"enabled": true,
	"mcp_fixed_bearer_enabled": true,
	"mcp_credential_enabled": true,
	"token": "mb-mcp-...",
	"tools": [
		"generalinfo",
		"igor2"
	]
}
```

Each profile can enable either or both authentication paths:

```text
fixed bearer token
personal credentials through CredentialFoundation
```

The fixed token is a shared technical secret stored in the profile. CredentialFoundation access publishes the stable service id `missionbay:mcp:<profile-id>`. KeyHarbor or another implementation can grant that service to personal Bearer or HMAC credentials. Profile ids are immutable after creation because they are part of this service id; a profile can be duplicated or deleted instead.

## HTTP transport

Supported transport mode:

```text
POST application/json
```

Not supported:

```text
GET text/event-stream
```

`GET`, `DELETE` and other non-POST methods are rejected with `405 Method Not Allowed`. This server does not provide SSE streaming in v1.

Clients send the normal MCP headers plus one enabled authentication form. Fixed and personal Bearer access both use `Authorization: Bearer ...`. KeyHarbor HMAC access additionally sends:

```text
X-BASE3-Timestamp: <Unix timestamp>
X-BASE3-Nonce: <unique nonce>
X-BASE3-Signature: <lowercase HMAC-SHA256 hex>
```

The HMAC canonical request is method, request path, raw query string, timestamp, nonce, and SHA-256 of the exact request body, separated by newlines. The endpoint never falls back to the fixed profile token when any HMAC header is present.

Missing `MCP-Protocol-Version` is tolerated for compatibility. Supported protocol versions are `2025-11-25`, `2025-06-18` and `2025-03-26`. Unsupported protocol versions are rejected.

## Supported MCP methods

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

Notifications are accepted with HTTP `202` and an empty body. `notifications/cancelled` is explicitly accepted and logged so clients can cancel outstanding work without causing protocol errors. The synchronous v1 server does not interrupt already-running PHP tool code.

## Tools

Tools are resolved from the presets configured in the MCP profile. MissionBay maps `IAgentTool` definitions to MCP tool definitions.

`tools/list` supports `cursor` and may return `nextCursor`.

MissionBay exposes the four standard MCP safety annotations for every tool:

```text
readOnlyHint
destructiveHint
idempotentHint
openWorldHint
```

Explicit boolean values from the MissionBay tool definition are preserved. Top-level MissionBay annotations are mapped to the MCP `annotations` object. Missing values are completed conservatively:

```text
explicit readOnlyHint=true
  destructiveHint=false when unspecified
  idempotentHint=true when unspecified
  openWorldHint=true when unspecified

readOnlyHint missing or false
  readOnlyHint=false
  destructiveHint=true when unspecified
  idempotentHint=false when unspecified
  openWorldHint=true when unspecified
```

A contradictory explicit combination is preserved so the client can apply its own stricter policy.

MissionBay does not expose an additional confirmation tool. The MCP client or host decides whether user approval is required before `tools/call`, based on these annotations and its own security policy.

## Structured tool output

Tool results include MCP text content. Object-like PHP results are also returned as `structuredContent`.

```json
{
	"content": [
		{
			"type": "text",
			"text": "..."
		}
	],
	"structuredContent": {
		"ok": true
	}
}
```

Output schemas can be provided through `Base3\Api\IOutputSchemaProvider`.

## Tool authorization and approval ownership

The inbound MCP endpoint uses a client-authorized execution model.

```text
MCP client or host
  -> evaluates tool annotations
  -> obtains user approval when required
  -> sends tools/call

MissionBay MCP server
  -> loads the selected MCP profile
  -> accepts an enabled fixed token or authorizes the profile service through CredentialFoundation
  -> verifies that the requested tool belongs to that profile
  -> executes the authorized tools/call directly
```

The server does not create a second pending confirmation and does not expose `missionbay_confirm_action`. This avoids duplicate human-in-the-loop dialogs and keeps the endpoint interoperable with standard MCP clients.

Server-side security remains mandatory. A credential only receives profiles explicitly granted through CredentialFoundation, and every profile only exposes its configured tools. Fixed profile tokens remain available for technical compatibility. Personal credentials establish the BASE3 user identity through access control before the profile grant is checked.

`IConfirmableAgentTool` remains available for direct in-process callers. It is not part of the inbound MCP execution path. The policy-controlled MissionBay agent harness continues to use action policies and mutation commit guards for its own local tool execution.

## Resources

Resources are provided by tools or globally discoverable services implementing:

```text
MissionBay\Api\IAgentResourceProvider
```

Supported methods:

```text
resources/list
resources/read
resources/templates/list
```

`resources/list` and `resources/templates/list` support `cursor` and may return `nextCursor`.

Current built-in resources include:

```text
missionbay://profile/<profile-id>
generalinfo://topics
generalinfo://topic/<topic>
```

Current built-in resource templates include:

```text
generalinfo://topic/{topic}
```

`IAgentResourceProvider::getResourceDefinitions()` may return concrete resources with `uri` and resource templates with `uriTemplate`. Concrete resources are exposed through `resources/list`; template entries are exposed through `resources/templates/list`.

Host integrations can add additional resources through the same interface. MissionBay itself does not depend on ILIAS. Host-specific resources live in the host integration plugin.

The ILIAS lab endpoint registers this optional host resource:

```text
ilias://context/current
```

## Prompts

Prompts are provided by tools or globally discoverable services implementing:

```text
MissionBay\Api\IAgentPromptProvider
```

Supported methods:

```text
prompts/list
prompts/get
```

`prompts/list` supports `cursor` and may return `nextCursor`.

Prompts are intended to guide MCP clients through useful tool workflows. Global prompt providers are loaded through the BASE3 class map; profile tools can also provide prompts directly.

## Host-provided providers

Host systems may expose request-local MCP resources or prompts without coupling MissionBay to the host implementation.

The host endpoint can register providers through:

```text
MissionBay\Mcp\McpHostProviderRegistry
```

The ILIAS endpoint registers `Base3IliasLab\Resource\IliasContextResourceProvider` before dispatching the MissionBay MCP output.

## Security notes

Implemented in v1:

```text
optional fixed profile Bearer token
optional CredentialFoundation Bearer or HMAC access
per-profile credential service grants
immutable profile ids
same-host Origin check
Accept validation
protocol-version validation
max request body size
audit logging for MCP calls
ping support
cancellation notification acceptance
tool annotations for read-only and write-capable tools
client-owned approval before tools/call
no server-side pending confirmation tool
no SSE endpoint
read-only functions remain direct in mixed read/write tools
```

Intentionally outside v1:

```text
OAuth client registration
rate limiting
SSE streaming
native MCP elicitation
fine-grained tool scopes
additional domain-specific resources and tools
```

## Pagination

List methods use simple numeric cursors. A first request may omit `cursor`; a response with `nextCursor` can be passed into the next list request.

```json
{
	"jsonrpc": "2.0",
	"id": 1,
	"method": "tools/list",
	"params": {
		"cursor": "50"
	}
}
```

## Runtime test

Use the MCP profile and Agent Component Preset administration test actions for runtime verification. These paths use the same catalogs, configured wrappers, security policy, and audit boundary as production requests.

The legacy `scripts/mcp-smoke.sh` path is intentionally disabled and contains no request logic.
