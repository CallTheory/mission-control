<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Mission Control Utility Dashboard</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <link rel="shortcut icon" type="image/png" href="/images/mission-control.png"/>

        @yield('head')

        @stack('styles')

        <script>let FFOUC;</script>
    </head>
    <body class="bg-gradient-to-b from-gray-200 to-gray-50">

        <div class="font-sans text-gray-900 antialiased">

            {{ $slot }}

        </div>
        @stack('scripts')
    </body>
</html>
