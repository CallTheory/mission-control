<div class="max-w-7xl mx-auto sm:px-6 lg:px-8 mt-6">
    <div class="md:grid md:grid-cols-3 md:gap-6">
        <div class="md:col-span-1 flex justify-between px-4 sm:px-0">
            <div class="max-w-xs">
                <h3 class="text-lg font-medium text-surface-fg">Expiry Alerts</h3>
                <p class="mt-1 text-sm text-muted">
                    One email each time a credential crosses
                    {{ implode(', ', array_slice($this->thresholds, 0, -1)) }} days remaining, and
                    once more when it expires. Never repeated for the same threshold, and never
                    sent for an acknowledged credential.
                </p>
            </div>
        </div>

        <div class="mt-5 md:mt-0 md:col-span-2">
            <div class="px-4 py-5 sm:p-6 bg-surface shadow sm:rounded-lg">
                <div class="grid grid-cols-6 gap-4">

                    <div class="col-span-6">
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="enabled"
                                   class="rounded border-border text-primary shadow-sm focus:ring-primary" />
                            <span class="ml-2 text-sm text-surface-fg-soft">Send expiry alerts</span>
                        </label>
                        <x-input-error for="enabled" class="mt-2" />
                    </div>

                    <x-form-field
                        for="recipients"
                        label="Recipients"
                        errorFor="recipients"
                        help="Separate addresses with commas or new lines.">
                        <textarea id="recipients" rows="3" wire:model="recipients"
                                  class="mt-1 block w-full border-border bg-surface text-surface-fg focus:border-primary focus:ring-primary rounded-md shadow-sm"
                                  placeholder="identity-team@example.com, oncall@example.com"></textarea>
                        <x-input-error for="recipients" class="mt-2" />
                    </x-form-field>

                </div>

                <div class="flex items-center justify-end mt-5 gap-3">
                    <x-action-message class="mr-3" on="saved">Saved.</x-action-message>
                    <x-button wire:click="save" wire:loading.attr="disabled">Save</x-button>
                </div>
            </div>
        </div>
    </div>
</div>
