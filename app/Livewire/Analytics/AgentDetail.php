<?php

namespace App\Livewire\Analytics;

use App\Models\Stats\Agents\Agent;
use Exception;
use Illuminate\View\View;
use Livewire\Component;

class AgentDetail extends Component
{
    public array $agent;

    /**
     * @throws Exception
     */
    public function mount($agent_name): void
    {
        $agent = new Agent(['agent_name' => $agent_name]);

        if (isset($agent->results[0])) {
            $this->agent = (array) $agent->results[0];
        } else {
            abort(404);
        }
    }

    public function render(): View
    {
        return view('livewire.analytics.agent-detail');
    }
}
