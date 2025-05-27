
<nav class="space-y-1" aria-label="Sidebar">
    @php
        $active = 'bg-gray-100     text-gray-900 flex items-center px-3 py-2 text-sm font-medium rounded-md';
        $inactive = 'text-gray-600 0   hover:bg-gray-50 hover:text-gray-900 flex items-center px-3 py-2 text-sm font-medium rounded-md';
    @endphp


    <a href="{{ route('system') }}" class="@if(request()->routeIs('system')) {{ $active }} @else {{ $inactive }} @endif" aria-current="page">
    <span class="truncate">
      General
    </span>
    </a>


    <a href="{{ route('system.data-sources') }}" class="@if(request()->routeIs('system.data-sources')) {{ $active }} @else {{ $inactive }} @endif">
    <span class="truncate">
        Data Sources
    </span>
    </a>

    <a href="{{ route('system.integrations') }}" class="@if(request()->routeIs('system.integrations')) {{ $active }} @else {{ $inactive }} @endif">
    <span class="truncate">
      Integrations
    </span>
    </a>


    <a href="{{ route('system.permissions') }}" class="@if(request()->routeIs('system.permissions')) {{ $active }} @else {{ $inactive }} @endif">
    <span class="truncate">
      Permissions
    </span>
    </a>

</nav>
