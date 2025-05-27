<x-guest-layout>

    <x-authentication-card>

        <x-slot name="logo">
            <div class="pt-8">
                <x-authentication-card-logo />
            </div>
        </x-slot>

        <h3 class="text-2xl mx-auto text-center font-semibold">
            <code class="text-indigo-500 font-bold font-mono">403</code> Forbidden
        </h3>

        <div class="w-full mx-auto pb-8">
            <a href="/dashboard" class="text-center hover:underline block">
                Return to dashboard
            </a>
        </div>


    </x-authentication-card>
</x-guest-layout>
