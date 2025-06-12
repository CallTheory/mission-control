<x-form-section submit="updateProfileInformation">
    <x-slot name="title">
        {{ __('Profile Information') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Update your account\'s profile information and email address.') }}
    </x-slot>

    <x-slot name="form">
        <!-- Profile Photo -->
        @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
            <div x-data="{photoName: null, photoPreview: null}" class="col-span-6 sm:col-span-4">
                <!-- Profile Photo File Input -->
                <input type="file" class="hidden"
                            wire:model.live="photo"
                            x-ref="photo"
                            x-on:change="
                                    photoName = $refs.photo.files[0].name;
                                    const reader = new FileReader();
                                    reader.onload = (e) => {
                                        photoPreview = e.target.result;
                                    };
                                    reader.readAsDataURL($refs.photo.files[0]);
                            " />

                <x-label for="photo" value="{{ __('Photo') }}" />

                <!-- Current Profile Photo -->
                <div class="mt-2" x-show="! photoPreview">
                    <img src="{{ $this->user->profile_photo_url }}" alt="{{ $this->user->name }}" class="rounded-full h-20 w-20 object-cover border-2 border-transparent ">
                </div>

                <!-- New Profile Photo Preview -->
                <div class="mt-2" x-show="photoPreview">
                    <span class="block rounded-full w-20 h-20"
                          x-bind:style="'background-size: cover; background-repeat: no-repeat; background-position: center center; background-image: url(\'' + photoPreview + '\');'">
                    </span>
                </div>

                <x-button class="mt-2 mr-2" type="button" x-on:click.prevent="$refs.photo.click()">
                    {{ __('Select A New Photo') }}
                </x-button>

                @if ($this->user->profile_photo_path)
                    <x-secondary-button type="button" class="mt-2" wire:click="deleteProfilePhoto">
                        {{ __('Remove Photo') }}
                    </x-secondary-button>
                @endif

                <x-input-error for="photo" class="mt-2" />
            </div>
        @endif

        <!-- Name -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="name" value="{{ __('Name') }}" />
            <x-input id="name" type="text" class="mt-1 block w-full " wire:model.live="state.name" autocomplete="name" />
            <x-input-error for="name" class="mt-2" />
        </div>

        <!-- Email -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="email" value="{{ __('Email') }}" />
            <x-input id="email" type="email" class="mt-1 block w-full " wire:model.live="state.email" />
            <x-input-error for="email" class="mt-2" />
        </div>

        <!-- Email -->
        <div class="col-span-6 sm:col-span-4">
            <x-label for="timezone" value="{{ __('Timezone') }}" />
            <x-input list="timezone_list" value="" id="timezone" type="timezone" class=" p-2 mt-1 block w-full border border-gray-300" wire:model.live="state.timezone" />
            <datalist id="timezone_list" class="min-w-full">
                <option value="UTC">Coordinated Universal Time </option>
                @php
                    // United states association notes
                    $notations['America/New_York'] = 'Eastern Time Zone (EST/EDT)';
                    $notations['America/Chicago'] = 'Central';
                    $notations['America/Denver'] = 'Mountain ';
                    $notations['America/Phoenix'] = 'Mountain no DST';
                    $notations['America/Los_Angeles'] = 'Pacific';
                    $notations['America/Anchorage'] = 'Alaska';
                    $notations['America/Adak'] = 'Hawaii';
                    $notations['Pacific/Honolulu'] = 'Hawaii no DST';

                    //Canada association notes
                    $notations['America/St_Johns'] = 'Newfoundland ';
                    $notations['America/Halifax'] = 'Atlantic ';
                    $notations['America/Blanc-Sablon'] = 'Atlantic no DST ';
                    $notations['America/Toronto'] = 'Eastern ';
                    $notations['America/Atikokan'] = 'Eastern no DST';
                    $notations['America/Winnipeg'] = 'Central ';
                    $notations['America/Regina'] = 'Central no DST';
                    $notations['Pacific/Edmonton'] = 'Mountain ';
                    $notations['Pacific/Creston'] = 'Mountain no DST';
                    $notations['Pacific/Vancouver'] = 'Pacific';

                    //General association notes
                    $notations['UTC'] = 'GMT'
                @endphp
                @foreach(DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, 'US') as $tz )
                    @php
                        $parts = explode('/', $tz);
                    @endphp
                    @if( count($parts) > 2)
                        <option value="{{ $tz }}">{{ str_replace('_', ' ', implode(', ',array_reverse(array_slice( $parts, 1, 2)))) }} (USA) {!!   isset($notations[$tz]) ? "&middot; {$notations[$tz]}" : ''  !!}</option>
                    @else
                        <option value="{{ $tz }}">{{ str_replace('_', ' ', implode(' ',array_reverse(array_slice( $parts, 1, 1) ))) }} (USA) {!!   isset($notations[$tz]) ? "&middot; {$notations[$tz]}" : ''  !!}</option>
                    @endif

                @endforeach
                @foreach(DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, 'CA') as $tz )
                    @php
                        $parts = explode('/', $tz);
                    @endphp
                    @if( count($parts) > 2)
                        <option value="{{ $tz }}">{{ str_replace('_', ' ', implode(', ',array_reverse(array_slice( $parts, 1, 2) ))) }} (Canada) {!!   isset($notations[$tz]) ? "&middot; {$notations[$tz]}" : ''  !!}</option>
                    @else
                        <option value="{{ $tz }}">{{ str_replace('_', ' ', implode(' ',array_reverse(array_slice( $parts, 1, 1) ))) }} (Canada) {!!   isset($notations[$tz]) ? "&middot; {$notations[$tz]}" : ''  !!}</option>
                    @endif
                @endforeach

            </datalist>
            <x-input-error for="timezone" class="mt-2" />
        </div>

    </x-slot>

    <x-slot name="actions">
        <x-action-message class="mr-3 " on="saved">
            {{ __('Saved.') }}
        </x-action-message>

        <x-button wire:loading.attr="disabled" wire:target="photo">
            {{ __('Save') }}
        </x-button>
    </x-slot>
</x-form-section>
