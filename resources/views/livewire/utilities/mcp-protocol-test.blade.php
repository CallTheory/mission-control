<div>
    <div class="p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">MCP Protocol Test - JSON-RPC & vCon Tool</h3>
        
        <div class="mb-4">
            <p class="text-sm text-gray-600 mb-2">API Token required for authentication. Generate one from your profile settings.</p>
            <input type="text" id="apiToken" placeholder="Enter your API token" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="mb-4">
            <label class="text-sm text-gray-600 mb-1 block">Call ID for vCon test:</label>
            <input type="text" id="callId" placeholder="Enter a call ID (e.g., CALL-12345)" value="CALL-12345"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="mb-4 space-x-2">
            <button id="initBtn" 
                    class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Initialize MCP
            </button>
            <button id="listToolsBtn" 
                    class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500"
                    disabled>
                List Tools
            </button>
            <button id="getVconBtn" 
                    class="px-4 py-2 bg-purple-500 text-white rounded-md hover:bg-purple-600 focus:outline-none focus:ring-2 focus:ring-purple-500"
                    disabled>
                Get vCon Record
            </button>
            <button id="clearBtn" 
                    class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500">
                Clear Log
            </button>
        </div>

        <div class="mb-4">
            <h4 class="text-md font-medium text-gray-900 mb-2">Request/Response Log</h4>
            <div id="requestLog" class="p-3 bg-gray-100 rounded-md h-96 overflow-y-auto font-mono text-xs">
                <div class="text-gray-500">No requests sent yet...</div>
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
            entry.className = type === 'error' ? 'text-red-600 mb-2' : 
                            type === 'success' ? 'text-green-600 mb-2' : 
                            type === 'request' ? 'text-blue-600 mb-2' :
                            type === 'response' ? 'text-purple-600 mb-2' :
                            'text-gray-700 mb-2';
            
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
            requestLog.innerHTML = '<div class="text-gray-500">Log cleared...</div>';
        });
    </script>
</div>