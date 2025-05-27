<x-guest-layout>
    <div class="min-h-screen flex flex-col items-center mt-12 pt-6 sm:pt-0">
        <div>
            <a href="/">
                <x-authentication-card-logo />
            </a>
        </div>

        <div class="w-full sm:max-w-2xl mt-6 p-6 bg-white border border-gray-300 shadow overflow-hidden sm:rounded-lg prose">
            {!! $terms !!}
        </div>
    </div>
</x-guest-layout>
