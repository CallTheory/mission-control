<x-action-section>
    <x-slot name="title">
        {{ __('Single Sign-On') }}
    </x-slot>

    <x-slot name="description">
        {{ __('For teams whose members will never have an identity provider account.') }}
    </x-slot>

    <x-slot name="content">
        <div x-data="{ isEnabled: $wire.ssoExempt }" class="flex items-center justify-between">
            <span class="flex flex-grow flex-col">
                <span class="text-md font-semibold leading-6 text-surface-fg" id="sso_exempt-label">
                    {{ __('Exempt from single sign-on') }}
                </span>
                <span class="text-sm text-muted mt-1" id="sso_exempt-description">
                    {{ __('Members may sign in with a password even when linked accounts are otherwise forced through the identity provider. They are still covered by the "Require SSO or Two-Factor" policy, so they will be asked to set up two-factor instead.') }}
                </span>
            </span>

            <button type="button"
                    :class="{ 'bg-primary': isEnabled, 'bg-surface-3': !isEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="sso_exempt-label"
                    aria-describedby="sso_exempt-description"
                    wire:click="toggleSsoExemption(); isEnabled = !isEnabled">
                <span aria-hidden="true"
                      :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                      class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-surface shadow ring-0 transition duration-200 ease-in-out"></span>
            </button>
        </div>

        <x-action-message class="mt-3" on="saved">
            {{ __('Saved.') }}
        </x-action-message>
    </x-slot>
</x-action-section>
