<div>
    <div class="p-6">
        <h3 class="text-lg font-medium text-surface-fg mb-4">MCP Server &mdash; Streamable HTTP Test</h3>

        {{-- This page used to open an EventSource against /api/mcp/user-info.
             That endpoint never existed, and EventSource ignores a `headers`
             option entirely (its only init field is withCredentials), so the
             bearer token was never sent either. The server has always spoken
             Streamable HTTP -- one endpoint, JSON-RPC over POST, GET answered
             with 405 -- which is what the 2025-03-26 spec replaced the old
             HTTP+SSE transport with. This talks to it directly via fetch(). --}}
        <p class="text-sm text-surface-fg-soft mb-4">
            JSON-RPC 2.0 over POST to <code class="bg-surface-2 px-1 rounded">/api/mcp/protocol</code>.
            No streaming connection is held open, so nothing here needs proxy buffering turned off.
        </p>

        <div class="mb-4">
            <label for="apiToken" class="text-sm text-surface-fg-soft mb-1 block">API Token</label>
            <input type="text" id="apiToken" placeholder="Paste your API token"
                   class="w-full px-3 py-2 border border-border rounded-md focus:outline-none focus:ring-2 focus:ring-info">
            <p class="text-xs text-muted mt-1">
                <a href="{{ route('api-tokens.index') }}" class="font-semibold text-primary hover:underline">Create one under API Tokens</a>.
                Any token works &mdash; access is decided by your team and role, not by the token's permissions.
            </p>
        </div>

        <div class="mb-4">
            <label for="callId" class="text-sm text-surface-fg-soft mb-1 block">Call ID (for the vCon tool)</label>
            <input type="text" id="callId" placeholder="e.g. CALL-12345" value="CALL-12345"
                   class="w-full px-3 py-2 border border-border rounded-md focus:outline-none focus:ring-2 focus:ring-info">
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <button id="initBtn" class="px-4 py-2 bg-info text-info-fg rounded-md hover:bg-info-hover focus:outline-none focus:ring-2 focus:ring-info">
                Initialize
            </button>
            <button id="listToolsBtn" disabled
                    class="px-4 py-2 bg-success text-success-fg rounded-md hover:bg-success-hover focus:outline-none focus:ring-2 focus:ring-success disabled:opacity-50">
                List Tools
            </button>
            <button id="getVconBtn" disabled
                    class="px-4 py-2 bg-accent text-accent-fg rounded-md hover:bg-accent-hover focus:outline-none focus:ring-2 focus:ring-accent disabled:opacity-50">
                Get vCon Record
            </button>
            <button id="clearBtn" class="px-4 py-2 bg-surface-3 text-surface-fg rounded-md hover:bg-border-strong focus:outline-none focus:ring-2 focus:ring-border">
                Clear Log
            </button>
        </div>

        <div>
            <h4 class="text-md font-medium text-surface-fg mb-2">Request / Response Log</h4>
            <div id="requestLog" class="p-3 bg-surface-2 rounded-md h-96 overflow-y-auto font-mono text-xs">
                <div class="text-muted">No requests sent yet...</div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const apiTokenInput = document.getElementById('apiToken');
            const callIdInput = document.getElementById('callId');
            const initBtn = document.getElementById('initBtn');
            const listToolsBtn = document.getElementById('listToolsBtn');
            const getVconBtn = document.getElementById('getVconBtn');
            const clearBtn = document.getElementById('clearBtn');
            const requestLog = document.getElementById('requestLog');

            // Matches what App\Services\Mcp\McpServer advertises back.
            const PROTOCOL_VERSION = '2025-03-26';
            const ENDPOINT = '/api/mcp/protocol';

            let requestId = 1;
            let initialized = false;

            function log(message, type = 'info') {
                const stamp = new Date().toLocaleTimeString();
                const entry = document.createElement('div');
                entry.className = {
                    error: 'text-danger mb-2',
                    success: 'text-success mb-2',
                    request: 'text-info mb-2',
                    response: 'text-accent mb-2',
                }[type] || 'text-surface-fg-soft mb-2';

                if (type === 'request' || type === 'response') {
                    const pre = document.createElement('pre');
                    pre.className = 'whitespace-pre-wrap break-words';
                    pre.textContent = `[${stamp}] ${type.toUpperCase()}:\n${message}`;
                    entry.appendChild(pre);
                } else {
                    entry.textContent = `[${stamp}] ${message}`;
                }

                if (requestLog.firstChild && requestLog.firstChild.textContent.includes('No requests sent yet')) {
                    requestLog.innerHTML = '';
                }

                requestLog.appendChild(entry);
                requestLog.scrollTop = requestLog.scrollHeight;
            }

            async function send(method, params = null) {
                const token = apiTokenInput.value.trim();

                if (!token) {
                    log('Enter an API token first.', 'error');
                    return null;
                }

                const request = { jsonrpc: '2.0', method: method, id: requestId++ };
                if (params !== null) {
                    request.params = params;
                }

                log(JSON.stringify(request, null, 2), 'request');

                let response;
                try {
                    response = await fetch(ENDPOINT, {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(request),
                    });
                } catch (error) {
                    log(`Network error reaching ${ENDPOINT}: ${error.message}`, 'error');
                    return null;
                }

                // Report the status rather than failing silently. A 404 here almost
                // always means the mcp-server feature flag is off, which is
                // otherwise indistinguishable from a bad token.
                if (!response.ok) {
                    const body = await response.text();
                    log(`HTTP ${response.status} ${response.statusText} from ${ENDPOINT}`, 'error');

                    if (response.status === 404) {
                        log('The endpoint is not registered. Enable the MCP Server feature under System.', 'error');
                    } else if (response.status === 401) {
                        log('Token rejected. Create a fresh one under API Tokens.', 'error');
                    } else if (response.status === 403) {
                        log('Authenticated, but the MCP Server utility is not enabled for your team or role.', 'error');
                    }

                    if (body) {
                        log(body.slice(0, 2000), 'response');
                    }

                    return null;
                }

                const data = await response.json();
                log(JSON.stringify(data, null, 2), 'response');

                // A JSON-RPC error arrives with HTTP 200, so it has to be read
                // out of the body rather than the status.
                if (data && data.error) {
                    log(`JSON-RPC error ${data.error.code}: ${data.error.message}`, 'error');
                    return null;
                }

                return data;
            }

            initBtn.addEventListener('click', async () => {
                log('Initializing...', 'info');

                const response = await send('initialize', {
                    protocolVersion: PROTOCOL_VERSION,
                    capabilities: { tools: {}, resources: {} },
                    clientInfo: { name: 'mission-control-test', version: '1.0.0' },
                });

                if (response && response.result) {
                    const served = response.result.protocolVersion;
                    log(`Initialized. Server protocol: ${served}`, 'success');

                    if (served && served !== PROTOCOL_VERSION) {
                        log(`Note: this page speaks ${PROTOCOL_VERSION}.`, 'info');
                    }

                    initialized = true;
                    listToolsBtn.disabled = false;
                    getVconBtn.disabled = false;

                    await send('initialized');
                }
            });

            listToolsBtn.addEventListener('click', async () => {
                if (!initialized) {
                    log('Initialize first.', 'error');
                    return;
                }

                log('Listing tools...', 'info');
                const response = await send('tools/list');

                if (response && response.result) {
                    const tools = response.result.tools || [];
                    log(`${tools.length} tool(s) available.`, 'success');

                    if (tools.length === 0) {
                        log('None enabled. Check Allowed Tools under System > MCP Server.', 'info');
                    }
                }
            });

            getVconBtn.addEventListener('click', async () => {
                if (!initialized) {
                    log('Initialize first.', 'error');
                    return;
                }

                const callId = callIdInput.value.trim();
                if (!callId) {
                    log('Enter a call ID.', 'error');
                    return;
                }

                log(`Fetching vCon record for ${callId}...`, 'info');

                const response = await send('tools/call', {
                    name: 'get_vcon_record',
                    arguments: { callId: callId, includeRecording: true, includeTranscription: true },
                });

                if (response && response.result) {
                    log('vCon record retrieved.', 'success');
                }
            });

            clearBtn.addEventListener('click', () => {
                requestLog.innerHTML = '<div class="text-muted">Log cleared...</div>';
            });
        })();
    </script>
</div>
