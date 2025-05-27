<x-form-section submit="saveAccounts">
    <x-slot name="title">
        {{ __('Allowed Accounts') }}
    </x-slot>

    <x-slot name="description">
        {{ __('The accounts that are available for this team. Leave blank to include every account.') }}
    </x-slot>

    <x-slot name="form">
        <!-- Team Name -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="name" value="{{ __('Allowed Accounts') }}" />

            <textarea id="allowed_accounts"
                         type="text"
                         class="border border-gray-300     focus:border-indigo-300 focus:ring focus:ring-indigo-200 rounded-md shadow  mt-1 block w-full "
                         wire:model.live="state.allowed_accounts"
                         @if(!Gate::check('update', $team)) disabled @endif></textarea>
            <small class="0">Enter account numbers or ranges separated by a comma or new-line. (1,2,3-6,17)</small>
            <x-input-error for="allowed_accounts" class="mt-2" />
            <div class="rounded my-2 bg-indigo-50 p-2 text-sm text-indigo-700">
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
