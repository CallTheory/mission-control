@php
    use Illuminate\Pagination\LengthAwarePaginator;
    $per_page = 25;
@endphp
<div class="py-4 w-full">

    @if($this->results === null)
       We could not get database health information. Please check your system configuration or database status.
    @else
        @include('utilities.database-health.maintenance-schedule', ['results' => $this->maintenance_schedule ?? null, 'checklist' => $this->maintenance_checklist ?? []])
        <hr class="my-8 border border-gray-300">
        @include('utilities.database-health.database-server', ['results' => $this->results[0] ?? null])
        <hr class="my-8 border border-gray-300">
        @include('utilities.database-health.volume-details', ['results' => $this->results[1] ?? null])
        <hr class="my-8 border border-gray-300">
        @include('utilities.database-health.database-details', ['results' => $this->results[2] ?? null])
        <hr class="my-8 border border-gray-300">
        @include('utilities.database-health.backup-status', ['results' => $this->results[3] ?? null])
        <hr class="my-8 border border-gray-300">
        @include('utilities.database-health.intelligent-database', ['results' => new LengthAwarePaginator(array_slice($this->results[4] ?? [], (($this->getPage() * $per_page)-$per_page), $per_page), count($this->results[4]), $per_page)])

    @endif

</div>
