@php
$warning_icon = '<svg class="w-6 h-6 mx-auto rounded-full p-1 text-indigo-400 group-hover:text-white ease-in-out duration-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>';
@endphp

<div>
    @if($isOpen)
        <div class="absolute">
            <div class="absolute inset-0 overflow-hidden" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
                <div class="absolute inset-0 overflow-hidden">
                    <!--
                      Background overlay, show/hide based on slide-over state.
                    -->
                    <template x-if="$wire.isOpen = true">
                    <div

                        x-transition:enter="transform transition ease-in-out duration-1000"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transform transition ease-in-out duration-1000"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="inline-block transform transition absolute inset-0 bg-black transition-opacity fixed-bottom" aria-hidden="true"></div>
                    </template>

                    <div class="fixed inset-y-0 right-0 pl-10 max-w-full flex z-100">
                        <!--
                          Slide-over panel, show/hide based on slide-over state.
                        -->
                        <template x-if="$wire.isOpen = true">
                        <div

                            x-transition:enter="transform transition ease-in-out duration-500 sm:duration-1000"
                            x-transition:enter-start="translate-x-full"
                            x-transition:enter-end="translate-x-0"
                            x-transition:leave="transform transition ease-in-out duration-500 sm:duration-1000"
                            x-transition:leave-start="translate-x-0"
                            x-transition:leave-end="translate-x-full"

                            class="inline-block relative w-screen max-w-5xl h-full">

                            <div class="group h-full flex flex-col py-6 bg-white  shadow
                            overflow-y-scroll transform transition duration-700 ease-in-out border-l-4 shadow hover:border-gray-500 border-gray-300">
                                <div class="px-4 sm:px-6">


                                    <h2 class="text-lg font-medium text-gray-900
                                    transform transition duration-700 ease-in-out"
                                        id="slide-over-title">
                                        <template  x-if="$wire.isOpen = true">
                                        <div

                                            x-transition:enter="transform transition ease-in-out duration-1000"
                                            x-transition:enter-start="opacity-0"
                                            x-transition:enter-end="opacity-100"
                                            x-transition:leave="transform transition ease-in-out duration-1000"
                                            x-transition:leave-start="opacity-100"
                                            x-transition:leave-end="opacity-0"

                                            class="inline-flex">
                                            <button  wire:click="$toggle('isOpen')" class="rounded-md text-gray-300 hover:text-gray-500 focus:outline-hidden focus:ring-2 focus:ring-white align-text-bottom">
                                                <span class="sr-only">Close panel</span>
                                                <!-- Heroicon name: outline/x -->
                                                <svg class="h-4 w-4 " xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                        </template>
                                        Email Rule <span class="0  transform transition duration-700 ease-in-out">{{ $state['name'] }}</span>
                                    </h2>
                                </div>
                                <div class="mt-6 relative flex-1 px-4 sm:px-6">
                                    <!-- Replace with your content -->
                                    <div class="absolute inset-0 px-4 sm:px-6">
                                        <!--border-2 border-dashed border-gray-300 -->
                                        <div class="h-full " aria-hidden="true">
                                            <form wire:submit.prevent="saveRules">

                                                <div class="my-4">
                                                    <x-label for="name" value="{{ __('Rule Nickname') }}" />
                                                    <span class="text-xs ">An easy to remember nickname for the rule</span>
                                                    <x-input id="name" type="text" class="mt-1 block w-full " wire:model.defer="state.name" />
                                                    <x-input-error for="state.name" class="mt-2" />
                                                </div>

                                                <div class="my-4">
                                                    <x-label for="category" value="{{ __('Call Category') }}" />
                                                    <span class="text-xs ">Emails that match this rule will send this category to the script.</span>
                                                    <x-input id="category" type="text" class="mt-1 block w-full " wire:model.defer="state.category" />
                                                    <x-input-error for="state.category" class="mt-2" />
                                                </div>

                                                <div class="my-4">
                                                    <x-label for="account" value="{{ __('Account') }}" />
                                                    <span class="text-xs ">The account to use for MergeComm inbound email.</span>
                                                    <x-input id="account" type="text" class="mt-1 block w-full " wire:model.defer="state.account" />
                                                    <x-input-error for="state.account" class="mt-2" />
                                                </div>

                                              <div class="my-4">
                                                  <x-label for="enabled" value="{{ __('Status') }}" />
                                                  <span class="text-xs ">
                                                       Whether the inbound email rule is enabled for processing.
                                                    </span>
                                                  <select class="mt-1 block w-full  border-gray-300
                                                     focus:border-indigo-300
                                                  focus:ring focus:ring-indigo-200 rounded-md
                                                  shadow "
                                                          name="enabled" wire:model.defer="state.enabled">
                                                      <option value=""></option>
                                                      <option value="1">Enabled</option>
                                                      <option value="0">Disabled</option>
                                                  </select>
                                                  <x-input-error for="state.enabled" class="mt-2" />
                                              </div>


                                                <div class="my-4">
                                                    <x-label for="rules" value="{{ __('Rules') }}" />
                                                    <span class="text-xs ">A list of rules to match against incoming email.</span>

                                                    <div class="flex my-2" x-data="">
                                                        <select x-ref="field" class="mt-1 block w-full  border-gray-300
                                                     focus:border-indigo-300
                                                  focus:ring focus:ring-indigo-200 rounded-md
                                                  shadow  h-8 text-xs mx-1">
                                                            <option value="to">To</option>
                                                            <option value="from">From</option>
                                                            <option value="subject">Subject</option>
                                                            <option value="text">Body</option>
                                                            <option value="attachment">Attachment</option>
                                                        </select>
                                                        <select x-ref="modifier" class="mt-1 block w-full  border-gray-300
                                                     focus:border-indigo-300
                                                  focus:ring focus:ring-indigo-200 rounded-md
                                                  shadow  h-8 text-xs mx-1">
                                                            <option value="exact_match">Exact Match</option>
                                                            <option value="contains">Contains</option>
                                                            <option value="starts_with">Starts With</option>
                                                            <option value="ends_with">Ends With</option>
                                                        </select>
                                                        <input x-ref="item" value="" class="mt-1 block w-full  border-gray-300
                                                     focus:border-indigo-300
                                                  focus:ring focus:ring-indigo-200 rounded-md
                                                  shadow  h-8 text-xs"/>
                                                        <a href="#"  @click="$wire.addRule($refs.field.value,$refs.modifier.value,$refs.item.value);" class="cursor-pointer text-indigo-300 hover:text-gray-500 transform transition duration-500 ease-in-out px-2 w-1/4 mx-1">
                                                            <svg class="align-text-bottom p-1 my-1 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                                                        </a>
                                                    </div>


                                                    <div class="block p-4 shadow-inner bg-gray-200 rounded-lg min-h-full mx-1">


                                                        @php
                                                            $rules_array = $this->state['rules'];
                                                        @endphp
                                                        @if(is_array($rules_array))
                                                            @foreach($rules_array as $field => $modifiers )
                                                                @foreach($modifiers as $modifier => $items)
                                                                    @foreach($items as $k => $item )

                                                                        <div  class="flex-row mt-1 block w-full ">
                                                                            <code class="bg-indigo-500 text-white rounded px-2">{{ $field }}</code> <span class="italic font-semibold text-indigo-400">{{ $modifier }}</span> {{ $item }}
                                                                            <input type="hidden" name="rules[{{$field}}][{{ $modifier }}][]" value="{{ $item }}" wire:mode.defer="state.rules.{{$field}}.{{$modifier}}.{{ $k }}.{{ $item }}">
                                                                            <a wire:click="removeRule('{{$field}}', '{{$modifier}}', '{{$k}}','{{$item}}');" href="#" class="text-indigo-500 hover:text-red-500 transform transition duration-700 ease-in-out">
                                                                                <svg class="w-4 h-4 inline-flex align-text-bottom" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                                            </a>
                                                                        </div>
                                                                    @endforeach
                                                                @endforeach

                                                            @endforeach
                                                        @endif
                                                    </div>

                                                    <x-input-error for="state.rules" class="mt-2" />


                                                </div>
                                                <div class="flex items-center justify-end px-4 py-3 bg-gray-50    text-right
                                                sm:px-6 shadow sm:rounded-md sm:rounded-md">

                                                    @if(isset($state['id']))
                                                        <div class="mr-3">
                                                            <x-danger-button wire:loading.attr="disabled" wire:click="$toggle('deleteRuleModal');">
                                                                {{ __('Delete') }}
                                                            </x-danger-button>
                                                        </div>
                                                    @endif

                                                    <x-action-message class="mr-3 " on="saved">
                                                        {{ __('Saved.') }}
                                                    </x-action-message>

                                                    <x-button>
                                                        {{ __('Save') }}
                                                    </x-button>
                                                </div>
                                            </form>

                                        </div>
                                    </div>
                                    <!-- /End replace -->
                                </div>
                            </div>
                        </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Rule Confirmation Modal -->
        <x-confirmation-modal wire:model="deleteRuleModal">
            <x-slot name="title">
                <div class="">
                    {{ __('Delete Inbound Email Rule?') }}
                </div>
            </x-slot>

            <x-slot name="content">
                <div class="">
                    {{ __('Are you sure you want to delete this inbound email rule? Once a rule is deleted, it cannot be retrieved.') }}
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="$toggle('deleteRuleModal')" wire:loading.attr="disabled">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button class="ml-2" wire:click="deleteRule" wire:loading.attr="disabled">
                    {{ __('Delete Rule') }}
                </x-danger-button>
            </x-slot>
        </x-confirmation-modal>

    @endif
</div>

