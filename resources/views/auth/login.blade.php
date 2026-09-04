@php
    use App\Models\System\Settings;
@endphp
<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <x-validation-errors class="mb-4" />

        @if (session('status'))
            <div class="mb-4 font-medium text-sm text-success">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div>
                <x-label for="email" value="{{ __('Email') }}" />
                <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            </div>

            <div class="mt-4">
                <x-label for="password" value="{{ __('Password') }}" />
                <x-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            </div>

            <div class="block mt-4">
                <label for="remember_me" class="flex items-center">
                    <x-checkbox id="remember_me" name="remember" />
                    <span class="ml-2 text-sm text-surface-fg-soft">{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">
                @if (Route::has('password.request'))
                    <a class="underline text-sm text-surface-fg-soft hover:text-surface-fg " href="{{ route('password.request') }}">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif

                <x-button class="ml-4">
                    {{ __('Log in') }}
                </x-button>
            </div>

            @php
                try{
                    $settings = Settings::first();
                }
                catch(Exception $e){
                    $settings = null;
                }
            @endphp
            @if(!is_null($settings) && !is_null($settings->saml2_enabled) && $settings->saml2_enabled === 1)
                <div class="block mt-4">
                    <div class="relative mt-6 mb-2">
                        <div class="absolute inset-0 flex items-center" aria-hidden="true">
                            <div class="w-full border-t border-border"></div>
                        </div>
                        <div class="relative flex justify-center text-sm font-medium leading-6">
                            <span class="bg-surface px-6 text-muted">Enterprise SAML2 SSO</span>
                        </div>
                    </div>
                    <a class="underline hover:bg-surface-3 hover:border-border bg-surface-3 border border-border
                            transition duration-700 ease-in-out
                            text-sm py-2 text-surface-fg-soft hover:text-surface-fg text-center block w-full rounded"
                       href="{{ route('sso.saml2.redirect') }}">
                        {{ __('Login with Single Sign-On') }}
                    </a>
                </div>
            @endif
        </form>
    </x-authentication-card>
</x-guest-layout>
