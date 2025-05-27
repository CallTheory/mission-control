<x-form-section submit="saveBoardCheckSettings">
    <x-slot name="title">
        {{ __('Board Check Configuration') }}
    </x-slot>

    <x-slot name="description">
        Set the system-level configuration values for the <a class="font-semibold hover:text-indigo-300 transition transform duration-700 ease-in-out" href="/utilities/board-check">Board Check Utility</a> functionality.
    </x-slot>

    <x-slot name="form">

        <div class="col-span-6 sm:col-span-4">
            <x-label for="board_check_starting_msgId" value="{{ __('Starting Intelligent Series msgId') }}" />
            <x-input id="board_check_starting_msgId" type="text" class="mt-1 block w-full " wire:model.defer="state.board_check_starting_msgId" />
            <small class="text-xs text-gray-400 0">After you initially set this, the system will automatically update the <code class="rounded inline text-gray-200 bg-gray-700   px-1 py-0.5">msgId</code> as records are exported. (It's still safe to override it here.)</small>
            <x-input-error for="state.board_check_starting_msgId" class="mt-2" />
        </div>

        <div class="col-span-6 sm:col-span-4">
            <x-label for="board_check_people_praise_export_method" value="{{ __('People Praise Export Method') }}" />
            <select id="board_check_people_praise_export_method"
                    class="mt-1 block w-full rounded shadow border border-gray-300"
                    wire:model.defer="state.board_check_people_praise_export_method">
                <option value="file">File</option>
                <option value="api">API</option>
            </select>
            <small class="text-xs text-gray-400 0">
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
