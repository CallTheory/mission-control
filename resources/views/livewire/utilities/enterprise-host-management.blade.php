<div>
    <x-page-header title="Enterprise Host Management">
        <x-slot name="actions">
            <x-button wire:click="createHost">
                Create Enterprise Host
            </x-button>
        </x-slot>
    </x-page-header>

    {{ $this->table }}

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
