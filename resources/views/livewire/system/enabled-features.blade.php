<x-form-section submit="updateSystemFeatures">
    <x-slot name="title">
        {{ __('System Features') }}
    </x-slot>

    <x-slot name="description">
        Please enable/disable the features you wish to utilize. Disabling a feature will prevent it from being used and can help with system resource management.
    </x-slot>

    <x-slot name="form">

        <!-- Transcription -->
        <div class="col-span-6 sm:col-span-4">
            <div x-data="{ isEnabled: $wire.transcription }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="transcription-enabled-label">Transcription</span>
                    <span class="text-sm text-gray-500 pr-2" id="transcription-description">
                        Enable or disable the use of <strong>whisper.cpp</strong> for translating and transcribing recordings.
                    </span>
                </span>
                <button
                    type="button"
                    :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="transcription-enabled-label"
                    aria-describedby="transcription-description"
                    @click="$wire.toggleTranscriptionFeature(); isEnabled = !isEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>
        <!-- End Transcription -->


        <!-- Screen Captures -->
        <div class="col-span-6 sm:col-span-4">
            <div x-data="{ isEnabled: $wire.screencaptures }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="screencaptures-enabled-label">Screen Captures</span>
                    <span class="text-sm text-gray-500 pr-2" id="screencaptures-description">
                        Enable or disable the use of <strong>ffmpeg</strong> for video playback of screen capture recordings.
                    </span>
                </span>
                <button
                    type="button"
                    :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="screencaptures-enabled-label"
                    aria-describedby="screencaptures-description"
                    @click="$wire.toggleScreencapturesFeature(); isEnabled = !isEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>
        <!-- End Screen Captures -->

        <!-- ModelContextProtocol -->
        <div class="col-span-6 sm:col-span-4">
            <div x-data="{ isEnabled: $wire.mcp }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="mcp-enabled-label">MCP Server <span class="text-white text-xs font-normal bg-indigo-500 rounded-lg px-2 py-0.5">beta</span></span>
                    <span class="text-sm text-gray-500 pr-2" id="mcp-description">
                        Enable the use of <strong>Model Context Protocol (MCP)</strong> for use in your favorite AI stack.
                    </span>
                </span>
                <button
                    type="button"
                    :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="mcp-enabled-label"
                    aria-describedby="mcp-description"
                    @click="$wire.toggleMcpFeature(); isEnabled = !isEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>
            </div>
        </div>
        <!-- End ModelContextProtocol -->

    </x-slot>

    <x-slot name="actions">
        &nbsp;
        <x-action-message class="mr-3 " on="saved">
            {{ __('Saved.') }}
        </x-action-message>
    </x-slot>
</x-form-section>
