<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ auth()->user()?->dark_mode === 'dark' ? 'dark' : '' }}">
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
    <body class="bg-gradient-to-b from-canvas to-canvas-2">

        <div class="font-sans text-surface-fg antialiased">

            {{ $slot }}

        </div>
        @stack('scripts')
    </body>
</html>
