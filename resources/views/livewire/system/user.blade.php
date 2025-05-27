<div class="mt-8 bg-gray-100  p-4">
    <x-form-section submit="saveUserDetails">
        <x-slot name="title">
            <a class="font-semibold hover:underline" href="/system/users">Users</a> &rarr; {{  $user->name }}
            <code class="bg-indigo-50 px-2 py-0.5 border border-indigo-300 rounded">{{ $user->id }}</code>
        </x-slot>

        <x-slot name="description">
            <div class="text-indigo-700 block mb-4">{{ __('Update the selected user details, information, and assignment') }}</div>
            <hr class="border border-gray-300" />
            <dl class="text-sm text-gray-500 font-sans">
                <div class="my-2">
                    <dt class="font-semibold text-gray-700">Created</dt>
                    <dd>
                        {{ $user->created_at->timezone(request()->user()->timezone)->format('m/d/Y g:i:s A T') }}
                        <small class="block text-gray-400">
                            {{ $user->created_at->timezone(request()->user()->timezone)->diffForHumans() }}
                        </small>
                    </dd>
                </div>
                <div class="my-2">
                    <dt class="font-semibold text-gray-700">Updated</dt>
                    <dd>
                        {{$user->updated_at->timezone(request()->user()->timezone)->format('m/d/Y g:i:s A T') }}
                        <small class="block  text-gray-400">
                            {{ $user->updated_at->timezone(request()->user()->timezone)->diffForHumans() }}
                        </small>
                    </dd>
                </div>
                <div class="my-2">
                    <dt class="font-semibold text-gray-700">SAML2 SSO</dt>
                    <dd>{!! $user->saml_linked_id ? "<span class=\"text-green-500\">{$user->saml_linked_id}</span>" : '<span class="text-gray500">Not Linked</span>' !!}</dd>
                </div>
                @if(!$user->saml_linked_id)
                    <div class="my-2">
                        <dt class="font-semibold text-gray-700">2FA/MFA</dt>
                        <dd>{!! $user->two_factor_secret ? '<span class="text-green-500">Enabled</span>' : '<span class="text-red-500">Disabled</span>' !!}</dd>
                    </div>
                    <div class="my-2">
                        <dt class="font-semibold text-gray-700">Email Verification</dt>
                        <dd>{!! $user->email_verified_at ? '<span class="text-green-500">Verified</span>' : '<span class="text-red-500">Not Verified</span>' !!}</dd>
                    </div>
                @endif

                <div class="my-2">
                    <dt class="font-semibold text-gray-700">Current Team(s)</dt>
                    <dd class="mt-1">
                        @foreach($user->allTeams() as $team)
                            <span class="my-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border
                            @if($team->personal_team)  bg-gray-200 border-gray-300 text-gray-800 @else bg-indigo-100 border-indigo-400 text-indigo-800 @endif mr-1">
                                {{ $team->name }}
                            </span>
                        @endforeach
                    </dd>
                </div>
            </dl>

        </x-slot>

        <x-slot name="form">

            <div class="col-span-6 lg:col-span-4">
                <x-label class="mb-2 font-semibold" for="user_name" value="User Name" />
                <x-input class="w-full" type="text" name="user_name" wire:model="user_name" />
                <small class="block my-2 text-xs text-gray-500">
                    The display name of the user account in Mission Control
                </small>
                <x-input-error for="user_name" class="mt-2" />
            </div>

            <div class="col-span-6 lg:col-span-4">
                <x-label class="mb-2 font-semibold" for="user_email" value="Email Address" />
                <x-input class="w-full" type="email" name="email" wire:model="user_email" />
                <small class="block my-2 text-xs text-gray-500">
                    The email address of the user account
                </small>
                <x-input-error for="user_email" class="mt-2" />
            </div>

            <!-- Email -->
            <div class="col-span-6 sm:col-span-4">
                <x-label class="mb-2 font-semibold" for="user_timezone" value="{{ __('Timezone') }}" />
                <x-input list="timezone_list" value="" id="user_timezone" type="timezone" class="p-2 mt-1 block w-full border border-gray-300 rounded" wire:model.live="user_timezone" />
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
                <small class="block my-2 text-xs text-gray-500">
                    The timezone to display application timestamps and date/times in
                </small>
                <x-input-error for="user_timezone" class="mt-2" />
            </div>

            <!-- Member Email -->
            <div class="col-span-6 sm:col-span-4">
                <x-label class="mb-2 font-semibold" for="user_agtId" value="{{ __('Intelligent Series agtId') }}" />
                <x-input id="user_agtId" name="user_agtId" list="agents" type="text" class="mt-1 block w-full " wire:model.live="user_agtId" />
                <datalist id="agents">
                    @if(isset($agents))
                        @foreach($agents as $agent)
                            <option value="{{ $agent->agtId }}">{{ $agent->Name }} ({{ $agent->Initials }})</option>
                        @endforeach
                    @endif
                </datalist>
                <small class="block my-2 text-xs text-gray-500">
                    The Intelligent Series agtId of the user (i.e., the raw database id)
                </small>
                <x-input-error for="user_agtId" class="mt-2" />
            </div>

        </x-slot>

        <x-slot name="actions">
            <x-action-message class="mr-3 " on="saved">
                {{ __('Saved.') }}
            </x-action-message>

            <x-button wire:loading.attr="disabled">
                {{ __('Save') }}
            </x-button>
        </x-slot>
    </x-form-section>

    <x-section-border class="my-6" />

    <x-form-section submit="assignTeamAndRole">
        <x-slot name="title">
           Team & Role Assignment
        </x-slot>

        <x-slot name="description">
            Assign or remove a user for a particular team &ndash; and the role within that team.

            <x-input-error for="remove_error" class="my-4" />
            <div class="w-full mt-4">
                <table class="table-auto min-w-full divide-y divide-gray-300">
                    <thead class="text-gray-500">
                        <tr class="text-left border-b border-gray-300">
                            <th>Team</th>
                            <th>Role</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                    @foreach($user->allTeams() as $team)
                        <tr>
                            <td class="text-sm py-2 font-semibold text-indigo-900">{{ $team->name }}</td>
                            <td class="text-sm py-2  text-gray-600">{{ $user->teamRole($team)->name ?? 'Error' }}</td>
                            <td class="text-sm py-2 ">
                                @if(!$team->personal_team)
                                    <a title="Remove {{ $user->name }} from the {{ $team->name }} team"
                                        wire:confirm="Remove {{ $user->name }} from the {{ $team->name }} team?"
                                        class="cursor-pointer text-red-400 hover:text-red-600 border border-gray-300 hover:bg-red-100 border hover:border-red-400 rounded-full text-xs px-1.5"
                                        wire:click="removeFromTeam({{ $team->id }})">
                                        Remove
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <small class="block text-xs text-gray-400 mt-4">
                Team owners and owner roles cannot be removed or changed - if you need to re-assign a team owner please contact support.
            </small>
        </x-slot>

        <x-slot name="form">
            <div class="col-span-6 lg:col-span-4">
                <x-label class="mb-2 font-semibold" for="new_team" value="Team Assignment" />
                <select class="w-full border border-gray-300 rounded shadow"  wire:model="new_team">
                    <option value="">Select a Team</option>
                    @foreach($teams as $team)
                        <option value="{{ $team->id }}">{{ $team->name }}</option>
                    @endforeach
                </select>
                <small class="block my-2 text-xs text-gray-500">
                    The team to assign to the user
                </small>
                <x-input-error for="new_team" class="mt-2" />
            </div>
            <div class="col-span-6 lg:col-span-4">
                <x-label class="mb-2 font-semibold" for="new_role" value="Role Assignment" />
                <select class="w-full border border-gray-300 rounded shadow" id="new_role" wire:model="new_role">
                    <option value="">Select a Role</option>
                    @foreach($roles as $role)
                        <option value="{{ $role }}">{{ ucwords($role) }}</option>
                    @endforeach
                </select>
                <small class="block my-2 text-xs text-gray-500">
                    The role on the team to assign to the user.
                </small>
                <x-input-error for="new_role" class="mt-2" />
            </div>
        </x-slot>

        <x-slot name="actions">
            <x-action-message class="mr-3 " on="assigned">
                {{ __('Assigned.') }}
            </x-action-message>

            <x-button wire:loading.attr="disabled">
                {{ __('Assign') }}
            </x-button>
        </x-slot>

    </x-form-section>


    <x-section-border class="my-6" />

    <x-action-section>
        <x-slot name="title">
            {{ __('Delete Account') }}
        </x-slot>

        <x-slot name="description">
            {{ __('Permanently delete the user account.') }}
        </x-slot>

        <x-slot name="content">
            <div class="max-w-xl text-sm text-gray-600 ">
                Once the account is deleted, <strong>all of its resources and data will be permanently deleted</strong>.
                <span class="italic">Before deleting the account</span>, please download any data or information that you wish to retain.
            </div>

            <div class="mt-5">
                <x-danger-button wire:click="confirmUserDeletion" wire:loading.attr="disabled">
                    {{ __('Delete Account') }}
                </x-danger-button>
            </div>

            <!-- Delete User Confirmation Modal -->
            <x-dialog-modal wire:model.live="confirmingUserDeletion">
                <x-slot name="title">
                    {{ __('Delete Account') }}
                </x-slot>

                <x-slot name="content">
                    <span class="italic text-lg ">Are you sure you want to delete this account?</span>
                    <p class="my-4">
                        <strong>All resources and data will be permanently removed - including</strong>:
                        <ul class="list-disc list-inside ml-4">
                            <li>the account's personal team</li>
                            <li>all application user records</li>
                        </ul>
                    </p>

                    <x-input-error for="delete_user" class="mt-2" />
                </x-slot>

                <x-slot name="footer">
                    <x-secondary-button wire:click="$toggle('confirmingUserDeletion')" wire:loading.attr="disabled">
                        {{ __('Cancel') }}
                    </x-secondary-button>

                    <x-danger-button class="ml-2" wire:click="deleteUser" wire:loading.attr="disabled">
                        {{ __('Delete Account') }}
                    </x-danger-button>
                </x-slot>
            </x-dialog-modal>
        </x-slot>
    </x-action-section>


</div>
