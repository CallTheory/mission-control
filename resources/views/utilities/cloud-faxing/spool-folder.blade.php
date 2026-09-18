@php
    use Carbon\Carbon;

    /**
     * One spool directory's contents.
     *
     * @var string $title        Heading for the card.
     * @var string $description  What files in this folder are waiting on.
     * @var string $folder       Directory name under storage/app/{provider}/ (tosend|sent|fail|preproc).
     * @var array  $files        Descriptors from App\Services\Faxing\FaxSpool::files().
     * @var bool   $canManage    Whether the viewer holds fax.manage_spool.
     */
    $timezone = Auth::user()->timezone ?? 'UTC';
@endphp

<div class="bg-surface shadow overflow-hidden sm:rounded-lg my-3">
    <div class="px-4 py-5 sm:px-6 sm:flex sm:items-start sm:justify-between gap-4">
        <div>
            <h3 class="text-lg leading-6 font-medium text-surface-fg">{{ $title }}</h3>
            <p class="mt-1 max-w-2xl text-sm text-muted">{{ $description }}</p>
        </div>

        @if($canManage && count($files))
            <div class="mt-3 sm:mt-0 shrink-0">
                <button type="button"
                        wire:click="mountAction('clearSpoolFolder', { folder: '{{ $folder }}' })"
                        wire:loading.attr="disabled"
                        class="cursor-pointer inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md bg-danger-soft text-danger-soft-fg border border-danger/40 hover:bg-danger hover:text-danger-fg focus:outline-hidden focus:ring focus:ring-danger/40 transition disabled:opacity-50">
                    Clear Folder ({{ count($files) }})
                </button>
            </div>
        @endif
    </div>

    <div class="border-t border-border">
        @if(count($files) === 0)
            <p class="px-4 py-5 sm:px-6 text-sm text-muted">This folder is empty.</p>
        @else
            <ul role="list" class="divide-y divide-border-soft">
                @foreach($files as $file)
                    <li class="px-4 py-4 sm:px-6 sm:flex sm:items-center sm:justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-surface-fg truncate">{{ $file['name'] }}</p>
                            <p class="mt-1 text-xs text-muted">
                                @switch($file['type'])
                                    @case('cap')
                                        Fax Message
                                        @break
                                    @case('fs')
                                        Fax Metadata
                                        @break
                                    @default
                                        Unrecognized file
                                @endswitch

                                @if(!empty($file['job_id']))
                                    <span class="mx-1 text-border">&middot;</span> Job {{ $file['job_id'] }}
                                @endif

                                <span class="mx-1 text-border">&middot;</span>
                                {{ number_format(($file['size'] ?? 0) / 1024, 1) }} KB

                                @if(!empty($file['modified_at']))
                                    <span class="mx-1 text-border">&middot;</span>
                                    <span title="{{ Carbon::parse($file['modified_at'])->timezone($timezone)->format('m/d/Y g:i:s A T') }}">
                                        {{ Carbon::parse($file['modified_at'])->diffForHumans() }}
                                    </span>
                                @endif
                            </p>
                        </div>

                        <div class="mt-2 sm:mt-0 flex items-center gap-4 shrink-0">
                            @if(!empty($file['account']))
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-surface-2 text-surface-fg border border-border-soft"
                                      title="Intelligent Series account">
                                    {{ $file['account'] }}
                                </span>
                            @else
                                <span class="text-xs text-muted italic">Account unknown</span>
                            @endif

                            @if($canManage)
                                <button type="button"
                                        wire:click="mountAction('deleteSpoolFile', { folder: '{{ $folder }}', file: '{{ $file['name'] }}' })"
                                        wire:loading.attr="disabled"
                                        title="Delete this file"
                                        class="text-xs text-danger hover:underline disabled:opacity-50">
                                    Delete
                                </button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
