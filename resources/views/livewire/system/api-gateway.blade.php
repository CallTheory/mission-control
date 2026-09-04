<x-form-section submit="saveAPISecuritySettings">
    <x-slot name="title">
        {{ __('API Gateway Security') }}
    </x-slot>

    <x-slot name="description">
        Set the system-level security requirements for accessing the Mission Control API Gateway.
    </x-slot>
    <x-slot name="form">
        <div x-data="{ isEnabled: $wire.require_api_tokens }" class="flex items-center justify-between col-span-3">
            <span class="flex flex-grow flex-col">
                <span class="text-md font-semibold leading-6 text-surface-fg" id="require-tokens-enabled-label">Require API Tokens</span>
                <span class="text-sm text-muted" id="require-tokens-description">
                    Require the use of an <code class="font-semibold">api_key</code> as a <code class="font-semibold">GET</code>, <code class="font-semibold">POST</code>, or <code class="font-semibold">Bearer &lt;api_key&gt;</code> in Authorization headers.
                </span>
            </span>

            <button
                type="button"
                :class="{ 'bg-primary': isEnabled, 'bg-surface-3': !isEnabled }"
                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-primary focus:ring-offset-2"
                role="switch"
                :aria-checked="isEnabled.toString()"
                aria-labelledby="require-tokens-enabled-label"
                aria-describedby="require-tokens-description"
                @click="$wire.toggleApiTokens(); isEnabled = !isEnabled"
            >
                <span
                    aria-hidden="true"
                    :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                    class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-surface shadow ring-0 transition duration-200 ease-in-out"
                ></span>
            </button>
        </div>
        <div class="text-xs text-primary col-span-6">
            The <strong>Require API Tokens</strong> toggle applies <i>immediately</i> when switched
        </div>
        <hr class="col-span-6 my-4 border border-border" />
        <div class="col-span-6 sm:col-span-4">
            <x-label for="api_whitelist" class="font-semibold" value="{{ __('API Whitelist') }}" />
            <textarea id="api_whitelist" class="mt-1 block w-full border border-border rounded-md shadow " wire:model="api_whitelist"></textarea>
            <small class="text-xs text-muted">
                One IP address or slash-formatted subnet per-line (i.e., <code>1.2.3.4</code> or <code>1.2.3.0/24</code>)
                <br>
                Leave blank to disable the API whitelist.
                <br>
                Your IP address is: <code class="bg-surface-3 rounded px-1 py-0.5">{{ request()->ip() }}</code>
            </small>

            <x-input-error for="api_whitelist" class="mt-2" />
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
