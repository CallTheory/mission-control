<div>
    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('message') }}</span>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    {{-- Header and Create Button --}}
    <div class="mb-6 flex justify-between items-center">
        <h2 class="text-2xl font-semibold text-gray-900">Enterprise Host Management</h2>
        <button wire:click="createHost" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
            <svg class="inline-block w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Create Enterprise Host
        </button>
    </div>

    {{-- Filters --}}
    <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
            <input wire:model.live="search" type="text" id="search" 
                   placeholder="Search by name or sender ID..."
                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        
        <div>
            <label for="filterEnabled" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select wire:model.live="filterEnabled" id="filterEnabled" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All</option>
                <option value="1">Enabled</option>
                <option value="0">Disabled</option>
            </select>
        </div>
        
        <div>
            <label for="filterTeam" class="block text-sm font-medium text-gray-700 mb-1">Team</label>
            <select wire:model.live="filterTeam" id="filterTeam" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Teams</option>
                @foreach($teams as $team)
                    <option value="{{ $team->id }}">{{ $team->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Hosts Table --}}
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sender ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Team</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Messages</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Activity</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($hosts as $host)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-medium text-gray-900">{{ $host->name }}</div>
                            @if($host->phone_numbers && count($host->phone_numbers) > 0)
                                <div class="text-xs text-gray-500">📱 {{ count($host->phone_numbers) }} number(s)</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <code class="bg-gray-100 px-2 py-1 rounded">{{ $host->senderID }}</code>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $host->team ? $host->team->name : 'Global' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ number_format($host->messages_count) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $host->last_message_at ? $host->last_message_at->diffForHumans() : 'Never' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <button wire:click="toggleEnabled({{ $host->id }})" 
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                           {{ $host->enabled ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $host->enabled ? 'Enabled' : 'Disabled' }}
                            </button>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <button wire:click="editHost({{ $host->id }})" 
                                    class="text-indigo-600 hover:text-indigo-900 mr-3">
                                Edit
                            </button>
                            <button wire:click="viewMessages({{ $host->id }})" 
                                    class="text-blue-600 hover:text-blue-900 mr-3">
                                Messages
                            </button>
                            @if($host->messages_count == 0)
                                <button wire:click="deleteHost({{ $host->id }})" 
                                        wire:confirm="Are you sure you want to delete this host?"
                                        class="text-red-600 hover:text-red-900">
                                    Delete
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                            No enterprise hosts found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $hosts->links() }}
    </div>

    {{-- Create/Edit Modal --}}
    @if($showCreateModal || $showEditModal)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 overflow-y-auto h-full w-full z-50">
            <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <h3 class="text-lg font-medium leading-6 text-gray-900 mb-4">
                        {{ $editingHost ? 'Edit Enterprise Host' : 'Create Enterprise Host' }}
                    </h3>
                    
                    <form wire:submit="save">
                        <div class="grid grid-cols-1 gap-4">
                            {{-- Name --}}
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                                <input wire:model="name" type="text" id="name" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            {{-- Sender ID --}}
                            <div>
                                <label for="senderID" class="block text-sm font-medium text-gray-700">Sender ID</label>
                                <input wire:model="senderID" type="text" id="senderID" required
                                       {{ $editingHost ? 'readonly' : '' }}
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm {{ $editingHost ? 'bg-gray-100' : '' }}">
                                @error('senderID') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                <p class="mt-1 text-xs text-gray-500">This is the unique identifier used in WCTP messages</p>
                            </div>

                            {{-- Security Code --}}
                            <div>
                                <label for="securityCode" class="block text-sm font-medium text-gray-700">
                                    Security Code {{ $editingHost ? '(leave blank to keep existing)' : '' }}
                                </label>
                                <div class="mt-1 flex rounded-md shadow-sm">
                                    <input wire:model="securityCode" type="text" id="securityCode" 
                                           {{ !$editingHost ? 'required' : '' }}
                                           class="flex-1 block w-full rounded-none rounded-l-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <button type="button" wire:click="generateSecurityCode"
                                            class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-gray-300 bg-gray-50 text-gray-500 text-sm hover:bg-gray-100">
                                        Generate
                                    </button>
                                </div>
                                @error('securityCode') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                <p class="mt-1 text-xs text-gray-500">Used for authentication in WCTP requests</p>
                            </div>

                            {{-- Callback URL (Optional) --}}
                            <div>
                                <label for="callback_url" class="block text-sm font-medium text-gray-700">Callback URL (Optional)</label>
                                <input wire:model="callback_url" type="url" id="callback_url"
                                       placeholder="https://example.com/wctp/receive"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('callback_url') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                <p class="mt-1 text-xs text-gray-500">URL to forward inbound SMS messages to this host</p>
                            </div>

                            {{-- Phone Numbers --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Numbers</label>
                                
                                {{-- Existing Phone Numbers --}}
                                @if(count($phoneNumbers) > 0)
                                    <div class="mb-3 space-y-2">
                                        @foreach($phoneNumbers as $index => $phoneNumber)
                                            <div class="flex items-center justify-between bg-gray-50 px-3 py-2 rounded">
                                                <span class="text-sm font-mono text-gray-900">{{ $phoneNumber }}</span>
                                                <button type="button" wire:click="removePhoneNumber({{ $index }})" 
                                                        class="text-red-600 hover:text-red-800">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                
                                {{-- Add New Phone Number --}}
                                <div class="flex gap-2">
                                    <input wire:model="newPhoneNumber" type="tel" 
                                           placeholder="+1234567890 or 1234567890"
                                           class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <button type="button" wire:click="addPhoneNumber" 
                                            class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500">
                                        Add
                                    </button>
                                </div>
                                @error('newPhoneNumber') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                @error('phoneNumbers') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                @error('phoneNumbers.*') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                <p class="mt-1 text-xs text-gray-500">Phone numbers mapped to this host for inbound/outbound routing</p>
                            </div>

                            {{-- Team --}}
                            <div>
                                <label for="team_id" class="block text-sm font-medium text-gray-700">Team (Optional)</label>
                                <select wire:model="team_id" id="team_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Global (No Team)</option>
                                    @foreach($teams as $team)
                                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                                    @endforeach
                                </select>
                                @error('team_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                <p class="mt-1 text-xs text-gray-500">Associate this host with a specific team</p>
                            </div>

                            {{-- Enabled --}}
                            <div>
                                <label class="flex items-center">
                                    <input wire:model="enabled" type="checkbox" 
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <span class="ml-2 text-sm text-gray-700">Enabled</span>
                                </label>
                                <p class="mt-1 text-xs text-gray-500">Disabled hosts will reject all incoming messages</p>
                            </div>
                        </div>

                        {{-- Modal Actions --}}
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" 
                                    wire:click="resetForm"
                                    @click="$wire.showCreateModal = false; $wire.showEditModal = false"
                                    class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                Cancel
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                {{ $editingHost ? 'Update' : 'Create' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>