<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="mb-4 text-sm text-gray-600">
            {{ __('Forgot your password? Send a password reset link to your registered email address.') }}
        </div>

        @if (session('status'))
            <div class="mb-4 font-medium text-sm rounded-md p-4 text-green-100 shadow-inner bg-gradient-to-br from-green-700 to-green-900 border border-green-800">
                {{ session('status') }}
            </div>
        @endif

        <x-validation-errors class="mb-4 p-4 rounded-md" />

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div class="block">
                <x-label for="email" value="{{ __('Email') }}" />
                <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            </div>

            <div class="flex items-center justify-end mt-4">
                <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('login') }}">
                    {{ __('Back to login') }}
                </a>
                <x-button class="ml-4">
                    {{ __('Send Link') }}
                </x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
