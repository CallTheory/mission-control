<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Mission Control &middot; @yield('title',ucwords(request()->path())) </title>

        @vite(['resources/css/app.css', 'resources/scss/diff-table.scss', 'resources/js/app.js'])

        <link rel="shortcut icon" type="image/png" href="/images/mission-control.png"/>

    </head>
    <body class="font-sans antialiased bg-gradient-to-br from-gray-200 to-gray-100">

        <x-banner />

        <div class="min-h-screen bg-gradient-to-br from-gray-200 to-gray-100">

            <div class="sticky top-0 bg-gradient-to-br from-gray-200 to-gray-100 z-10">

                <div class="block">
                    @livewire('navigation-menu')
                </div>

                <!-- Page Heading -->
                @if (isset($header))
                    <header class="bg-white shadow z-20">

                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">

                            <div class="flex float-right ">
                                <livewire:navigation.search />

                            </div>

                            {{ $header }}

                        </div>
                    </header>
                @endif

            </div>

            <!-- Page Content -->
            <main class="z-50">
                {{ $slot }}
            </main>
        </div>

        @stack('scripts')

        @stack('modals')

        @livewire('wire-elements-modal')

    </body>
</html>
