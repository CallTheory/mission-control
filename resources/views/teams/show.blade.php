<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl leading-tight ">
            {{ Auth::user()->currentTeam->name ?? 'Current Team'  }}'s <span class="text-gray-500">Settings</span>
        </h2>
    </x-slot>

    <div class="p-4">

        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            @if($team->personal_team === true)
                <x-alert-info title="Personal Team" description="You cannot add team members or configure settings for personal teams" />
            @endif

            <livewire:teams.update-team-name-form lazy="lazy" :team="$team" />

            @if($team->personal_team === false)

                <x-section-border />

                <livewire:teams.accounts lazy="lazy" :team="$team" />

                <x-section-border />

                <livewire:teams.billing-numbers lazy="lazy" :team="$team" />

                <livewire:teams.team-member-manager lazy="lazy" :team="$team" />

                <x-section-border />

                <livewire:teams.enabled-utilities lazy="lazy" :team="$team" />


                @if (Gate::check('delete', $team) && ! $team->personal_team)
                    <x-section-border />

                    <div class="mt-10 sm:mt-0">
                        <livewire:teams.delete-team-form lazy="lazy" :team="$team" />
                    </div>
                @endif

            @endif
        </div>
    </div>
</x-app-layout>
