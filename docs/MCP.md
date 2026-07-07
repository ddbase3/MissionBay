# MissionBay MCP Server v1

MissionBay provides a profile-based MCP server for exposing selected agent tools, resources and prompts to MCP clients.

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
	"token": "mb-mcp-...",
	"tools": [
		"generalinfo",
		"igor2"
	]
}
```

The token is stored in the profile and is used as a Bearer token. This is the v1 profile-token authenticator. OAuth can replace this later behind the MCP authentication layer.

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

Clients should send:

```text
Authorization: Bearer <profile-token>
Content-Type: application/json
Accept: application/json, text/event-stream
MCP-Protocol-Version: 2025-11-25
```

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

MissionBay also maps tool `annotations` to MCP. Confirmable tools receive conservative default annotations unless their own tool definition provides annotations:

```json
{
	"readOnlyHint": false,
	"destructiveHint": true,
	"idempotentHint": false,
	"openWorldHint": true
}
```

MissionBay also adds one internal control tool:

```text
missionbay_confirm_action
```

This tool accepts or declines pending actions created by confirmable tools. It declares MCP annotations and an output schema so clients can treat it as a write-capable confirmation control.

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

## Pending confirmations

MissionBay v1 uses a multi-call confirmation workflow instead of SSE-based elicitation.

A write-capable tool can implement:

```text
MissionBay\Api\IConfirmableAgentTool
```

When a tool call needs confirmation, the MCP server stores a pending confirmation and returns:

```json
{
	"requires_confirmation": true,
	"confirmation_id": "mcp-cnf-...",
	"tool": "example_write_tool",
	"title": "Confirm action",
	"message": "Please confirm this action.",
	"summary": [
		"Action detail"
	],
	"risk": "medium",
	"expires_at": "2026-07-07T10:00:00+00:00",
	"next_tool": "missionbay_confirm_action"
}
```

The MCP client or assistant should then ask the user for confirmation. If accepted, it calls:

```json
{
	"name": "missionbay_confirm_action",
	"arguments": {
		"confirmation_id": "mcp-cnf-...",
		"decision": "accept"
	}
}
```

To decline:

```json
{
	"name": "missionbay_confirm_action",
	"arguments": {
		"confirmation_id": "mcp-cnf-...",
		"decision": "decline"
	}
}
```

This works with ordinary MCP tool calls and does not require SSE.

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
profile Bearer token
same-host Origin check
Accept validation
protocol-version validation
max request body size
audit logging for MCP calls
ping support
cancellation notification acceptance
tool annotations for confirmable/write-capable tools
confirmation control output schema
no SSE endpoint
confirmation workflow for write-capable tools
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

## Smoke test

Each MCP patch includes:

```text
scripts/mcp-smoke.sh
```

Run it with:

```bash
bash scripts/mcp-smoke.sh \
	'https://example.org/path/mcp.php?profile=my-mcp-profile' \
	'<profile-token>'
```

For ILIAS smoke tests, require the host context resource explicitly:

```bash
MCP_REQUIRE_ILIAS_CONTEXT=1 ./scripts/mcp-smoke.sh '<mcp-url>' '<token>'
```
