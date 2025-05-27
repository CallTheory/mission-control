@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            setTimeout(function(){
                window.location.reload();
            }, {{ config('session.lifetime')*60*1000-(1000*60*10) }} );
        });
    </script>
@endpush
<x-app-layout>

    <x-slot name="header">

        <h2 class="inline font-normal text-xl leading-tight ">
            <div class="flex flex-row">
                <div class="my-2 mr-4">Dashboard</div>
                <livewire:dashboard.timeframe />
            </div>
        </h2>

    </x-slot>

    <div class="p-4">

        @if(request()->user()->currentTeam->personal_team === true)
            <livewire:personal-dashboard :user="request()->user()"></livewire:personal-dashboard>
        @else
            <div class="max-w-9xl mx-auto px-4">
                @if(request()->user()->currentTeam->allowed_accounts || request()->user()->currentTeam->allowed_billing)
                    <div class="bg-indigo-50 text-indigo-500 flex mb-2 rounded-lg px-2 py-2 shadow border border-indigo-300 text-xs">
                        @if(request()->user()->currentTeam->allowed_accounts)
                            <div class="mr-2">
                                <span class="font-semibold">Account(s)</span> &ndash; {{ implode(',', array_filter(explode("\n", request()->user()->currentTeam->allowed_accounts))) }}
                            </div>
                        @endif
                        @if(request()->user()->currentTeam->allowed_billing)
                            <div class="mr-2">
                                <span class="font-semibold">Billing Code(s)</span> &ndash;  {{ implode(',', array_filter(explode("\n", request()->user()->currentTeam->allowed_billing))) }}
                            </div>
                        @endif
                    </div>
                @endif
                <div class="overflow-hidden sm:rounded-lg ">
                    <ul role="list" class="widgets grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-y-4 gap-x-4 mb-12 z-0">
                        <livewire:dashboard.statistic :type="'secretarial_calls'"  />
                        <livewire:dashboard.statistic :type="'average_answer_time'" />
                        <livewire:dashboard.statistic :type="'average_talk_time'" />
                        <livewire:dashboard.statistic :type="'total_abandons'" />
                        <livewire:dashboard.statistic :type="'system_abandons'"  />
                        <livewire:dashboard.statistic :type="'agent_abandons'" />
                        <livewire:dashboard.statistic :type="'average_abandon_rate'" />
                        <livewire:dashboard.statistic :type="'average_disc_time'" />
                        <livewire:dashboard.statistic :type="'greeting_hangups'" />
                    </ul>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
