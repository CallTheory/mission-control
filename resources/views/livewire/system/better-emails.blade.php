@php
    use Illuminate\Support\Facades\File;
@endphp
<div class="block w-full">
    <div class="inline-block w-full px-2">
        <h2 class="text-lg font-semibold">Better Emails</h2>
        <p class="text-md text-gray-500">
            These settings allow you to preview and set the default Better Email options for new clients.
            You can of course override the default settings for individual clients.
        </p>

        <x-alert-beta title="Beta Feature" description="This feature is ready for testing, feedback, and small production deployments."/>

    </div>

    <div class="mx-2 p-4 bg-gray-50 rounded-sm border border-gray-300 shadow">
        <h2 class="text-lg font-semibold">Default Mail Settings</h2>
        <div class="flex flex-wrap">
            <div class="my-2 mx-2">
                <label for="title" class="font-semibold text-sm text-gray-500">Title</label>
                <x-input id="title"
                         type="text"
                         class="mt-1 block border border-gray-300 rounded-md shadow"
                         wire:model.defer="title" />
            </div>
            <div class="my-2 mx-2 ">
                <label for="description" class="font-semibold text-sm text-gray-500">Description</label>
                <x-input id="description"
                         type="text"
                         class="mt-1 block border border-gray-300 rounded-md shadow"
                         wire:model.defer="description" />
            </div>
            <div class="my-2 mx-2 ">
                <label for="logo" class="font-semibold text-sm text-gray-500">Logo Url</label>
                <x-input id="logo"
                         type="text"
                         class="mt-1  block border border-gray-300 rounded-md shadow"
                         wire:model.defer="logo" />
            </div>
            <div class="my-2 mx-2 ">
                <label for="logo_alt" class="font-semibold text-sm text-gray-500">Logo Alt Text</label>
                <x-input id="logo_alt"
                         type="text"
                         class="mt-1  block border border-gray-300 rounded-md shadow"
                         wire:model.defer="logo_alt"  />
            </div>
            <div class="my-2 mx-2 ">
                <label for="logo_link" class="font-semibold text-sm text-gray-500">Logo Link</label>
                <x-input id="logo_link"
                         type="text"
                         class="mt-1  block border border-gray-300 rounded-md shadow"
                         wire:model.defer="logo_link"  />
            </div>
            <div class="my-2 mx-2 ">
                <label for="button_text" class="font-semibold text-sm text-gray-500">Button Text</label>
                <x-input id="button_text"
                         type="text"
                         class="mt-1  block border border-gray-300 rounded-md shadow"
                         wire:model.defer="button_text" />
            </div>
            <div class="my-2 mx-2 ">
                <label for="button_link" class="font-semibold text-sm text-gray-500">Button Link</label>
                <x-input id="button_link"
                         type="text"
                         class="mt-1  block border-gray-300 rounded-md shadow"
                         wire:model.defer="button_link" />
            </div>
            <div class="my-2 mx-2 ">
                <label for="theme" class="font-semibold text-sm text-gray-500">Theme</label>
                <select class="focus:border-indigo-300 focus:ring-3 focus:ring-indigo-200 rounded-md shadow  mt-1 block border border-gray-300 rounded-md shadow-m"
                        name="theme"
                        id="theme"
                        wire:model.defer="theme">
                    @if(File::exists(resource_path('views/emails/better-emails/custom.blade.php')))
                        <option value="custom">Custom</option>
                    @endif
                    <option value="standard">Standard - Light</option>
                    <option value="dark">Standard - Dark</option>
                </select>
            </div>
            <div class="my-2 mx-2 ">
                <label for="message_history" class="font-semibold text-sm text-gray-500">Message History</label>
                <select class="focus:border-indigo-300 focus:ring focus:ring-indigo-200 rounded-md shadow  mt-1 block border border-gray-300 rounded-md shadow-m"
                        id="message_history_toggle"
                        wire:model.defer="message_history">
                    <option value="1">Message History: true</option>
                    <option value="0">Message History: false</option>
                </select>
            </div>
            <div class="my-2 mx-2 ">
                <label for="report_metadata" class="font-semibold text-sm text-gray-500">Report Metadata</label>
                <select class="focus:border-indigo-300 focus:ring focus:ring-indigo-200 rounded-md shadow  mt-1 block border border-gray-300 rounded-md shadow-m"
                        id="report_metadata_toggle"
                        wire:model.defer="report_metadata">
                    <option value="1">Report Metadata: true</option>
                    <option value="0">Report Metadata: false</option>
                </select>
            </div>
            <div class="my-2 mx-2 ">
                <label for="example_file" class="font-semibold text-sm text-gray-500">Example File</label>
                <select class="focus:border-indigo-300 focus:ring focus:ring-indigo-200 rounded-md shadow  mt-1 block border border-gray-300 rounded-md shadow-m"
                        id="example_file"
                        wire:model.defer="example_file">
                    <option value="messages 5520 06112024-070001.txt">Multiple Messages</option>
                    <option value="messages 5520 06042024-070000.txt">Single Message</option>
                    <option value="messages 5520 06052024-070001.txt">No Messages</option>
                </select>
            </div>

            <div class="my-2 mx-2 ">
                <label for="canspam_company" class="font-semibold text-sm text-gray-500">CAN-SPAM Company</label>
                <x-input id="canspam_company"
                         type="text"
                         class="mt-1  block border-gray-300 rounded-md shadow"
                         wire:model.defer="canspam_company" />
            </div>

            <div class="my-2 mx-2 ">
                <label for="canspam_address" class="font-semibold text-sm text-gray-500">CAN-SPAM Address</label>
                <x-input id="canspam_address"
                         type="text"
                         class="mt-1  block border-gray-300 rounded-md shadow"
                         wire:model.defer="canspam_address" />
            </div>
            <div class="my-2 mx-2 ">
                <label for="canspam_address2" class="font-semibold text-sm text-gray-500">CAN-SPAM Address - Line 2</label>
                <x-input id="canspam_address2"
                         type="text"
                         class="mt-1  block border-gray-300 rounded-md shadow"
                         wire:model.defer="canspam_address2" />
            </div>

            <div class="my-2 mx-2 ">
                <label for="canspam_city" class="font-semibold text-sm text-gray-500">CAN-SPAM City/Locality</label>
                <x-input id="canspam_city"
                         type="text"
                         class="mt-1  block border-gray-300 rounded-md shadow"
                         wire:model.defer="canspam_city" />
            </div>

            <div class="my-2 mx-2 ">
                <label for="canspam_state" class="font-semibold text-sm text-gray-500">CAN-SPAM State/Province</label>
                <x-input id="canspam_state"
                         type="text"
                         class="mt-1  block border-gray-300 rounded-md shadow"
                         wire:model.defer="canspam_state" />
            </div>

            <div class="my-2 mx-2 ">
                <label for="canspam_postal" class="font-semibold text-sm text-gray-500">CAN-SPAM Postal/Zip</label>
                <x-input id="canspam_postal"
                         type="text"
                         class="mt-1  block border-gray-300 rounded-md shadow"
                         wire:model.defer="canspam_postal" />
            </div>

            <div class="my-2 mx-2 ">
                <label for="canspam_country" class="font-semibold text-sm text-gray-500">CAN-SPAM Country</label>
                <x-input id="canspam_country"
                         type="text"
                         class="mt-1  block border-gray-300 rounded-md shadow"
                         wire:model.defer="canspam_country" />
            </div>

            <div class="my-2 mx-2 ">
                <label for="canspam_email" class="font-semibold text-sm text-gray-500">CAN-SPAM Email</label>
                <x-input id="canspam_email"
                         type="email"
                         class="mt-1  block border-gray-300 rounded-md shadow"
                         wire:model.defer="canspam_email" />
            </div>

            <div class="my-2 mx-2 ">
                <label for="canspam_phone" class="font-semibold text-sm text-gray-500">CAN-SPAM Phone</label>
                <x-input id="canspam_phone"
                         type="text"
                         class="mt-1 block border-gray-300 rounded-md shadow"
                         wire:model.defer="canspam_phone" />
            </div>

            <div class="my-2 mx-2 h-full align-bottom mt-auto mb-2 flex">
                <x-secondary-button id="updatePreview" class="block mr-2 py-3" wire:click="$refresh">Preview</x-secondary-button>
                <x-button id="savePreview" class="block mr-2 py-3" wire:click="saveDefaultSettings" >Save</x-button>
                <x-action-message on="saved" class="text-green-500 my-2">Saved!</x-action-message>
            </div>
        </div>
    </div>

    <div class="border border-gray-300 rounded pb-8 px-4 mx-2 shadow mt-4">
        <h3 class="px-4 mt-4 mb-2 font-semibold text-lg">Mail Template</h3>
        <div class="px-4">
            We recommend you run your HTML through a <a class="font-semibold text-indigo-900 hover:text-indigo-700" href="https://premailer.github.io/premailer/" rel="external">standard premailer</a> for maximum email client compatibility.
            <div class="my-2">
                <x-secondary-button wire:click="downloadThemeTemplate">
                    Download Theme Template
                </x-secondary-button>
                <x-input-error for="download_template" class="mt-2" />
            </div>
        </div>
        <div class="px-4 text-sm mb-4">
            The available fields are listed below and also are contained in the example template. Fields with a <code class="border border-gray-300 bg-gray-100 px-1 rounded font-medium text-lg">#</code> symbol are conditional and will only be included if the condition is met.
        </div>
        <div class="flex flex-wrap space-x-4 text-xs px-4">
            <ul class="list-disc list-inside">
                <span class="font-semibold">Email Details:</span>
                <li class="font-mono text-gray-500">[[title]]</li>
                <li class="font-mono text-gray-500">[[description]]</li>
                <li class="font-mono text-gray-500">[[date]]</li>
                <li class="font-mono text-gray-500">[[time]]</li>
                <li class="font-mono text-gray-500">[[unsubscribe_link]]</li>
            </ul>
            <ul class="list-disc list-inside">
                <span class="font-semibold">Logo Details:</span>
                <li class="font-mono text-gray-500">[[logo_link]]</li>
                <li class="font-mono text-gray-500">[[logo_src]]</li>
                <li class="font-mono text-gray-500">[[logo_alt]]</li>
            </ul>
            <ul class="list-disc list-inside">
                <span class="font-semibold">Metadata:</span>
                <li class="font-mono text-gray-500">[[#include_report_metadata]]</li>
                <li class="font-mono text-gray-500">[[account]]</li>
                <li class="font-mono text-gray-500">[[type]]</li>
            </ul>
            <ul class="list-disc list-inside">
                <span class="font-semibold">Messages and History:</span>
                <li class="font-mono text-gray-500">[#messages]]</li>
                <li class="font-mono text-gray-500">[[#message_lines]]</li>
                <li class="font-mono text-gray-500">[[message]]</li>
                <li class="font-mono text-gray-500">[[#include_message_history]]</li>
                <li class="font-mono text-gray-500">[[#history]]</li>
                <li class="font-mono text-gray-500">[[history_line]]</li>
            </ul>
            <ul class="list-disc list-inside">
                <span class="font-semibold">CTA Button:</span>
                <li class="font-mono text-gray-500">[[button_link]]</li>
                <li class="font-mono text-gray-500">[[button_text]]</li>
            </ul>
            <ul class="list-disc list-inside">
                <span class="font-semibold">CAN-SPAM:</span>
                <li class="font-mono text-gray-500">[[company]]</li>
                <li class="font-mono text-gray-500">[[address]]</li>
                <li class="font-mono text-gray-500">[[address2]]</li>
                <li class="font-mono text-gray-500">[[city]]</li>
                <li class="font-mono text-gray-500">[[state]]</li>
                <li class="font-mono text-gray-500">[[postal]]</li>
                <li class="font-mono text-gray-500">[[country]]</li>
                <li class="font-mono text-gray-500">[[email]]</li>
                <li class="font-mono text-gray-500">[[phone]]</li>
            </ul>
        </div>
    </div>

    <div class="mx-2">

        <h3 class="mt-4 mb-2 font-semibold text-lg">Example Preview</h3>

        <iframe id="email-theme-preview-window"
                src="{{ $preview_url }}"
                class="border border-gray-300 rounded-md shadow mb-4 w-full min-h-screen"></iframe>
    </div>

</div>
