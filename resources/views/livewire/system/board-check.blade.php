<x-form-section submit="saveBoardCheckSettings">
    <x-slot name="title">
        {{ __('Board Check Configuration') }}
    </x-slot>

    <x-slot name="description">
        Set the system-level configuration values for the <a class="font-semibold hover:text-primary transition transform duration-700 ease-in-out" href="/utilities/board-check">Board Check Utility</a> functionality.
    </x-slot>

    <x-slot name="form">

        <div class="col-span-6 sm:col-span-4">
            <x-label for="board_check_starting_msgId" value="{{ __('Starting Intelligent Series msgId') }}" />
            <x-input id="board_check_starting_msgId" type="text" class="mt-1 block w-full " wire:model="state.board_check_starting_msgId" />
            <small class="text-xs text-muted">After you initially set this, the system will automatically update the <code class="rounded inline text-subtle bg-surface-inverse px-1 py-0.5">msgId</code> as records are exported. (It's still safe to override it here.)</small>
            <x-input-error for="state.board_check_starting_msgId" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-label for="board_check_people_praise_export_method" value="{{ __('People Praise Export Method') }}" />
            <select id="board_check_people_praise_export_method"
                    class="mt-1 block w-full rounded shadow border border-border"
                    wire:model="state.board_check_people_praise_export_method">
                <option value="file">File</option>
                <option value="api">API</option>
            </select>
            <small class="text-xs text-muted">
                The <strong>File</strong> saves to a CSV file on the server to be processed by People Praise. The <strong>API</strong> option requires enabling the People Praise API under <a class="font-semibold hover:underline" href="/system/integrations">System &rarr; Integrations</a>
            </small>
            <x-input-error for="state.board_check_people_praise_export_method" class="mt-2" />
        </div>

    </x-slot>

    <x-slot name="actions">
        <x-action-message class="mr-3 " on="saved">
            {{ __('Saved.') }}
        </x-action-message>

        <x-button>
            {{ __('Save') }}
        </x-button>
    </x-slot>
</x-form-section>
