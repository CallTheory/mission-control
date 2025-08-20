<div>
    <div class="p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">MCP Server - SSE Test</h3>
        
        <div class="mb-4">
            <p class="text-sm text-gray-600 mb-2">API Token required for authentication. Generate one from your profile settings.</p>
            <input type="text" id="apiToken" placeholder="Enter your API token" 
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="mb-4">
            <button id="connectBtn" 
                    class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Connect to SSE Stream
            </button>
            <button id="disconnectBtn" 
                    class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 ml-2"
                    style="display: none;">
                Disconnect
            </button>
        </div>

        <div class="mb-4">
            <h4 class="text-md font-medium text-gray-900 mb-2">Connection Status</h4>
            <div id="status" class="p-3 bg-gray-100 rounded-md text-sm">
                <span class="text-gray-500">Not connected</span>
            </div>
        </div>

        <div>
            <h4 class="text-md font-medium text-gray-900 mb-2">Event Log</h4>
            <div id="eventLog" class="p-3 bg-gray-100 rounded-md h-64 overflow-y-auto font-mono text-sm">
                <div class="text-gray-500">No events received yet...</div>
            </div>
        </div>
    </div>

    <script>
        let eventSource = null;
        const connectBtn = document.getElementById('connectBtn');
        const disconnectBtn = document.getElementById('disconnectBtn');
        const statusDiv = document.getElementById('status');
        const eventLog = document.getElementById('eventLog');
        const apiTokenInput = document.getElementById('apiToken');

        function addLogEntry(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            const entry = document.createElement('div');
            entry.className = type === 'error' ? 'text-red-600' : (type === 'success' ? 'text-green-600' : 'text-gray-700');
            entry.textContent = `[${timestamp}] ${message}`;
            
            if (eventLog.firstChild && eventLog.firstChild.textContent.includes('No events received yet')) {
                eventLog.innerHTML = '';
            }
            
            eventLog.appendChild(entry);
            eventLog.scrollTop = eventLog.scrollHeight;
        }

        function updateStatus(message, color = 'gray') {
            statusDiv.innerHTML = `<span class="text-${color}-600">${message}</span>`;
        }

        connectBtn.addEventListener('click', () => {
            const token = apiTokenInput.value.trim();
            
            if (!token) {
                addLogEntry('Please enter an API token', 'error');
                return;
            }

            updateStatus('Connecting...', 'yellow');
            addLogEntry('Attempting to connect to SSE stream...');

            try {
                eventSource = new EventSource('/api/mcp/user-info', {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'text/event-stream'
                    }
                });

                eventSource.onopen = () => {
                    updateStatus('Connected', 'green');
                    addLogEntry('Connection established', 'success');
                    connectBtn.style.display = 'none';
                    disconnectBtn.style.display = 'inline-block';
                };

                eventSource.onmessage = (event) => {
                    addLogEntry(`Message: ${event.data}`);
                };

                eventSource.addEventListener('user', (event) => {
                    const data = JSON.parse(event.data);
                    addLogEntry(`User Event: ${data.name} (${data.email}) - Team: ${data.team}`, 'success');
                });

                eventSource.addEventListener('connected', (event) => {
                    const data = JSON.parse(event.data);
                    addLogEntry(`Connected: ${data.message} (Server: ${data.server})`);
                });

                eventSource.addEventListener('heartbeat', (event) => {
                    const data = JSON.parse(event.data);
                    addLogEntry(`Heartbeat: ${data.timestamp}`);
                });

                eventSource.onerror = (error) => {
                    updateStatus('Connection error', 'red');
                    addLogEntry('Connection error - check your API token', 'error');
                    
                    if (eventSource.readyState === EventSource.CLOSED) {
                        disconnect();
                    }
                };

            } catch (error) {
                updateStatus('Failed to connect', 'red');
                addLogEntry(`Error: ${error.message}`, 'error');
            }
        });

        disconnectBtn.addEventListener('click', () => {
            disconnect();
        });

        function disconnect() {
            if (eventSource) {
                eventSource.close();
                eventSource = null;
            }
            
            updateStatus('Disconnected', 'gray');
            addLogEntry('Connection closed');
            connectBtn.style.display = 'inline-block';
            disconnectBtn.style.display = 'none';
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (eventSource) {
                eventSource.close();
            }
        });
    </script>
</div>