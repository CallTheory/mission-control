<div>
    <a wire:click="$toggle('editingProfile')" class="hover:underline cursor-pointer">{{ $state['name'] }}</a>

    <x-dialog-modal wire:model.live="editingProfile">
        <x-slot name="title">
            {{ __('Manage User Profile') }}
        </x-slot>

        <x-slot name="content">

            <input type="hidden" name="user_id" wire:model="user_id" value="{{ $user_id }}"/>
            <div class="my-4 w-full">
                <x-label for="name" value="{{ __('User Name') }}" />
                <x-input type="text" name="name" wire:model.defer="state.name" class="mt-1 block w-full" />
                <small class="text-gray-400">The users name (or nickname)</small>
                <x-input-error for="state.name" class="mt-2" />
            </div>
            <div class="my-4 w-full">
                <x-label for="email" value="{{ __('User Email') }}" />
                <x-input type="email" name="email" wire:model.defer="state.email"  class="mt-1 block w-full" />
                <small class="text-gray-400">The users email address</small>
                <x-input-error for="state.email" class="mt-2" />
            </div>
            <div class="my-4 w-full">
                <x-label for="agtId" value="{{ __('Agent') }}" />
                <x-input id="agtId" name="agtId" wire:model.defer="state.agtId"  list="agents" type="text" class="mt-1 block w-full " />
                <datalist id="agents">
                    @if(isset($agents->results))
                        @foreach($agents->results as $agent)
                            @if($agent->agtId === $user->agtId)
                                <option selected="selected" value="{{ $agent->agtId }}">{{ $agent->Name }} ({{ $agent->Initials }})</option>
                            @else
                                <option value="{{ $agent->agtId }}">{{ $agent->Name }} ({{ $agent->Initials }})</option>
                            @endif


                        @endforeach
                    @endif

                </datalist>
                <small class="text-gray-400">The Intelligent Series agtId for the users login. Used to populate the user's personal dashboard.</small>
                <x-input-error for="agtId" class="mt-2" />
            </div>
        </x-slot>

        <x-slot name="footer">
            <x-secondary-button wire:click="$toggle('editingProfile')" wire:loading.attr="disabled">
                {{ __('Cancel') }}
            </x-secondary-button>

            <x-button class="ml-2" wire:click="updateProfile" wire:loading.attr="disabled">
                {{ __('Save') }}
            </x-button>
        </x-slot>
    </x-dialog-modal>

</div>
