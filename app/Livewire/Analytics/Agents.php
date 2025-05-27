<?php

namespace App\Livewire\Analytics;

use App\Models\Stats\Agents\Overview;
use Carbon\Carbon;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Agents extends Component
{
    use WithPagination;

    public function render(): View
    {
        $agentArray = [];

        try {
            $agents = new Overview([
                'start_date' => Carbon::now()->subDays(1)->format('Y-m-d H:i:s'),
                'end_date' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            $agentArray = $agents->results;
        } catch (Exception $e) {
        }

        $current_page = request()->input('page') ?? 1;
        $per_page = 25;

        $starting_point = ($current_page * $per_page) - $per_page;

        $agentPaginator = new LengthAwarePaginator(array_slice($agentArray, $starting_point, $per_page), count($agentArray), $per_page, $current_page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);

        return view('livewire.analytics.agents', [
            'agents' => $agentPaginator,
        ]);
    }
}
