@php
use App\Models\Team;
@endphp
<nav x-data="{ open: false }" class="border-b border-gray-300">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-mark class="block h-9 w-auto transition transform duration-700 ease-in-out hover:scale-125" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">

                    <x-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard') || request()->routeIs('dashboard.*') ">
                        {{ __('Dashboard') }}
                    </x-nav-link >

                    @if(
                        (request()->user()->hasTeamRole(request()->user()->currentTeam, 'admin') ||
                        request()->user()->hasTeamRole(request()->user()->currentTeam, 'manager') ||
                        request()->user()->hasTeamRole(request()->user()->currentTeam, 'supervisor') ||
                        request()->user()->hasTeamRole(request()->user()->currentTeam, 'dispatcher'))
                        && request()->user()->currentTeam->personal_team === false

                    )
                        <x-nav-link href="{{ route('accounts') }}" :active="request()->routeIs('accounts')  || request()->routeIs('accounts.*') " >
                            {{ __('Accounts') }}
                        </x-nav-link>
                        <x-nav-link href="{{ route('utilities') }}" :active="request()->routeIs('utilities')  || request()->routeIs('utilities.*') " >
                            {{ __('Utilities') }}
                        </x-nav-link>
                    @endif

                    @if(request()->user()->hasTeamRole(request()->user()->currentTeam, 'admin') && request()->user()->currentTeam->personal_team === false)
                        <x-nav-link href="{{ route('system') }}" :active="request()->routeIs('system') || request()->routeIs('system.*') " >
                            {{ __('System') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <div class="sm:flex sm:items-center sm:ml-6">
                <!-- Teams Dropdown -->
                @if(isset(Auth::user()->currentTeam->id))
                    @if (Laravel\Jetstream\Jetstream::hasTeamFeatures())
                        <div class="ml-3 relative z-50">
                            <x-dropdown align="right" width="60">
                                <x-slot name="trigger">
                                    <span class="inline-flex rounded-md">
                                        <button type="button" class="cursor-pointer transform transition duration-700 ease-in-out inline-flex items-center px-3 py-2 border border-gray-300  text-sm leading-4 font-medium rounded-md text-gray-500   bg-white hover:bg-gray-50  hover:text-gray-700  focus:outline-hidden focus:bg-gray-50 active:bg-gray-50 shadow transition">
                                            {{ Auth::user()->currentTeam->name ?? 'No Team' }}

                                            <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </span>
                                </x-slot>

                                <x-slot name="content">
                                    <div class="w-60">
                                        <!-- Team Settings -->
                                        <x-dropdown-link href="{{ route('teams.show', Auth::user()->currentTeam->id) }}">
                                            {{ __('Team Settings') }}
                                        </x-dropdown-link>


                                        @if(Team::count() === 1)
                                            <x-dropdown-link href="{{ route('teams.create') }}">
                                                {{ __('Create New Team') }}
                                            </x-dropdown-link>
                                        @elseif( Auth::user()->hasTeamRole(Auth::user()->currentTeam, 'admin') && Auth::user()->currentTeam->personal_team === false )
                                            <x-dropdown-link  href="{{ route('teams.create') }}">

                                                {{ __('Create New Team') }}

                                            </x-dropdown-link>
                                        @endif


                                        @livewire('menu-break')

                                        <!-- Team Switcher -->
                                        <div class="block px-4 py-2 text-xs text-gray-400 ">
                                            {{ __('Switch Teams') }}
                                        </div>

                                        @foreach (Auth::user()->allTeams() as $team)
                                            <x-switchable-team :team="$team" />
                                        @endforeach

                                    </div>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    @else
                        <div class="ml-3 relative text-xs text-gray-400">
                            Teams Disabled
                        </div>
                    @endif
                @else
                    <div class="ml-3 relative text-xs text-gray-400">
                        No Team
                    </div>
                @endif

                <!-- Settings Dropdown -->
                <div class="ml-3 relative">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                                <button class="cursor-pointer group flex text-sm border-2 border-transparent rounded-full focus:outline-hidden focus:border-gray-300 transition">
                                    <img class="transform transition duration-700 ease-in-out  h-8 w-8 rounded-full object-cover border-2 border-transparent " src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                                </button>
                            @else
                                <span class="inline-flex rounded-md">
                                    <button type="button" class="cursor-pointer inline-flex items-center px-3 py-2 border border-transparent  text-sm leading-4 font-medium rounded-md text-gray-500   bg-white   hover:text-gray-700 focus:outline-hidden transition ">
                                        {{ Auth::user()->name }}

                                        <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </span>
                            @endif
                        </x-slot>

                        <x-slot name="content">
                            <!-- Account Management -->
                            <div class="block px-4 py-2 text-xs text-gray-400 ">
                                {{ __('Manage Account') }}
                            </div>

                            <x-dropdown-link href="{{ route('profile.show') }}">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            @if(
                                 (request()->user()->hasTeamRole(request()->user()->currentTeam, 'admin') ||
                                 request()->user()->hasTeamRole(request()->user()->currentTeam, 'manager') ||
                                 request()->user()->hasTeamRole(request()->user()->currentTeam, 'technical'))
                                 && request()->user()->currentTeam->personal_team === false
                            )
                                @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                                    <x-dropdown-link href="{{ route('api-tokens.index') }}">
                                        {{ __('API Tokens') }}
                                    </x-dropdown-link>
                                @endif
                            @endif


                            @livewire('menu-break')

                            <!-- Authentication -->
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <x-dropdown-link href="{{ route('logout') }}"
                                         onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                </div>
            </div>

            <!-- Hamburger -->
            <div class="-mr-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-300">
            <div class="flex items-center px-4">
                @if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
                    <div class="shrink-0 mr-3">
                        <img class="h-10 w-10 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
                    </div>
                @endif

                <div>
                    <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                </div>
            </div>

            <div class="mt-3 space-y-1">
                <!-- Account Management -->
                <x-responsive-nav-link href="{{ route('profile.show') }}" :active="request()->routeIs('profile.show')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                @if(
                    (request()->user()->hasTeamRole(request()->user()->currentTeam, 'admin') ||
                    request()->user()->hasTeamRole(request()->user()->currentTeam, 'manager') ||
                    request()->user()->hasTeamRole(request()->user()->currentTeam, 'technical'))
                    && request()->user()->currentTeam->personal_team === false
                )
                    @if (Laravel\Jetstream\Jetstream::hasApiFeatures())
                        <x-responsive-nav-link href="{{ route('api-tokens.index') }}" :active="request()->routeIs('api-tokens.index')">
                            {{ __('API Tokens') }}
                        </x-responsive-nav-link>
                    @endif
                @endif

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link href="{{ route('logout') }}"
                                   onclick="event.preventDefault();
                                    this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>

                @if(isset(Auth::user()->currentTeam->id))
                    <!-- Team Management -->
                    @if (Laravel\Jetstream\Jetstream::hasTeamFeatures())
                        <div class="border-t border-gray-300"></div>

                        <div class="block px-4 py-2 text-xs text-gray-400">
                            {{ __('Manage Team') }}
                        </div>

                        <!-- Team Settings -->
                        <x-responsive-nav-link href="{{ route('teams.show', Auth::user()->currentTeam->id) }}" :active="request()->routeIs('teams.show')">
                            {{ __('Team Settings') }}
                        </x-responsive-nav-link>


                        @can('create', Laravel\Jetstream\Jetstream::newTeamModel())
                            <x-responsive-nav-link href="{{ route('teams.create') }}" :active="request()->routeIs('teams.create')">
                                {{ __('Create New Team') }}
                            </x-responsive-nav-link>
                        @endcan

                        <div class="border-t border-gray-300"></div>

                        <!-- Team Switcher -->
                        <div class="block px-4 py-2 text-xs text-gray-400">
                            {{ __('Switch Teams') }}
                        </div>

                        @foreach (Auth::user()->allTeams() as $team)
                            <x-switchable-team :team="$team" component="responsive-nav-link" />
                        @endforeach
                    @endif
                @endif
            </div>
        </div>
    </div>
</nav>
