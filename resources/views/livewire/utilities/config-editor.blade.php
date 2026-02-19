@assets
    @vite('resources/js/config-editor.js')
@endassets
<div class="w-full">

    @if ($errorMessage)
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline">{{ $errorMessage }}</span>
        </div>
    @endif

    <div class="block space-y-2 w-full py-4">
        <div class="px-4">
            <h1 class="text-base font-semibold leading-6 text-gray-900">Amtelco Config Editor</h1>
            <p class="mt-2 text-sm text-gray-700">
                Decrypt and encrypt Amtelco configuration data and encrypted schedules using TripleDES.
            </p>
        </div>

        {{-- Database Load Panel --}}
        @if ($databaseLoaded)
        <div class="px-4 pt-4">
            <x-label class="font-semibold" value="{{ __('Load from Database') }}" />
            <div class="mt-2 space-y-4">
                    {{-- sysConfig --}}
                    @if (!empty($sysConfigs))
                        <div class="border border-gray-200 rounded p-3">
                            <h3 class="text-sm font-semibold text-gray-700 mb-2">sysConfig</h3>
                            <div class="flex gap-2">
                                @if (!empty($sysConfigs['Config']))
                                    <x-button wire:click="loadSysConfig('Config')" wire:loading.attr="disabled" class="text-xs">
                                        Load Config
                                    </x-button>
                                @endif
                                @if (!empty($sysConfigs['Config2']))
                                    <x-button wire:click="loadSysConfig('Config2')" wire:loading.attr="disabled" class="text-xs">
                                        Load Config2
                                    </x-button>
                                @endif
                            </div>
                        </div>
                    @endif

                    {{-- schSchedule --}}
                    @if (!empty($scheduleRecords))
                        <div class="border border-gray-200 rounded p-3" x-data="{ open: false }">
                            <button type="button" @click="open = !open" class="flex items-center justify-between w-full text-left">
                                <h3 class="text-sm font-semibold text-gray-700">schSchedule Records ({{ count($scheduleRecords) }})</h3>
                                <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-gray-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </button>
                            <div x-show="open" x-collapse x-cloak class="mt-2">
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-3 py-2 text-left font-medium text-gray-600">schId</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-600">Scheduled</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-600">Action</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-600"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($scheduleRecords as $record)
                                            <tr class="border-t border-gray-100 {{ $activeSchId === $record['schId'] ? 'bg-blue-50' : '' }}">
                                                <td class="px-3 py-2">{{ $record['schId'] }}</td>
                                                <td class="px-3 py-2">{{ $record['Scheduled'] }}</td>
                                                <td class="px-3 py-2">{{ $record['Action'] }}</td>
                                                <td class="px-3 py-2">
                                                    <x-button wire:click="loadScheduleRecord({{ $record['schId'] }})" wire:loading.attr="disabled" class="text-xs">
                                                        Load
                                                    </x-button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            </div>
                        </div>
                    @endif

                    {{-- cltEmailAccounts --}}
                    @if (!empty($emailAccounts))
                        <div class="border border-gray-200 rounded p-3" x-data="{ open: false }">
                            <button type="button" @click="open = !open" class="flex items-center justify-between w-full text-left">
                                <h3 class="text-sm font-semibold text-gray-700">cltEmailAccounts ({{ count($emailAccounts) }})</h3>
                                <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-gray-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </button>
                            <div x-show="open" x-collapse x-cloak class="mt-2">
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="px-3 py-2 text-left font-medium text-gray-600">ID</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-600">cltID</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-600">Name</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-600">Description</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-600"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($emailAccounts as $account)
                                            <tr class="border-t border-gray-100 {{ $activeEmailAccountId === $account['ID'] ? 'bg-blue-50' : '' }}">
                                                <td class="px-3 py-2">{{ $account['ID'] }}</td>
                                                <td class="px-3 py-2">{{ $account['cltID'] }}</td>
                                                <td class="px-3 py-2">{{ $account['Name'] }}</td>
                                                <td class="px-3 py-2">{{ $account['Description'] }}</td>
                                                <td class="px-3 py-2">
                                                    <x-button wire:click="loadEmailAccount({{ $account['ID'] }})" wire:loading.attr="disabled" class="text-xs">
                                                        Load
                                                    </x-button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            </div>
                        </div>
                    @endif
            </div>
        </div>
        @endif

        {{-- Tabbed Editor / Encrypted Input --}}
        <div class="px-4 pt-4" x-data="{ activeTab: 'editor' }">
            {{-- Tab Headers --}}
            <div class="flex border-b border-gray-300">
                <button
                    type="button"
                    @click="activeTab = 'editor'"
                    :class="activeTab === 'editor' ? 'border-b-2 border-indigo-500 text-indigo-600 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2 text-sm focus:outline-none"
                >
                    Content Editor
                </button>
                <button
                    type="button"
                    @click="activeTab = 'encrypted'"
                    :class="activeTab === 'encrypted' ? 'border-b-2 border-indigo-500 text-indigo-600 font-semibold' : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2 text-sm focus:outline-none"
                >
                    Encrypted Data
                </button>
            </div>

            {{-- Editor Tab --}}
            <div x-show="activeTab === 'editor'">
                <div wire:ignore>
                    <div
                        x-data="{
                            editor: null,
                            init() {
                                if (window.initConfigEditor) {
                                    this.editor = window.initConfigEditor(this.$refs.editorContainer, @this);
                                }

                                Livewire.on('xmlUpdated', (event) => {
                                    if (this.editor && event.xml !== undefined) {
                                        window.setConfigEditorContent(this.editor, event.xml);
                                    }
                                });
                            }
                        }"
                        x-init="init()"
                    >
                        <div x-ref="editorContainer" class="mt-1 border border-gray-300 rounded-md overflow-hidden" style="min-height: 400px;"></div>
                    </div>
                </div>

                <div class="mt-2 flex gap-2 items-center">
                    <x-button wire:click="encrypt" wire:loading.attr="disabled">
                        Encrypt
                    </x-button>

                    @if ($activeSchId !== null)
                        <x-button
                            wire:click="saveToSchedule"
                            wire:loading.attr="disabled"
                            wire:confirm="Are you sure you want to save back to schSchedule record #{{ $activeSchId }}?"
                            class="bg-green-600 hover:bg-green-700"
                        >
                            <span wire:loading wire:target="saveToSchedule">Saving...</span>
                            <span wire:loading.remove wire:target="saveToSchedule">Save Back to Database (schId: {{ $activeSchId }})</span>
                        </x-button>
                    @endif

                    @if ($activeEmailAccountId !== null)
                        <x-button
                            wire:click="saveToEmailAccount"
                            wire:loading.attr="disabled"
                            wire:confirm="Are you sure you want to save back to cltEmailAccounts record #{{ $activeEmailAccountId }}?"
                            class="bg-green-600 hover:bg-green-700"
                        >
                            <span wire:loading wire:target="saveToEmailAccount">Saving...</span>
                            <span wire:loading.remove wire:target="saveToEmailAccount">Save Back to Database (ID: {{ $activeEmailAccountId }})</span>
                        </x-button>
                    @endif
                </div>
            </div>

            {{-- Encrypted Data Tab --}}
            <div x-show="activeTab === 'encrypted'" x-cloak>
                <textarea
                    id="encryptedInput"
                    wire:model="encryptedInput"
                    rows="10"
                    class="mt-1 block w-full border border-gray-300 rounded-md shadow font-mono text-sm"
                    placeholder="Paste Base64 encrypted text here..."></textarea>
                <div class="mt-2">
                    <x-button wire:click="decrypt" wire:loading.attr="disabled">
                        Decrypt
                    </x-button>
                </div>

                @if ($encryptedOutput)
                    <div class="mt-4">
                        <x-label class="font-semibold" value="{{ __('Encrypted Output (Base64)') }}" />
                        <div class="relative">
                            <textarea
                                id="encryptedOutput"
                                readonly
                                rows="5"
                                class="mt-1 block w-full border border-gray-300 rounded-md shadow font-mono text-sm bg-gray-50"
                            >{{ $encryptedOutput }}</textarea>
                            <button
                                type="button"
                                x-data="{ copied: false }"
                                x-on:click="
                                    navigator.clipboard.writeText(document.getElementById('encryptedOutput').value.trim());
                                    copied = true;
                                    setTimeout(() => copied = false, 2000);
                                "
                                class="absolute top-2 right-2 mt-1 px-3 py-1 text-xs bg-gray-200 hover:bg-gray-300 rounded border border-gray-300 cursor-pointer"
                            >
                                <span x-show="!copied">Copy</span>
                                <span x-show="copied" x-cloak>Copied!</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
