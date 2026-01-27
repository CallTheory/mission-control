# MCP (Model Context Protocol) Implementation

## Overview

Mission Control includes an MCP server implementation with JSON-RPC 2.0 protocol support using **Streamable HTTP** transport (protocol version `2025-03-26`). The server provides tools that AI assistants can use to interact with call center data.

## Transport

This implementation uses **Streamable HTTP** transport, which is the current MCP standard (replacing the deprecated HTTP+SSE transport):

- **POST `/api/mcp/protocol`**: JSON-RPC request returns JSON response
- **GET `/api/mcp/protocol`**: Returns 405 (server-initiated messages not supported)

Benefits of Streamable HTTP:
- Simpler implementation (single endpoint, no SSE complexity)
- Lower resource usage (no heartbeat loops)
- Faster responses (plain JSON instead of SSE overhead)
- Full spec compliance with MCP protocol version `2025-03-26`

## Architecture

### Core Components

1. **JSON-RPC Protocol Handler** (`app/Services/Mcp/Protocol/JsonRpcMessage.php`)
   - Handles JSON-RPC 2.0 message parsing and creation
   - Supports requests, responses, notifications, and errors

2. **MCP Server** (`app/Services/Mcp/McpServer.php`)
   - Central server implementing MCP protocol
   - Tool registry and execution
   - Handles standard MCP methods: `initialize`, `tools/list`, `tools/call`

3. **Tool Interface** (`app/Services/Mcp/Tools/ToolInterface.php`)
   - Standard interface for all MCP tools
   - Defines name, description, input schema, and execution

4. **vCon Tool** (`app/Services/Mcp/Tools/VConTool.php`)
   - Retrieves call records in vCon (Virtual Conversation) format
   - Includes call metadata, participants, recordings, and transcriptions

5. **HTTP Controller** (`app/Http/Controllers/Api/McpSseController.php`)
   - Handles Streamable HTTP transport
   - Authentication via Laravel Sanctum API tokens

## API Endpoint

### MCP Protocol Endpoint
- **URL**: `/api/mcp/protocol`
- **Methods**: POST (JSON-RPC), GET (returns 405)
- **Authentication**: Bearer token (Sanctum)
- **Content-Type**: `application/json`

## Available Tools

### get_vcon_record
Retrieves a vCon record for a specific call.

**Input Schema:**
```json
{
  "callId": "string",           // Required: Call identifier
  "includeRecording": "boolean", // Optional: Include recording URLs (default: true)
  "includeTranscription": "boolean" // Optional: Include transcription (default: true)
}
```

**Returns:** vCon formatted call record with metadata, timeline, and media

### get_call_recording
Retrieves a call recording in MP3 format by its IsCallId.

**Input Schema:**
```json
{
  "isCallId": "string"  // Required: The Intelligent Switch Call ID
}
```

**Returns:**
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

**Notes:**
- Returns base64-encoded MP3 audio data
- Automatically converts WAV recordings to MP3 if not already cached
- MP3 files are cached in Redis for 24 hours
- Team-based access control is enforced
- Conversion may take 5-30 seconds for uncached recordings

## Usage Examples

### 1. Initialize Connection
```json
{
  "jsonrpc": "2.0",
  "method": "initialize",
  "params": {
    "protocolVersion": "2025-03-26",
    "capabilities": {},
    "clientInfo": {
      "name": "my-client",
      "version": "1.0.0"
    }
  },
  "id": 1
}
```

### 2. List Available Tools
```json
{
  "jsonrpc": "2.0",
  "method": "tools/list",
  "id": 2
}
```

### 3. Call vCon Tool
```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "name": "get_vcon_record",
    "arguments": {
      "callId": "CALL-12345",
      "includeRecording": true,
      "includeTranscription": true
    }
  },
  "id": 3
}
```

### 4. Get Call Recording (MP3)
```json
{
  "jsonrpc": "2.0",
  "method": "tools/call",
  "params": {
    "name": "get_call_recording",
    "arguments": {
      "isCallId": "12345"
    }
  },
  "id": 4
}
```

## Testing

A test interface is available at `/utilities/mcp-protocol-test` (requires authentication).

### Using cURL

```bash
# Get API token from your profile settings, then:

# Initialize
curl -X POST https://your-domain/api/mcp/protocol \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"jsonrpc":"2.0","method":"initialize","params":{"protocolVersion":"2025-03-26"},"id":1}'

# List tools
curl -X POST https://your-domain/api/mcp/protocol \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/list","id":2}'

# Get vCon record
curl -X POST https://your-domain/api/mcp/protocol \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"get_vcon_record","arguments":{"callId":"CALL-12345"}},"id":3}'

# Get call recording (MP3)
curl -X POST https://your-domain/api/mcp/protocol \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"tools/call","params":{"name":"get_call_recording","arguments":{"isCallId":"12345"}},"id":4}'

# GET returns 405 (server-initiated messages not supported)
curl -X GET https://your-domain/api/mcp/protocol \
  -H "Authorization: Bearer YOUR_API_TOKEN"
# Response: 405 Method Not Allowed
```

## Adding New Tools

To add a new MCP tool:

1. Create a new class implementing `ToolInterface`:
```php
namespace App\Services\Mcp\Tools;

class MyCustomTool implements ToolInterface
{
    public function getName(): string
    {
        return 'my_custom_tool';
    }

    public function getDescription(): string
    {
        return 'Description of what this tool does';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'param1' => ['type' => 'string', 'description' => 'Parameter description'],
            ],
            'required' => ['param1']
        ];
    }

    public function execute(array $arguments): mixed
    {
        // Tool implementation
        return ['result' => 'data'];
    }
}
```

2. Register it in `McpServer::registerDefaultTools()`:
```php
$this->registerTool(new MyCustomTool());
```

## Security Considerations

- All endpoints require authentication via Sanctum API tokens
- Team-based access control is enforced
- Sensitive data in vCon records should be handled appropriately
- Rate limiting should be configured for production use

## vCon Format

The vCon (Virtual Conversation) format includes:
- **Metadata**: UUID, timestamps, subject
- **Parties**: Participants with roles (caller, agent)
- **Dialog**: Timeline of conversation events
- **Analysis**: Statistics and insights
- **Attachments**: Recordings and related media

See [vCon specification](https://datatracker.ietf.org/doc/html/draft-petrie-vcon) for full format details.

## References

- [MCP Specification 2025-03-26](https://modelcontextprotocol.io/specification/2025-03-26)
- [Streamable HTTP Transport](https://modelcontextprotocol.io/specification/2025-03-26/basic/transports)
