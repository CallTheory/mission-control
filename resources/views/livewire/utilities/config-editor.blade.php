<div class="w-full">
    <x-page-header title="Configuration Editor">
        <x-slot name="subtitle">
            Decrypt an Amtelco configuration blob, edit the XML, then encrypt it back.
        </x-slot>
        <x-slot name="actions">
            {{ $this->loadFromDatabaseAction }}
        </x-slot>
    </x-page-header>

    @if($databaseLoaded)
        <div class="mb-4 grid gap-4 md:grid-cols-3">
            @if(!empty($sysConfigs))
                <x-filament::section heading="System Config" compact>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['Config', 'Config2'] as $field)
                            @if(!empty($sysConfigs[$field]))
                                <x-button wire:click="loadSysConfig('{{ $field }}')" class="text-xs">{{ $field }}</x-button>
                            @endif
                        @endforeach
                    </div>
                </x-filament::section>
            @endif

            @if(!empty($scheduleRecords))
                <x-filament::section heading="Schedule Records ({{ count($scheduleRecords) }})" compact>
                    <div class="max-h-48 overflow-y-auto flex flex-wrap gap-2">
                        @foreach($scheduleRecords as $record)
                            <x-button wire:click="loadScheduleRecord({{ $record['schId'] }})" class="text-xs">
                                {{ $record['schId'] }}
                            </x-button>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif

            @if(!empty($emailAccounts))
                <x-filament::section heading="Email Accounts ({{ count($emailAccounts) }})" compact>
                    <div class="max-h-48 overflow-y-auto flex flex-wrap gap-2">
                        @foreach($emailAccounts as $account)
                            <x-button wire:click="loadEmailAccount({{ $account['id'] }})" class="text-xs">
                                {{ $account['id'] }}
                            </x-button>
                        @endforeach
                    </div>
                </x-filament::section>
            @endif
        </div>
    @endif

    {{ $this->form }}

    <div class="mt-4 flex flex-wrap items-center gap-2">
        {{ $this->decryptAction }}
        {{ $this->encryptAction }}
        {{ $this->saveToScheduleAction }}
        {{ $this->saveToEmailAccountAction }}
    </div>

    <x-filament-actions::modals />
</div>
