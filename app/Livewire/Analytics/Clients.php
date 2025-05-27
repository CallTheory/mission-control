<?php

namespace App\Livewire\Analytics;

use App\Models\Stats\Clients\Overview;
use App\Models\Stats\Clients\Sources;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Clients extends Component
{
    use WithPagination;

    public function render(): View
    {
        $clientsArray = [];
        $sourcesArray = [];

        try {
            $clients = new Overview([]);
            $sources = new Sources(['all' => true]);
            $clientsArray = $clients->results;
            $sourcesArray = $sources->results;
        } catch (Exception $e) {
        }

        $current_page = request()->input('page') ?? 1;
        $per_page = 25;

        $starting_point = ($current_page * $per_page) - $per_page;

        $clientPaginator = new LengthAwarePaginator(array_slice($clientsArray, $starting_point, $per_page), count($clientsArray), $per_page, $current_page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);

        return view('livewire.analytics.clients', [
            'clients' => $clientPaginator,
            'sources' => $sourcesArray,
        ]);
    }
}
