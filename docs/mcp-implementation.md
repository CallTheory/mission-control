# MCP (Model Context Protocol) Implementation

## Overview

Mission Control now includes a full MCP server implementation with JSON-RPC 2.0 protocol support over both SSE (Server-Sent Events) and standard HTTP POST. The server provides tools that AI assistants can use to interact with call center data.

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

5. **SSE Controller** (`app/Http/Controllers/Api/McpSseController.php`)
   - Handles both SSE streaming and POST requests
   - Authentication via Laravel Sanctum API tokens

## API Endpoints

### MCP Protocol Endpoint
- **URL**: `/api/mcp/protocol`
- **Methods**: GET (SSE), POST (JSON-RPC)
- **Authentication**: Bearer token (Sanctum)

### Legacy SSE Endpoints
- `/api/mcp/user-info` - User information stream
- `/api/mcp/{type}` - Custom SSE streams

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

## Usage Examples

### 1. Initialize Connection
```json
{
  "jsonrpc": "2.0",
  "method": "initialize",
  "params": {
    "protocolVersion": "2024-11-05",
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

## Testing

A test interface is available at `/utilities/mcp-protocol-test` (requires authentication).

### Using cURL

```bash
# Get API token from your profile settings, then:

# Initialize
curl -X POST https://your-domain/api/mcp/protocol \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"initialize","params":{"protocolVersion":"2024-11-05"},"id":1}'

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
```

## nginx Configuration

Ensure your nginx configuration supports SSE:

```nginx
location /api/mcp/ {
    # Disable buffering for SSE
    proxy_buffering off;
    proxy_cache off;
    
    # Set timeout higher for long-lived connections
    proxy_read_timeout 86400s;
    keepalive_timeout 86400s;
    
    # SSE specific headers
    proxy_set_header Connection '';
    proxy_http_version 1.1;
    chunked_transfer_encoding off;
    
    # Pass to PHP-FPM
    fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
    
    # Disable fastcgi buffering
    fastcgi_buffering off;
    fastcgi_keep_conn on;
}
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