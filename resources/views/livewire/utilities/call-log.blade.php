@php
    use Illuminate\Support\Facades\Auth;
@endphp

<div class="w-full my-2">
    <div class="mb-2">
                @if($sql_code)
                    <div class="inline"
                        x-data="{ 'showSQLCode': false }"
                        @keydown.escape="showSQLCode = false"
                    >

                        <!-- Trigger for Modal -->
                        <x-secondary-button class="mt-2 inline"  @click="showSQLCode = true">
                            &lt;SQL&gt;
                        </x-secondary-button>

                        <!-- Modal -->
                        <div
                            class="fixed inset-0 z-100 overflow-scroll bg-surface-inverse text-surface-inverse-fg"
                            x-show="showSQLCode"
                            @click.away="showSQLCode = false"
                            x-transition:enter="motion-safe:ease-out duration-300"
                            x-transition:enter-start="opacity-0 scale-90"
                            x-transition:enter-end="opacity-100 scale-100"
                        >
                            <div class="p-4">
                                <div class="flex">
                                    <button type="button" class="hover:text-surface-fg-soft text-muted z-50 ml-0 cursor-pointer"
                                            onclick="navigator.clipboard.writeText('{{ str_replace("\n", "\\n", $sql_code) }}');">
                                        Copy SQL Code
                                    </button>
                                    <button type="button" class="z-50 ml-auto cursor-pointer" @click="showSQLCode = false">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <hr class="my-4 border border-border" />

                                <div>
                                    <code id="sql_code">{!! nl2br($sql_code) !!}</code>
                                </div>

                                <hr class="my-4 border border-border" />

                                <div>
                                    <pre>{!! print_r($sql_params, true) !!}</pre>
                                </div>

                            </div>
                        </div>
                    </div>
                @endif
    </div>

    {{ $this->table }}
</div>
