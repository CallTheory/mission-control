<div class="mt-10">
    <div class="md:grid md:grid-cols-3 md:gap-6">
        <div class="md:col-span-1 flex justify-between px-4 sm:px-0">
            <div class="max-w-xs">
                <h3 class="text-lg font-medium text-gray-900">Tracing</h3>
                <p class="mt-1 text-sm text-muted">
                    Export request, job and query timings to Grafana Tempo over OTLP.
                    Off by default.
                </p>
            </div>
        </div>

        <div class="mt-5 md:mt-0 md:col-span-2">
            <div class="px-4 py-5 sm:p-6 bg-white shadow sm:rounded-lg">

                @if($envOverridden)
                    <x-alert-warning
                        title="Overridden by environment"
                        description="OBSERVABILITY_TRACING_ENABLED is set in this environment and takes
                                     precedence over the toggle below." />
                @endif

                @if($probeStatus === 'reachable')
                    <x-alert-success title="Collector reachable" :description="$probeMessage" />
                @elseif($probeStatus === 'refused')
                    <x-alert-danger title="No collector is listening" :description="$probeMessage" />
                @elseif($probeStatus === 'unauthorized')
                    <x-alert-warning title="Collector rejected the credentials" :description="$probeMessage" />
                @elseif($probeStatus)
                    <x-alert-warning title="Collector check failed" :description="$probeMessage" />
                @endif

                <div class="grid grid-cols-6 gap-4">

                    <div class="col-span-6">
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="enabled"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="ml-2 text-sm text-gray-700">Enable tracing</span>
                        </label>
                    </div>

                    <x-form-field
                        for="endpoint"
                        label="OTLP endpoint"
                        wire:model="endpoint"
                        placeholder="http://localhost:4318"
                        errorFor="endpoint"
                        help="Point this at a collector on this host — normally a Grafana Alloy agent
                              with an otelcol.receiver.otlp block, which forwards to Tempo. A loopback
                              endpoint keeps the export cost negligible; exporting straight to a remote
                              Tempo puts a network round-trip in every sampled request." />

                    <x-form-field for="protocol" label="Protocol" errorFor="protocol">
                        <select wire:model="protocol" id="protocol"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            <option value="http/protobuf">http/protobuf (default)</option>
                            <option value="http/json">http/json (easier to debug)</option>
                        </select>
                    </x-form-field>

                    <x-form-field
                        for="authUsername"
                        label="Auth username"
                        wire:model="authUsername"
                        errorFor="authUsername"
                        help="Grafana Cloud: your instance ID. Leave blank for a local collector." />

                    <x-form-field
                        for="authToken"
                        label="Auth token"
                        type="password"
                        wire:model="authToken"
                        autocomplete="new-password"
                        errorFor="authToken"
                        :help="$hasAuthToken
                            ? 'A token is stored. Leave blank to keep it.'
                            : 'Leave blank for a local collector that needs no authentication.'" />

                    <x-form-field
                        for="serviceName"
                        label="Service name"
                        wire:model="serviceName"
                        placeholder="{{ config('app.name') }}"
                        errorFor="serviceName"
                        help="How this application identifies itself in Tempo." />

                    <x-form-field
                        for="sampleRate"
                        label="Sample rate"
                        type="number" step="0.01" min="0" max="1"
                        wire:model="sampleRate"
                        errorFor="sampleRate"
                        help="0.1 traces one request in ten. The decision is made once per trace, so a
                              queued job is always captured together with the request that dispatched it.
                              To keep every error instead, set this to 1.0 and configure tail sampling
                              in Alloy." />

                    <div class="col-span-6 border-t border-gray-200 pt-4">
                        <label class="flex items-center">
                            <input type="checkbox" wire:model.live="dbSpansEnabled"
                                   class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="ml-2 text-sm text-gray-700">Trace database queries</span>
                        </label>
                        <p class="mt-1 text-sm text-muted">
                            Highest-volume signal by far — one page with an N+1 can emit hundreds of
                            spans. Query text is sanitized and bindings are never recorded.
                        </p>
                    </div>

                    @if($dbSpansEnabled)
                        <x-form-field
                            for="dbSlowQueryMs"
                            label="Slow query threshold (ms)"
                            type="number" min="0" max="60000"
                            wire:model="dbSlowQueryMs"
                            errorFor="dbSlowQueryMs"
                            help="0 records every query. A threshold such as 50 records only slow ones —
                                  useful for outliers, but it hides N+1 patterns made of many fast queries." />
                    @endif

                </div>

                <div class="flex items-center justify-end mt-5 gap-3">
                    <x-action-message class="mr-3" on="saved">Saved.</x-action-message>

                    <button type="button" wire:click="checkEndpoint" wire:loading.attr="disabled"
                            class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 disabled:opacity-50">
                        Check collector
                    </button>

                    <x-button wire:click="save" wire:loading.attr="disabled">Save</x-button>
                </div>

            </div>
        </div>
    </div>
</div>
