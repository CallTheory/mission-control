# MCP Server

The MCP (Model Context Protocol) Server exposes Mission Control data to AI assistants and
other MCP-compatible clients using the standardized Model Context Protocol.

## Access

The MCP Server requires the `utility.mcp_server` capability, and must be enabled both as a
system utility and for the team. The **MCP Server** system feature must also be on. See
[Permissions](../system/permissions.md).

## Endpoint

| | |
|---|---|
| **URL** | `/api/mcp/protocol` |
| **Methods** | `POST` for JSON-RPC requests; `GET` returns `405` |
| **Authentication** | Bearer token — see [API Gateway](../system/api-gateway.md) |
| **Content-Type** | `application/json` |

Generate a token at `/user/api-tokens`. Team-based access control is enforced on every
call, so a client only reaches the data its token's owner can reach.

## Protocol

The server implements Model Context Protocol version `2025-03-26` over JSON-RPC 2.0, using
the **Streamable HTTP** transport — a single endpoint, no Server-Sent Events. The server
identifies itself as `mission-control-mcp` v1.0.0.

### Supported methods

| Method | Purpose |
|--------|---------|
| `initialize` | Returns server capabilities and info |
| `initialized` | Client confirmation |
| `tools/list` | Lists available tools |
| `tools/call` | Executes a tool |
| `ping` | Health check, returns pong |

### Capabilities

- **Tools** — listing and execution
- **Resources** — subscription support
- **Prompts** — listing support
- **Logging** — enabled

## Available Tools

Which tools a client can reach is controlled by the allowed-tools setting on the System →
MCP Server page.

### `get_vcon_record`

Retrieves a call record in vCon (Virtual Conversation) format: metadata, participants,
timeline, and associated media.

```json
{
  "callId": "string",                 // Required: call identifier
  "includeRecording": "boolean",      // Optional, default true
  "includeTranscription": "boolean"   // Optional, default true
}
```

### `get_call_recording`

Retrieves a call recording as MP3 audio by its Intelligent Series call id.

```json
{
  "isCallId": "string"   // Required
}
```

Returns base64-encoded MP3:

```json
{
  "isCallId": "12345",
  "format": "mp3",
  "encoding": "base64",
  "data": "//uQxAAAAAANIAAAAAExBTUUzLjEwMFVV...",
  "sizeBytes": 123456,
  "cached": true
}
```

WAV recordings are converted to MP3 on first request and cached for 24 hours, so an
uncached recording can take 5–30 seconds to return.

## Examples

Initialize a connection:

```json
{
  "jsonrpc": "2.0",
  "method": "initialize",
  "params": {
    "protocolVersion": "2025-03-26",
    "capabilities": {},
    "clientInfo": { "name": "my-client", "version": "1.0.0" }
  },
  "id": 1
}
```

Call a tool:

```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "name": "get_vcon_record",
    "arguments": { "callId": "CALL-12345", "includeRecording": true }
  },
  "id": 3
}
```

### With cURL

```bash
# List the available tools
curl -X POST https://your-server.tld/api/mcp/protocol \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":2}'

# Fetch a vCon record
curl -X POST https://your-server.tld/api/mcp/protocol \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"get_vcon_record","arguments":{"callId":"CALL-12345"}},"id":3}'

# Fetch a recording as MP3
curl -X POST https://your-server.tld/api/mcp/protocol \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"get_call_recording","arguments":{"isCallId":"12345"}},"id":4}'
```

## Protocol Test

A built-in test interface is available at `/utilities/mcp-protocol-test` for verifying
connectivity and trying tool calls from the browser.

## About vCon

The vCon (Virtual Conversation) format packages a conversation as metadata, parties,
dialog, analysis and attachments. See the
[vCon specification](https://datatracker.ietf.org/doc/html/draft-petrie-vcon) for the full
format.

## Setup

Enable the MCP Server in [System Settings](../system/index.md) and configure the allowed
tools on the System → MCP Server page.

## References

- [MCP Specification 2025-03-26](https://modelcontextprotocol.io/specification/2025-03-26)
- [Streamable HTTP Transport](https://modelcontextprotocol.io/specification/2025-03-26/basic/transports)
