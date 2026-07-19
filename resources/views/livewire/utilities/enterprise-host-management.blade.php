<div>
    <x-flash />

    <x-page-header title="Enterprise Host Management">
        <x-slot name="actions">
            <x-button wire:click="createHost">
                Create Enterprise Host
            </x-button>
        </x-slot>
    </x-page-header>

    {{-- Filters --}}
    <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <x-search-input wire-model="search" placeholder="Search by name or sender ID..." />

        <x-filter-select for="filterEnabled" label="Status" wire-model="filterEnabled" placeholder="All">
            <option value="1">Enabled</option>
            <option value="0">Disabled</option>
        </x-filter-select>

        <x-filter-select for="filterTeam" label="Team" wire-model="filterTeam" :options="$teams"
            placeholder="All Teams" option-value="id" option-label="name" />
    </div>

    {{-- Hosts Table --}}
    <x-table>
        <x-slot name="head">
            <x-table.heading>Name</x-table.heading>
            <x-table.heading>Sender ID</x-table.heading>
            <x-table.heading>Team</x-table.heading>
            <x-table.heading>Messages</x-table.heading>
            <x-table.heading>Last Activity</x-table.heading>
            <x-table.heading>Status</x-table.heading>
            <x-table.heading>Actions</x-table.heading>
        </x-slot>

        @forelse($hosts as $host)
            <x-table.row>
                <x-table.cell>
                    <div class="text-sm font-medium text-surface-fg">{{ $host->name }}</div>
                    @if($host->phone_numbers && count($host->phone_numbers) > 0)
                        <div class="text-xs text-muted">📱 {{ count($host->phone_numbers) }} number(s)</div>
                    @endif
                </x-table.cell>
                <x-table.cell>
                    <code class="bg-surface-2 px-2 py-1 rounded">{{ $host->senderID }}</code>
                </x-table.cell>
                <x-table.cell muted>{{ $host->team ? $host->team->name : 'Global' }}</x-table.cell>
                <x-table.cell>{{ number_format($host->messages_count) }}</x-table.cell>
                <x-table.cell muted>{{ $host->last_message_at ? $host->last_message_at->diffForHumans() : 'Never' }}</x-table.cell>
                <x-table.cell>
                    <button wire:click="toggleEnabled({{ $host->id }})">
                        <x-badge :color="$host->enabled ? 'green' : 'red'">{{ $host->enabled ? 'Enabled' : 'Disabled' }}</x-badge>
                    </button>
                </x-table.cell>
                <x-table.cell>
                    <button wire:click="editHost({{ $host->id }})" class="text-primary hover:underline mr-3">Edit</button>
                    <button wire:click="viewMessages({{ $host->id }})" class="text-primary hover:underline mr-3">Messages</button>
                    @if($host->messages_count == 0)
                        <button wire:click="deleteHost({{ $host->id }})"
                                wire:confirm="Are you sure you want to delete this host?"
                                class="text-danger hover:underline">Delete</button>
                    @endif
                </x-table.cell>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7">No enterprise hosts found.</x-table.empty>
        @endforelse

        <x-slot name="footer">{{ $hosts->links() }}</x-slot>
    </x-table>

    {{-- Create/Edit Modal --}}
    @if($showModal)
        <x-dialog-modal wire:model.live="showModal" maxWidth="2xl">
            <x-slot name="title">
                {{ $editingHost ? 'Edit Enterprise Host' : 'Create Enterprise Host' }}
            </x-slot>

            <x-slot name="content">
                <div class="grid grid-cols-1 gap-4">
                    <x-form-field for="name" label="Name" error-for="name" wire:model="name" required col-span="" />

                    <x-form-field for="senderID" label="Sender ID" error-for="senderID"
                        help="This is the unique identifier used in WCTP messages" col-span="">
                        <x-input id="senderID" type="text" wire:model="senderID" required
                            :readonly="(bool) $editingHost"
                            class="mt-1 block w-full {{ $editingHost ? 'bg-surface-2' : '' }}" />
                    </x-form-field>

                    <x-form-field for="securityCode"
                        label="{{ 'Security Code'.($editingHost ? ' (leave blank to keep existing)' : '') }}"
                        error-for="securityCode" help="Used for authentication in WCTP requests" col-span="">
                        <div class="mt-1 flex rounded-md shadow-sm">
                            <x-input id="securityCode" type="text" class="flex-1 block w-full rounded-r-none"
                                wire:model="securityCode" :required="! (bool) $editingHost" />
                            <button type="button" wire:click="generateSecurityCode"
                                class="inline-flex items-center px-3 rounded-r-md border border-l-0 border-border bg-surface-2 text-muted text-sm hover:bg-border">
                                Generate
                            </button>
                        </div>
                    </x-form-field>

                    <x-form-field for="callback_url" label="Callback URL (Optional)" type="url" error-for="callback_url"
                        wire:model="callback_url" placeholder="https://example.com/wctp/receive"
                        help="URL to forward inbound SMS messages to this host" col-span="" />

                    {{-- Phone Numbers --}}
                    <div>
                        <x-label value="Phone Numbers" class="mb-2" />

                        @if(count($phoneNumbers) > 0)
                            <div class="mb-3 space-y-2">
                                @foreach($phoneNumbers as $index => $phoneNumber)
                                    <div class="flex items-center justify-between bg-surface-2 px-3 py-2 rounded">
                                        <span class="text-sm font-mono text-surface-fg">{{ $phoneNumber }}</span>
                                        <button type="button" wire:click="removePhoneNumber({{ $index }})" class="text-danger hover:underline">Remove</button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <div class="flex gap-2">
                            <x-input type="tel" class="flex-1" wire:model="newPhoneNumber" placeholder="+1234567890 or 1234567890" />
                            <x-button type="button" wire:click="addPhoneNumber">Add</x-button>
                        </div>
                        <x-input-error for="newPhoneNumber" class="mt-2" />
                        <x-input-error for="phoneNumbers" class="mt-2" />
                        <p class="mt-1 text-xs text-muted">Phone numbers mapped to this host for inbound/outbound routing</p>
                    </div>

                    <x-form-field for="team_id" label="Team (Optional)" error-for="team_id"
                        help="Associate this host with a specific team" col-span="">
                        <select id="team_id" wire:model="team_id"
                            class="mt-1 block w-full rounded-md border-border bg-surface text-surface-fg shadow-sm focus:border-primary focus:ring focus:ring-primary/30">
                            <option value="">Global (No Team)</option>
                            @foreach($teams as $team)
                                <option value="{{ $team->id }}">{{ $team->name }}</option>
                            @endforeach
                        </select>
                    </x-form-field>

                    <x-toggle wire-model="enabled" label="Enabled" help="Disabled hosts will reject all incoming messages" />
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="resetForm" wire:loading.attr="disabled">
                    Cancel
                </x-secondary-button>

                <x-button class="ml-2" wire:click="save" wire:loading.attr="disabled">
                    {{ $editingHost ? 'Update' : 'Create' }}
                </x-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
