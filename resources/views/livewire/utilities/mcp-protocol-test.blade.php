<div>
    <div class="p-6">
        <h3 class="text-lg font-medium text-surface-fg mb-4">MCP Protocol Test - JSON-RPC & vCon Tool</h3>
        
        <div class="mb-4">
            <p class="text-sm text-surface-fg-soft mb-2">API Token required for authentication. Generate one from your profile settings.</p>
            <input type="text" id="apiToken" placeholder="Enter your API token" 
                   class="w-full px-3 py-2 border border-border rounded-md focus:outline-none focus:ring-2 focus:ring-info">
        </div>

        <div class="mb-4">
            <label class="text-sm text-surface-fg-soft mb-1 block">Call ID for vCon test:</label>
            <input type="text" id="callId" placeholder="Enter a call ID (e.g., CALL-12345)" value="CALL-12345"
                   class="w-full px-3 py-2 border border-border rounded-md focus:outline-none focus:ring-2 focus:ring-info">
        </div>

        <div class="mb-4 space-x-2">
            <button id="initBtn" 
                    class="px-4 py-2 bg-info text-info-fg rounded-md hover:bg-info-hover focus:outline-none focus:ring-2 focus:ring-info">
                Initialize MCP
            </button>
            <button id="listToolsBtn" 
                    class="px-4 py-2 bg-success text-success-fg rounded-md hover:bg-success-hover focus:outline-none focus:ring-2 focus:ring-success"
                    disabled>
                List Tools
            </button>
            <button id="getVconBtn" 
                    class="px-4 py-2 bg-accent text-accent-fg rounded-md hover:bg-accent-hover focus:outline-none focus:ring-2 focus:ring-accent"
                    disabled>
                Get vCon Record
            </button>
            <button id="clearBtn" 
                    class="px-4 py-2 bg-surface-3 text-surface-fg rounded-md hover:bg-border-strong focus:outline-none focus:ring-2 focus:ring-border">
                Clear Log
            </button>
        </div>

        <div class="mb-4">
            <h4 class="text-md font-medium text-surface-fg mb-2">Request/Response Log</h4>
            <div id="requestLog" class="p-3 bg-surface-2 rounded-md h-96 overflow-y-auto font-mono text-xs">
                <div class="text-muted">No requests sent yet...</div>
            </div>
        </div>
    </div>

    <script>
        const apiTokenInput = document.getElementById('apiToken');
        const callIdInput = document.getElementById('callId');
        const initBtn = document.getElementById('initBtn');
        const listToolsBtn = document.getElementById('listToolsBtn');
        const getVconBtn = document.getElementById('getVconBtn');
        const clearBtn = document.getElementById('clearBtn');
        const requestLog = document.getElementById('requestLog');
        
        let requestId = 1;
        let initialized = false;

        function addLogEntry(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            const entry = document.createElement('div');
            entry.className = type === 'error' ? 'text-danger mb-2' : 
                            type === 'success' ? 'text-success mb-2' : 
                            type === 'request' ? 'text-info mb-2' :
                            type === 'response' ? 'text-accent mb-2' :
                            'text-surface-fg-soft mb-2';
            
            if (type === 'request' || type === 'response') {
                const pre = document.createElement('pre');
                pre.className = 'whitespace-pre-wrap break-words';
                pre.textContent = `[${timestamp}] ${type.toUpperCase()}:\n${message}`;
                entry.appendChild(pre);
            } else {
                entry.textContent = `[${timestamp}] ${message}`;
            }
            
            if (requestLog.firstChild && requestLog.firstChild.textContent.includes('No requests sent yet')) {
                requestLog.innerHTML = '';
            }
            
            requestLog.appendChild(entry);
            requestLog.scrollTop = requestLog.scrollHeight;
        }

        async function sendJsonRpcRequest(method, params = null) {
            const token = apiTokenInput.value.trim();
            
            if (!token) {
                addLogEntry('Please enter an API token', 'error');
                return null;
            }

            const request = {
                jsonrpc: '2.0',
                method: method,
                id: requestId++
            };
            
            if (params !== null) {
                request.params = params;
            }

            addLogEntry(JSON.stringify(request, null, 2), 'request');

            try {
                const response = await fetch('/api/mcp/protocol', {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(request)
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();
                addLogEntry(JSON.stringify(data, null, 2), 'response');
                
                return data;
            } catch (error) {
                addLogEntry(`Error: ${error.message}`, 'error');
                return null;
            }
        }

        initBtn.addEventListener('click', async () => {
            addLogEntry('Initializing MCP server...', 'info');
            
            const response = await sendJsonRpcRequest('initialize', {
                protocolVersion: '2024-11-05',
                capabilities: {
                    tools: {},
                    resources: {}
                },
                clientInfo: {
                    name: 'mission-control-test',
                    version: '1.0.0'
                }
            });
            
            if (response && response.result) {
                addLogEntry('MCP server initialized successfully!', 'success');
                initialized = true;
                listToolsBtn.disabled = false;
                getVconBtn.disabled = false;
                
                // Send initialized notification
                await sendJsonRpcRequest('initialized');
            }
        });

        listToolsBtn.addEventListener('click', async () => {
            if (!initialized) {
                addLogEntry('Please initialize MCP first', 'error');
                return;
            }
            
            addLogEntry('Listing available tools...', 'info');
            const response = await sendJsonRpcRequest('tools/list');
            
            if (response && response.result) {
                addLogEntry(`Found ${response.result.tools.length} tools`, 'success');
            }
        });

        getVconBtn.addEventListener('click', async () => {
            if (!initialized) {
                addLogEntry('Please initialize MCP first', 'error');
                return;
            }
            
            const callId = callIdInput.value.trim();
            if (!callId) {
                addLogEntry('Please enter a call ID', 'error');
                return;
            }
            
            addLogEntry(`Getting vCon record for call: ${callId}`, 'info');
            
            const response = await sendJsonRpcRequest('tools/call', {
                name: 'get_vcon_record',
                arguments: {
                    callId: callId,
                    includeRecording: true,
                    includeTranscription: true
                }
            });
            
            if (response && response.result) {
                addLogEntry('vCon record retrieved successfully!', 'success');
            }
        });

        clearBtn.addEventListener('click', () => {
            requestLog.innerHTML = '<div class="text-muted">Log cleared...</div>';
        });
    </script>
</div>