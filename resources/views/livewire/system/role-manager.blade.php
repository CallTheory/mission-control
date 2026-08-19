<div class="space-y-8">

    @if ($errors->has('role'))
        <div class="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-700">
            {{ $errors->first('role') }}
        </div>
    @endif

    {{-- Roles list --}}
    <div>
        <h3 class="text-2xl">Roles</h3>
        <p class="text-sm text-gray-500 mb-4">
            Each role grants access to the utilities and admin areas you check below. Built-in roles start with today's
            defaults and can be edited; you can also add your own.
        </p>

        <div class="shadow overflow-x-auto border border-gray-300 sm:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Key</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Members</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capabilities</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach ($roles as $role)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $role->label }}
                                @if ($role->is_system)
                                    <span class="ml-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">built-in</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $role->key }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $role->users_count }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ $role->capabilities->count() }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <button wire:click="editRole({{ $role->id }})" class="cursor-pointer text-indigo-600 hover:text-indigo-900">Edit</button>
                                @unless ($role->is_system)
                                    <button wire:click="deleteRole({{ $role->id }})"
                                            wire:confirm="Delete the {{ $role->label }} role?"
                                            class="cursor-pointer ml-4 text-red-600 hover:text-red-900">Delete</button>
                                @endunless
                            </td>
                        </tr>

                        @if ($editingRoleId === $role->id)
                            <tr>
                                <td colspan="5" class="px-6 py-6 bg-gray-50">
                                    @error('capabilities')
                                        <div class="mb-3 text-sm text-red-700">{{ $message }}</div>
                                    @enderror

                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                        @foreach ($groupedCapabilities as $group => $capabilities)
                                            <div>
                                                <h4 class="text-xs font-semibold uppercase text-gray-500 mb-2">{{ $group }}</h4>
                                                <div class="space-y-1">
                                                    @foreach ($capabilities as $capability)
                                                        <label class="flex items-center gap-2 text-sm text-gray-700">
                                                            <input type="checkbox"
                                                                   value="{{ $capability->value }}"
                                                                   wire:model="selectedCapabilities"
                                                                   class="rounded border-gray-300 text-indigo-600" />
                                                            {{ $capability->label() }}
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="mt-6 flex items-center gap-3">
                                        <x-button wire:click="saveCapabilities" wire:loading.attr="disabled">Save Capabilities</x-button>
                                        <button wire:click="cancelEdit" type="button" class="cursor-pointer text-sm text-gray-500 hover:text-gray-800">Cancel</button>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Create role --}}
    <div class="border border-gray-300 rounded-lg p-6 bg-white">
        <h3 class="text-xl mb-4">Add a Role</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <x-label for="newLabel" value="Name" />
                <x-input id="newLabel" type="text" class="mt-1 block w-full" wire:model="newLabel" placeholder="Night Shift" />
                <x-input-error for="newLabel" class="mt-1" />
            </div>
            <div>
                <x-label for="newKey" value="Key (optional)" />
                <x-input id="newKey" type="text" class="mt-1 block w-full" wire:model="newKey" placeholder="night_shift" />
                <x-input-error for="newKey" class="mt-1" />
            </div>
            <div>
                <x-label for="newDescription" value="Description (optional)" />
                <x-input id="newDescription" type="text" class="mt-1 block w-full" wire:model="newDescription" />
                <x-input-error for="newDescription" class="mt-1" />
            </div>
        </div>
        <div class="mt-4">
            <x-button wire:click="createRole" wire:loading.attr="disabled">Create Role</x-button>
        </div>
    </div>

    {{-- Suffix rules --}}
    <div class="border border-gray-300 rounded-lg p-6 bg-white">
        <h3 class="text-xl">Agent Name Rules</h3>
        <p class="text-sm text-gray-500 mb-4">
            Grant extra capabilities based on the linked Amtelco agent's login name — for example, agents whose name
            contains <code>-SUP</code>. Global defaults apply to every team and are shown for reference.
        </p>

        @if ($globalSuffixRules->isNotEmpty())
            <div class="mb-4">
                <h4 class="text-xs font-semibold uppercase text-gray-500 mb-2">Global defaults</h4>
                <ul class="text-sm text-gray-600 space-y-1">
                    @foreach ($globalSuffixRules as $rule)
                        <li><span class="font-mono">{{ $rule->match_type }}: {{ $rule->pattern }}</span> &rarr; {{ $rule->capability }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mb-4">
            <h4 class="text-xs font-semibold uppercase text-gray-500 mb-2">This team</h4>
            @forelse ($teamSuffixRules as $rule)
                <div class="flex items-center justify-between text-sm text-gray-700 py-1">
                    <span><span class="font-mono">{{ $rule->match_type }}: {{ $rule->pattern }}</span> &rarr; {{ $rule->capability }}</span>
                    <button wire:click="deleteSuffixRule({{ $rule->id }})" class="cursor-pointer text-red-600 hover:text-red-900">Remove</button>
                </div>
            @empty
                <p class="text-sm text-gray-400">No team-specific rules.</p>
            @endforelse
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start border-t border-gray-200 pt-4">
            <div>
                <x-label for="suffixMatchType" value="Match" />
                <select id="suffixMatchType" wire:model="suffixMatchType" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm">
                    <option value="contains">Contains</option>
                    <option value="suffix">Ends with</option>
                    <option value="prefix">Starts with</option>
                </select>
            </div>
            <div>
                <x-label for="suffixPattern" value="Pattern" />
                <x-input id="suffixPattern" type="text" class="mt-1 block w-full" wire:model="suffixPattern" placeholder="-SUP" />
                <x-input-error for="suffixPattern" class="mt-1" />
            </div>
            <div>
                <x-label value="Grants capabilities" />
                <select multiple wire:model="suffixCapabilities" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm h-32">
                    @foreach ($groupedCapabilities as $group => $capabilities)
                        <optgroup label="{{ $group }}">
                            @foreach ($capabilities as $capability)
                                <option value="{{ $capability->value }}">{{ $capability->label() }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <x-input-error for="suffixCapabilities" class="mt-1" />
            </div>
        </div>
        <div class="mt-4">
            <x-button wire:click="addSuffixRule" wire:loading.attr="disabled">Add Rule</x-button>
        </div>
    </div>

    <x-action-message class="text-green-600" on="saved">Saved.</x-action-message>
</div>
