@php
    use App\Models\MergeCommISWebTrigger;
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
                        class="transform transition absolute inset-0 bg-black transition-opacity fixed-bottom" aria-hidden="true"></div>
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

                            class="relative w-screen max-w-5xl h-full">
                            <!--
                              Close button, show/hide based on slide-over state.
                            -->


                            <!--
                             -->
                            <div class="group h-full flex flex-col py-6 bg-white  shadow
                            overflow-y-scroll transform transition duration-700 ease-in-out border-l-4 shadow hover:border-white border-indigo-300 z-100">


                                <div class="px-4 sm:px-6 z-100">


                                    <h2 class="text-lg font-medium text-gray-900
                                    transform transition duration-700 ease-in-out"
                                        id="slide-over-title">
                                        <template x-if="$wire.isOpen = true">
                                        <div
                                            x-transition:enter="transform transition ease-in-out duration-1000"
                                            x-transition:enter-start="opacity-0"
                                            x-transition:enter-end="opacity-100"
                                            x-transition:leave="transform transition ease-in-out duration-1000"
                                            x-transition:leave-start="opacity-100"
                                            x-transition:leave-end="opacity-0"

                                            class="inline-flex">
                                            <button  wire:click="$toggle('isOpen')" class="rounded-md text-gray-500 hover:text-black focus:outline-hidden focus:ring-2 focus:ring-white align-text-bottom">
                                                <span class="sr-only">Close panel</span>
                                                <!-- Heroicon name: outline/x -->
                                                <svg class="h-4 w-4 " xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                        </template>
                                        Email <span class="0  transform transition duration-700 ease-in-out">{{ $email->id }}</span>
                                    </h2>
                                </div>
                                <div class="mt-6 relative flex-1 px-4 sm:px-6 z-100">
                                    <!-- Replace with your content -->
                                    <div class="absolute inset-0 px-4 sm:px-6">
                                        <!--border-2 border-dashed border-gray-300 -->
                                        <div class="h-full " aria-hidden="true">
                                            <form wire:submit="$toggle('isOpen')">

                                                <div class="my-4">
                                                    <x-label for="created_at" value="{{ __('Email Processed') }}" />
                                                    <x-input disabled id="created_at" type="text" class="mt-1 block w-full " wire:model.live="state.created_at" />

                                                </div>

                                                <div class="my-4">
                                                    <x-label for="subject" value="{{ __('Email Subject') }}" />
                                                    <x-input disabled id="name" type="text" class="mt-1 block w-full " wire:model.live="state.subject" />

                                                </div>
                                                <div class="my-4">
                                                    <x-label for="to" value="{{ __('Email To') }}" />

                                                    <x-input disabled id="to" type="text" class="mt-1 block w-full " wire:model.live="state.to" />

                                                </div>
                                                <div class="my-4">
                                                    <x-label for="from" value="{{ __('Email From') }}" />

                                                    <x-input disabled id="from" type="text" class="mt-1 block w-full " wire:model.live="state.from" />

                                                </div>
                                                <div class="my-4">
                                                    <x-label for="text" value="{{ __('Email Body') }}" />

                                                    <textarea rows="10" disabled id="text" class="mt-1 block w-full h-full  border-gray-300     focus:border-indigo-300 focus:ring focus:ring-indigo-200 rounded-md shadow ">{{ $this->state['text'] }}</textarea>

                                                </div>

                                                <div class="flex items-center justify-end px-4 py-3 bg-gray-50    text-right
                                                sm:px-6 shadow sm:rounded-md sm:rounded-md">

                                                    <x-action-message class="mr-3 " on="forwarded">
                                                        {{ __('Email forwarded.') }}
                                                    </x-action-message>

                                                    <div class="mr-3">
                                                        <x-secondary-button wire:click="$toggle('isOpen')" wire:loading.attr="disabled">
                                                            {{ __('Cancel') }}
                                                        </x-secondary-button>
                                                    </div>

                                                    <div class="mr-3">
                                                        <x-button wire:click.prevent="$toggle('forwardEmailModal')" wire:loading.attr="disabled">
                                                            {{ __('Forward') }}
                                                        </x-button>
                                                    </div>

                                                    <div class="mr-3">
                                                        <x-button wire:click.prevent="$toggle('processEmailModal')" wire:loading.attr="disabled">
                                                            {{ __('Re-Process') }}
                                                        </x-button>
                                                    </div>

                                                    <x-danger-button wire:loading.attr="disabled" wire:click.prevent="$toggle('deleteEmailModal');">
                                                        {{ __('Delete') }}
                                                    </x-danger-button>
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
        <x-confirmation-modal wire:model.live="deleteEmailModal">
            <x-slot name="title">
                <div class="">
                    {{ __('Delete Inbound Email?') }}
                </div>
            </x-slot>

            <x-slot name="content">
                <div class="">
                    {{ __('Are you sure you want to delete this inbound email? Once an email is deleted, it cannot be retrieved.') }}
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="$toggle('deleteEmailModal')" wire:loading.attr="disabled">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button class="ml-2" wire:click="deleteEmail" wire:loading.attr="disabled">
                    {{ __('Delete Email') }}
                </x-danger-button>
            </x-slot>
        </x-confirmation-modal>


        <!-- forward to confirmation modal -->
        <x-confirmation-modal wire:model.live="forwardEmailModal">
            <x-slot name="title">
                <div class="">
                    {{ __('Forward Inbound Email') }}
                </div>
            </x-slot>

            <x-slot name="content">
                <div class="">
                    <div class="block my-4l">
                        <x-label for="forward_email" value="{{ __('Email Address') }}" />
                        <span class="text-xs ">The email address to forward the message to</span>
                        <x-input id="forward_email" type="email" class="mt-1  block w-full " wire:model.live="state.forward_email" />
                        <x-input-error for="state.forward_email" class="mt-2" />
                    </div>
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="$toggle('forwardEmailModal')" wire:loading.attr="disabled">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-button class="ml-2" wire:click="forwardEmail" wire:loading.attr="disabled">
                    {{ __('Forward Email') }}
                </x-button>
            </x-slot>
        </x-confirmation-modal>



        <!-- reprocess email Confirmation Modal -->
        <x-confirmation-modal wire:model.live="processEmailModal">
            <x-slot name="title">
                <div class="">
                    {{ __('Re-Process Inbound Email?') }}
                </div>
            </x-slot>

            <x-slot name="content">
                <div class="">
                    {{ __('Are you sure you want to re-process this inbound email? Inbound Email Rules will be re-applied and matches will be sent into MergeComm again.') }}
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click.prevent="$toggle('processEmailModal')" wire:loading.attr="disabled">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-button class="ml-2" wire:click.prevent="processEmail" wire:loading.attr="disabled">
                    {{ __('Process Email') }}
                </x-button>
            </x-slot>
        </x-confirmation-modal>

    @endif

</div>

