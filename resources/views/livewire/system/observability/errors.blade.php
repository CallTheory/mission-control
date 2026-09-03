<div>
    <div class="md:grid md:grid-cols-3 md:gap-6">
        <div class="md:col-span-1 flex justify-between px-4 sm:px-0">
            <div class="max-w-xs">
                <h3 class="text-lg font-medium text-gray-900">Error Reporting</h3>
                <p class="mt-1 text-sm text-muted">
                    Send unhandled exceptions to a GlitchTip or Sentry instance. Off by
                    default; nothing leaves this application until you enable it.
                </p>
            </div>
        </div>

        <div class="mt-5 md:mt-0 md:col-span-2">
            <div class="px-4 py-5 sm:p-6 bg-white shadow sm:rounded-lg">

                @if($envOverridden)
                    <x-alert-warning
                        title="Overridden by environment"
                        description="OBSERVABILITY_ERRORS_ENABLED is set in this environment and takes
                                     precedence over the toggle below. Unset it to control this from here." />
                @endif

                @if($testResult)
                    <x-alert-success title="Connection succeeded" :description="$testResult" />
                @endif

                @if($testError)
                    <x-alert-danger title="Connection failed" :description="$testError" />
                @endif

                <div class="grid grid-cols-6 gap-4">

                    <div class="col-span-6">
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="enabled"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="ml-2 text-sm text-gray-700">Enable exception reporting</span>
                        </label>
                        <x-input-error for="enabled" class="mt-2" />
                    </div>

                    <x-form-field
                        for="dsn"
                        label="DSN"
                        type="password"
                        wire:model="dsn"
                        autocomplete="new-password"
                        errorFor="dsn"
                        :help="$hasDsn
                            ? 'Currently set to '.$dsnPreview.'. Leave blank to keep it.'
                            : 'For example https://key@glitchtip.example.com/3'" />

                    <x-form-field
                        for="environment"
                        label="Environment"
                        wire:model="environment"
                        placeholder="{{ config('app.env') }}"
                        errorFor="environment"
                        help="Groups events in GlitchTip. Defaults to the application environment." />

                    <x-form-field
                        for="release"
                        label="Release"
                        wire:model="release"
                        errorFor="release"
                        help="Optional. A version or commit SHA, so regressions can be traced to a deploy." />

                    <x-form-field
                        for="sampleRate"
                        label="Sample rate"
                        type="number"
                        step="0.01"
                        min="0"
                        max="1"
                        wire:model="sampleRate"
                        errorFor="sampleRate"
                        help="1.0 reports every exception. Lower it only if volume becomes a problem." />

                    @if($lastTestAt)
                        <div class="col-span-6">
                            <p class="text-sm text-muted">
                                Last test: <span class="font-medium">{{ $lastTestStatus }}</span> ({{ $lastTestAt }})
                            </p>
                        </div>
                    @endif

                </div>

                <div class="flex items-center justify-end mt-5 gap-3">
                    <x-action-message class="mr-3" on="saved">Saved.</x-action-message>

                    <button type="button" wire:click="sendTestEvent" wire:loading.attr="disabled"
                            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 disabled:opacity-50">
                        Send test event
                    </button>

                    <x-button wire:click="save" wire:loading.attr="disabled">Save</x-button>
                </div>

            </div>
        </div>
    </div>
</div>
