<x-form-section submit="updateTeamName">
    <x-slot name="title">
        {{ __('Team Name') }}
    </x-slot>

    <x-slot name="description">
        {{ __('The team\'s name and owner information.') }}
    </x-slot>

    <x-slot name="form">
        <!-- Team Owner Information -->
        <div class="col-span-6">
            <x-label value="{{ __('Team Owner') }}" />

            <div class="flex items-center mt-2">
                <img class="w-12 h-12 rounded-full object-cover border-2 border-transparent " src="{{ $team->owner->profile_photo_url }}" alt="{{ $team->owner->name }}">

                <div class="ml-4 leading-tight">
                    <div class="">{{ $team->owner->name }}</div>
                    <div class="text-gray-700  text-sm">{{ $team->owner->email }}</div>
                </div>
            </div>
        </div>

        <!-- Team Name -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="name" value="{{ __('Team Name') }}" />

            <x-input id="name"
                        type="text"
                        class="mt-1 block w-full"
                        wire:model.live="state.name"
                        :class="$team->personal_team ? 'cursor-not-allowed  ' : ''"
                        :disabled="! Gate::check('update', $team) || $team->personal_team" />

            <x-input-error for="name" class="mt-2" />
        </div>

    </x-slot>

    @if (Gate::check('update', $team))
        <x-slot name="actions">
            <x-action-message class="mr-3 " on="saved">
                {{ __('Saved.') }}
            </x-action-message>

            <x-button
                :class="$team->personal_team ? 'cursor-not-allowed   ' : ''"
                :disabled="! Gate::check('update', $team) || $team->personal_team" >
                {{ __('Save') }}
            </x-button>
        </x-slot>
    @endif
</x-form-section>
