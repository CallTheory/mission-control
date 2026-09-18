<x-form-section submit="saveAccounts">
    <x-slot name="title">
        {{ __('Allowed Accounts') }}
    </x-slot>

    <x-slot name="description">
        {{ __('The accounts that are available for this team.') }}

        <p class="text-sm py-4">A team must either list the accounts it may see, or be
        marked as seeing every account. A team with neither is treated as unconfigured,
        and call data is withheld from it.</p>
    </x-slot>

    <x-slot name="form">
        <!-- Team Name -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="name" value="{{ __('Allowed Accounts') }}" />

            <textarea id="allowed_accounts"
                         type="text"
                         class="border border-border focus:border-primary focus:ring focus:ring-primary rounded-md shadow mt-1 block w-full "
                         wire:model.live="state.allowed_accounts"
                         @if(!Gate::check('update', $team) || ($state['unrestricted_accounts'] ?? false)) disabled @endif></textarea>
            <small class="0">Enter account numbers or ranges separated by a comma or new-line. (1,2,3-6,17)</small>
            <x-input-error for="state.allowed_accounts" class="mt-2" />

            <label for="unrestricted_accounts" class="flex items-start mt-4">
                <input id="unrestricted_accounts"
                       type="checkbox"
                       class="rounded border-border text-primary shadow-sm focus:border-primary focus:ring focus:ring-primary focus:ring-opacity-50 mt-0.5"
                       wire:model.live="state.unrestricted_accounts"
                       @if(!Gate::check('update', $team)) disabled @endif>
                <span class="ms-2 text-sm">
                    {{ __('This team may see every account') }}
                    <span class="block text-muted">Ticking this clears the list above; the
                    two cannot both apply.</span>
                </span>
            </label>
            <div class="rounded my-2 bg-primary-soft p-2 text-sm text-primary-soft-fg">
                Restrictions apply against the <a class="hover:underline font-semibold" href="/utilities/call-lookup">Call Log/Lookup</a> and <a class="hover:underline font-semibold" href="/accounts">Account</a> list.
                All other utilities are unrestricted by account number.
            </div>
        </div>
    </x-slot>

    @if (Gate::check('update', $team))
        <x-slot name="actions">
            <x-action-message class="mr-3 " on="saved">
                {{ __('Saved.') }}
            </x-action-message>

            <x-button>
                {{ __('Save') }}
            </x-button>
        </x-slot>
    @endif
</x-form-section>
