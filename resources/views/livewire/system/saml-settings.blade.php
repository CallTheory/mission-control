<x-form-section submit="saveSamlSettings">
    <x-slot name="title">
        {{ __('Enterprise SSO using SAML2') }}
    </x-slot>

    <x-slot name="description">
        Enterprise SAML2 Service Provider for SSO with most 3rd-party SAML2 Identity Providers (IdP)
    </x-slot>

    <x-slot name="form">

        <!-- SAML Enabled -->
        <div class="col-span-6 sm:col-span-4">
            <div x-data="{ isEnabled: $wire.saml_enabled }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="saml_support-enabled-label">SAML Support</span>
                    <span class="text-sm text-gray-500 mt-1" id="saml_support-description">
                        Use Mission Control <strong>SAML2 Service Provider (SP)</strong> for SSO
                    </span>
                </span>
                <button
                    type="button"
                    :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                    role="switch"
                    :aria-checked="isEnabled.toString()"
                    aria-labelledby="saml_support-enabled-label"
                    aria-describedby="saml_support-description"
                    wire:click="toggleSamlSupport(); isEnabled = !isEnabled"
                >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                </button>

            </div>
            <x-input-error for="saml_enabled" class="mt-2 flex flex-grow flex-col" />
        </div>

        @if($saml_enabled)
            <div class="col-span-6">
                <dl>
                    <div class="my-2">
                        <dt class="font-semibold">Identifier (Entity ID)</dt>
                        <dd><code class="text-sm">{{ secure_url('/sso/saml2') }}</code></dd>
                    </div>
                    <div class="my-2">
                        <dt class="font-semibold">Reply URL (Assertion Consumer Service URL, or ACS URL)</dt>
                        <dd><code class="text-sm">{{ secure_url('/sso/saml2/callback') }}</code></dd>
                    </div>
                    <div class="my-2">
                        <dt class="font-semibold">SP Sign on URL</dt>
                        <dd><code class="text-sm">{{ secure_url('/login') }}</code></dd>
                    </div>
                    <div class="my-2">
                        <dt class="font-semibold">Relay State (Start URL)</dt>
                        <dd><code class="text-sm">{{ secure_url('/dashboard') }}</code></dd>
                    </div>
                    <div class="mt-2 text-gray-400">
                        <dt class="font-semibold">Logout URL</dt>
                        <dd class="text-sm">
                            This feature is not supported for security considerations<br>
                            SLO requires <code class="font-semibold">SameSite=None</code> insecure cookie setting</dd>
                    </div>
                </dl>
            </div>

            <hr class="col-span-6 my-2 border border-gray-300">

            <div class="col-span-6">
                <h3 class="text-gray-800">Mission Control SP Metadata URL</h3>
                <p class="mb-4 text-gray-500 text-sm">{{ secure_url('/sso/saml2/metadata') }}</p>
                <a href="/sso/saml2/metadata"
                   class="inline-flex flex whitespace-nowrap text-sm px-2 py-1 bg-indigo-700 text-indigo-50 hover:bg-indigo-600 hover:text-white mb-2 font-semibold rounded-lg shadow transition duration-700 ease-in-out">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-white h-4 my-1 mr-1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 9.75 16.5 12l-2.25 2.25m-4.5 0L7.5 12l2.25-2.25M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />
                    </svg>
                    View SP Metadata XML
                </a>
            </div>

            <hr class="col-span-6 my-2 border border-gray-300">

            <div class="col-span-6 my-4">
                <p class="mb-4">
                    Mission Control expects the following SAML2 <strong>Attribute</strong> mappings:
                </p>
                <ul class="list-disc list-inside ml-4">
                    <li class="my-1"><code class="font-semibold">emailaddress</code> &ndash; The full email address of the user's IdP account</li>
                    <li class="my-1"><code class="font-semibold">givenname</code> &ndash; The first name, nickname, or full-name of the user's IdP account</li>
                </ul>
            </div>

            <hr class="col-span-6 my-2 border border-gray-300">

            <!-- SAML Stateless Redirect -->
            <div class="col-span-6 sm:col-span-4">
                <div x-data="{ isEnabled: $wire.stateless_redirect }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="stateless_redirect-enabled-label">Stateless Redirect</span>
                    <span class="text-sm text-gray-500 mt-1" id="saml_support-description">
                        Enable stateless redirect for SAML2 SSO
                    </span>
                </span>
                    <button
                        type="button"
                        :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                        role="switch"
                        :aria-checked="isEnabled.toString()"
                        aria-labelledby="stateless_redirect-enabled-label"
                        aria-describedby="stateless_redirect-description"
                        wire:click="toggleStatlessRedirect(); isEnabled = !isEnabled"
                    >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                    </button>

                </div>
                <x-input-error for="stateless_redirect" class="mt-2 flex flex-grow flex-col" />
            </div>
            <!-- End SAML Stateless Redirect -->

            <!-- SAML Stateless Callback -->
            <div class="col-span-6 sm:col-span-4">
                <div x-data="{ isEnabled: $wire.stateless_callback }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="stateless_callback-enabled-label">Stateless Callback</span>
                    <span class="text-sm text-gray-500 mt-1" id="stateless_callback-description">
                        Enable stateless callback for SAML2 SSO (IdP initiated logins)
                    </span>
                </span>
                    <button
                        type="button"
                        :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                        role="switch"
                        :aria-checked="isEnabled.toString()"
                        aria-labelledby="stateless_callback-enabled-label"
                        aria-describedby="stateless_callback-description"
                        wire:click="toggleStatelessCallback(); isEnabled = !isEnabled"
                    >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                    </button>

                </div>
                <x-input-error for="stateless_callback" class="mt-2 flex flex-grow flex-col" />
            </div>
            <!-- End SAML Stateless Callback -->

            <!-- SAML Sign Assertions -->
            <div class="col-span-6 sm:col-span-4">
                <div x-data="{ isEnabled: $wire.sign_assertions }" class="flex items-center justify-between">
                <span class="flex flex-grow flex-col">
                    <span class="text-md font-semibold leading-6 text-gray-900" id="sign_assertions-enabled-label">Sign Assertions</span>
                    <span class="text-sm text-gray-500 mt-1" id="sign_assertions-description">
                        Enable signing of SAML2 assertions
                    </span>
                </span>
                    <button
                        type="button"
                        :class="{ 'bg-indigo-600': isEnabled, 'bg-gray-200': !isEnabled }"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2"
                        role="switch"
                        :aria-checked="isEnabled.toString()"
                        aria-labelledby="sign_assertions-enabled-label"
                        aria-describedby="sign_assertions-description"
                        wire:click="toggleSignAssertions(); isEnabled = !isEnabled"
                        wire:confirm="The SAML Assertion signing certificate will be rotated.\nThe existing certificate and private key will be cleared.\n\nAre you sure you want to continue?"
                    >
                    <span
                        aria-hidden="true"
                        :class="{ 'translate-x-5': isEnabled, 'translate-x-0': !isEnabled }"
                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                    ></span>
                    </button>

                </div>
                <x-input-error for="sign_assertions" class="mt-2 flex flex-grow flex-col" />
            </div>
            <!-- End SAML Sign Assertions -->
        @endif

        @if($sign_assertions && $saml_enabled)
            <div class="col-span-6">
                <dl>
                    <div class="mb-2">
                        <dt class="font-semibold">Certificate Thumbprint</dt>
                        <dd><code class="text-sm text-gray-500">{{ strtoupper($cert_fingerprint ?? '') }}</code></dd>
                    </div>
                    <div class="my-2">
                        <dt class="font-semibold">Certificate Valid Dates</dt>
                        <dd class="text-sm text-gray-500">
                            <span>{{ $cert_valid_from ?? '' }}</span> &mdash;
                            <span>{{ $cert_valid_to ?? '' }}</span></dd>
                    </div>
                </dl>
                <a href="/system/saml-settings/download-cert"
                   class="inline-flex flex whitespace-nowrap text-sm px-2 py-1 bg-indigo-700 text-indigo-50 hover:bg-indigo-600 hover:text-white mb-2 font-semibold rounded-lg shadow transition duration-700 ease-in-out">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="text-white h-4 my-1 mr-1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Public Certificate
                </a>
            </div>
        @endif

        @if($metadata_url)
            <div x-data="{ currentTab: 'metadata_url_tab'}" class="col-span-6">
        @elseif($metadata_xml)
            <div x-data="{ currentTab: 'metadata_xml_tab'}" class="col-span-6">
        @else
            <div x-data="{ currentTab: 'metadata_url_tab'}" class="col-span-6">
        @endif
        @if($saml_enabled)
            <div class="mb-4">
                <div class="hidden sm:block">
                    <div class="border-b border-gray-300">
                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                            <a @click="currentTab = 'metadata_url_tab'" href="#"
                               class="whitespace-nowrap border-b-2  px-1 py-4 text-sm font-medium"
                               :class="{'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700': currentTab !== 'metadata_url_tab', 'text-indigo-600 border-indigo-500': currentTab === 'metadata_url_tab'}"
                            >
                                Metadata URL
                            </a>
                            <a @click="currentTab = 'metadata_xml_tab'" href="#"
                               class="whitespace-nowrap border-b-2 px-1 py-4 text-sm font-medium"
                               :class="{'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700': currentTab !== 'metadata_xml_tab', 'text-indigo-600 border-indigo-500': currentTab === 'metadata_xml_tab'}"
                            >
                                Metadata XML
                            </a>
                        </nav>
                    </div>
                </div>
            </div>

            <div x-show="currentTab === 'metadata_url_tab'" class="col-span-6 sm:col-span-4">

                <x-alert-info title="Recommended" description="Use the Metadata URL option if your Identity Provider (IdP) supports it" />
                <x-label for="metadata_url" value="{{ __('Metadata URL') }}" />
                <x-input type="text" wire:model="metadata_url" id="metadata_url" class="mt-1 w-full" />
                <small class="block text-sm text-gray-500 my-2">
                    The URL to the SAML2 metadata file for the Identity Provider (IdP)
                </small>
                <x-input-error for="metadata_url" class="mt-2" />
            </div>

            <div x-show="currentTab === 'metadata_xml_tab'" id="metadata_xml_tab"  class="col-span-6 sm:col-span-4">
                <x-label for="metadata_xml" value="{{ __('Metadata XML') }}" />
                <textarea rows="10" wire:model="metadata_xml" id="metadata_xml" class="mt-1 block w-full  border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 rounded-md shadow "></textarea>
                <small class="block text-sm text-gray-500 my-2">
                    The XML content of the SAML2 metadata file for your Identity Provider (IdP)
                </small>
                <x-input-error for="metadata_xml" class="mt-2" />
            </div>
        @endif
        </div>

    </x-slot>

    @if($saml_enabled)
        <x-slot name="actions">
            <x-action-message class="mr-3 " on="saved">
                {{ __('Saved.') }}
            </x-action-message>

            <x-button>
                {{ __('Save') }}
            </x-button>

        </x-slot>
    @endif
</x-form-section>
