@section('head')
    <meta http-equiv="refresh" content="30">
@endsection
<x-guest-layout>


    <x-authentication-card>

        <x-slot name="logo">
            <div class="pt-8">
                <x-authentication-card-logo />
            </div>
        </x-slot>

        <h3 class="text-2xl mx-auto text-center font-semibold">
            <code class="text-indigo-500 font-bold font-mono">503</code> Service Unavailable
        </h3>
        <p class="my-2 text-xs text-center ">
            This usually means we're updating the web application.
            <br>
            <strong class="">Please try again in a couple of minutes.</strong>
        </p>

        <div class="w-full mx-auto pb-8">
            <a href="/dashboard" class="text-center hover:underline block">
                Return to dashboard
            </a>
        </div>


    </x-authentication-card>
</x-guest-layout>
