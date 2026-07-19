{{-- Standard table chrome. Usage:
     <x-table>
         <x-slot name="head">
             <x-table.heading>Name</x-table.heading> ...
         </x-slot>
         @forelse($rows as $row)
             <x-table.row> <x-table.cell>...</x-table.cell> </x-table.row>
         @empty
             <x-table.empty :colspan="3">No records found.</x-table.empty>
         @endforelse
         <x-slot name="footer">{{ $rows->links() }}</x-slot>
     </x-table> --}}
<div {{ $attributes->merge(['class' => 'bg-surface shadow rounded-lg overflow-hidden']) }}>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-border">
            @isset($head)
                <thead class="bg-surface-2">
                    <tr>{{ $head }}</tr>
                </thead>
            @endisset
            <tbody class="divide-y divide-border">
                {{ $slot }}
            </tbody>
        </table>
    </div>

    @isset($footer)
        <div class="px-4 py-3 border-t border-border">{{ $footer }}</div>
    @endisset
</div>
