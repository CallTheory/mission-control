<div class="space-y-6">
    <!-- Main Enable/Disable Toggle -->
    <x-form-section submit="saveMcpSettings">
        <x-slot name="title">
            {{ __('MCP Server Configuration') }}
        </x-slot>

        <x-slot name="description">
            Configure the Model Context Protocol (MCP) server settings for AI assistant integrations.
        </x-slot>
        
        <x-slot name="form">
            <!-- MCP Enabled Toggle -->
            <div x-data="{ isEnabled: $wire.mcp_enabled }" class="flex items-center justify-between col-span-6">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-surface-fg" id="mcp-enabled-label">Enable MCP Server</span>
                    <span class="text-sm text-muted" id="mcp-enabled-description">
                        Enable the MCP server to allow AI assistants to interact with your Mission Control data via JSON-RPC protocol.
                    </span>
                </span>

                <button
                    type="button"
                    :class="{ 'bg-primary': isEnabled, 'bg-surface-3': !isEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="mcp-enabled-label"
                    aria-describedby="mcp-enabled-description"
                    @click="$wire.toggleMcpEnabled(); isEnabled = !isEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-surface shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>

            <hr class="col-span-6 my-4 border border-border" />

            <!-- Rate Limiting -->
            <div class="col-span-6 sm:col-span-3">
                <x-label for="mcp_rate_limit" class="font-semibold" value="{{ __('Rate Limit') }}" />
                <x-input id="mcp_rate_limit" type="number" class="mt-1 block w-full" wire:model="mcp_rate_limit" min="1" max="1000" />
                <small class="text-xs text-muted">Requests per minute per API key (1-1000)</small>
                <x-input-error for="mcp_rate_limit" class="mt-2" />
            </div>

            <!-- Timeout -->
            <div class="col-span-6 sm:col-span-3">
                <x-label for="mcp_timeout" class="font-semibold" value="{{ __('Tool Execution Timeout') }}" />
                <x-input id="mcp_timeout" type="number" class="mt-1 block w-full" wire:model="mcp_timeout" min="1" max="300" />
                <small class="text-xs text-muted">Maximum seconds for tool execution (1-300)</small>
                <x-input-error for="mcp_timeout" class="mt-2" />
            </div>

            <!-- Max Response Size -->
            <div class="col-span-6 sm:col-span-3">
                <x-label for="mcp_max_response_size" class="font-semibold" value="{{ __('Max Response Size') }}" />
                <x-input id="mcp_max_response_size" type="number" class="mt-1 block w-full" wire:model="mcp_max_response_size" min="1024" max="10485760" />
                <small class="text-xs text-muted">Maximum response size in bytes (1KB - 10MB)</small>
                <x-input-error for="mcp_max_response_size" class="mt-2" />
            </div>

            <!-- Log Level -->
            <div class="col-span-6 sm:col-span-3">
                <x-label for="mcp_log_level" class="font-semibold" value="{{ __('Log Level') }}" />
                <select id="mcp_log_level" wire:model="mcp_log_level" class="mt-1 block w-full border-border focus:border-primary focus:ring-primary rounded-md shadow-sm">
                    <option value="error">Error</option>
                    <option value="warning">Warning</option>
                    <option value="info">Info</option>
                    <option value="debug">Debug</option>
                </select>
                <small class="text-xs text-muted">Level of detail for MCP server logs</small>
                <x-input-error for="mcp_log_level" class="mt-2" />
            </div>

            <!-- Logging Enabled -->
            <div x-data="{ isLoggingEnabled: $wire.mcp_logging_enabled }" class="flex items-center justify-between col-span-6">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-surface-fg" id="logging-enabled-label">Enable Logging</span>
                    <span class="text-sm text-muted" id="logging-enabled-description">
                        Log MCP server requests and responses for debugging and auditing.
                    </span>
                </span>

                <button
                    type="button"
                    :class="{ 'bg-primary': isLoggingEnabled, 'bg-surface-3': !isLoggingEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isLoggingEnabled.toString()"
                    aria-labelledby="logging-enabled-label"
                    aria-describedby="logging-enabled-description"
                    @click="isLoggingEnabled = !isLoggingEnabled; $wire.mcp_logging_enabled = isLoggingEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isLoggingEnabled, 'translate-x-0': !isLoggingEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-surface shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>

            <!-- Require Team Context -->
            <div x-data="{ isTeamRequired: $wire.mcp_require_team_context }" class="flex items-center justify-between col-span-6">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-surface-fg" id="team-context-label">Require Team Context</span>
                    <span class="text-sm text-muted" id="team-context-description">
                        Require valid team context for tool execution. When enabled, tools will only access data within the user's current team scope.
                    </span>
                </span>

                <button
                    type="button"
                    :class="{ 'bg-primary': isTeamRequired, 'bg-surface-3': !isTeamRequired }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isTeamRequired.toString()"
                    aria-labelledby="team-context-label"
                    aria-describedby="team-context-description"
                    @click="isTeamRequired = !isTeamRequired; $wire.mcp_require_team_context = isTeamRequired"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isTeamRequired, 'translate-x-0': !isTeamRequired }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-surface shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>

            <!-- CORS Origins -->
            <div class="col-span-6">
                <x-label for="mcp_cors_origins" class="font-semibold" value="{{ __('CORS Origins (Optional)') }}" />
                <textarea id="mcp_cors_origins" class="mt-1 block w-full border border-border rounded-md shadow" rows="3" wire:model="mcp_cors_origins" placeholder="https://example.com"></textarea>
                <small class="text-xs text-muted">
                    One origin per line. Leave blank to use default CORS policy.
                    <br>Example: <code>https://claude.ai</code> or <code>http://localhost:3000</code>
                </small>
                <x-input-error for="mcp_cors_origins" class="mt-2" />
            </div>
        </x-slot>

        <x-slot name="actions">
            <x-action-message class="mr-3" on="saved">
                {{ __('Saved.') }}
            </x-action-message>

            <x-button>
                {{ __('Save Settings') }}
            </x-button>
        </x-slot>
    </x-form-section>

    <!-- Available Tools Section -->
    <x-form-section submit="saveMcpSettings">
        <x-slot name="title">
            {{ __('Available MCP Tools') }}
        </x-slot>

        <x-slot name="description">
            Select which tools should be available to AI assistants through the MCP server.
        </x-slot>
        
        <x-slot name="form">
            <div class="col-span-6 space-y-3">
                @forelse($available_tools as $tool)
                    <div x-data="{ isEnabled: {{ $tool['enabled'] ? 'true' : 'false' }} }" class="flex items-center justify-between p-3 border rounded-lg">
                        <span class="flex flex-grow flex-col">
                            <span class="text-sm font-semibold text-surface-fg">{{ $tool['name'] }}</span>
                            <span class="text-xs text-muted">{{ $tool['description'] }}</span>
                        </span>

                        <button
                            type="button"
                            :class="{ 'bg-primary': isEnabled, 'bg-surface-3': !isEnabled }"
                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-primary focus:ring-offset-2"
                            role="switch"
                            :aria-checked="isEnabled.toString()"
                            @click="isEnabled = !isEnabled; $wire.toggleTool('{{ $tool['name'] }}')"
                        >
                            <span
                                aria-hidden="true"
                                :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-surface shadow ring-0 transition duration-200 ease-in-out"
                            ></span>
                        </button>
                    </div>
                @empty
                    <div class="text-sm text-muted">No tools available</div>
                @endforelse
            </div>
        </x-slot>

        <x-slot name="actions">
            <x-action-message class="mr-3" on="saved">
                {{ __('Saved.') }}
            </x-action-message>

            <x-button>
                {{ __('Save Tool Settings') }}
            </x-button>
        </x-slot>
    </x-form-section>

    <!-- API Endpoint Information -->
    <div class="bg-surface-2 overflow-hidden shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <h3 class="text-lg font-medium leading-6 text-surface-fg mb-4">MCP API Endpoints</h3>
            
            <div class="space-y-3">
                <div>
                    <h4 class="text-sm font-semibold text-surface-fg-soft">SSE Endpoint (GET)</h4>
                    <code class="block mt-1 text-xs bg-surface-2 p-2 rounded">{{ url('/api/mcp/protocol') }}</code>
                    <p class="text-xs text-muted mt-1">Server-Sent Events for real-time streaming</p>
                </div>
                
                <div>
                    <h4 class="text-sm font-semibold text-surface-fg-soft">JSON-RPC Endpoint (POST)</h4>
                    <code class="block mt-1 text-xs bg-surface-2 p-2 rounded">{{ url('/api/mcp/protocol') }}</code>
                    <p class="text-xs text-muted mt-1">Standard JSON-RPC 2.0 requests</p>
                </div>
                
                <div>
                    <h4 class="text-sm font-semibold text-surface-fg-soft">Authentication</h4>
                    <p class="text-xs text-surface-fg-soft mt-1">
                        Bearer token authentication, using a token from
                        <a href="{{ route('api-tokens.index') }}" class="font-semibold text-primary hover:underline">API Tokens</a>.
                        Access is decided by the caller's team and role, not by the token's permissions:
                    </p>
                    <code class="block mt-1 text-xs bg-surface-2 p-2 rounded">Authorization: Bearer YOUR_API_TOKEN</code>
                </div>

                <div>
                    <h4 class="text-sm font-semibold text-surface-fg-soft">Testing Interface</h4>
                    <p class="text-xs text-surface-fg-soft mt-1">
                        Test the MCP server at: <a href="{{ route('utilities.mcp-server') }}" class="text-primary hover:underline">{{ url('/utilities/mcp-server') }}</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>