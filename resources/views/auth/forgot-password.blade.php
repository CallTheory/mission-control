<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <div class="mb-4 text-sm text-surface-fg-soft">
            {{ __('Forgot your password? Send a password reset link to your registered email address.') }}
        </div>

        @if (session('status'))
            <div class="mb-4 font-medium text-sm rounded-md p-4 text-success-soft-fg shadow-inner bg-success-soft border border-success">
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
                <a class="underline text-sm text-surface-fg-soft hover:text-surface-fg" href="{{ route('login') }}">
                    {{ __('Back to login') }}
                </a>
                <x-button class="ml-4">
                    {{ __('Send Link') }}
                </x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
