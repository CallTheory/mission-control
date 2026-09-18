
<nav class="space-y-1" aria-label="Sidebar">
    @php
        $active = 'bg-surface-2 text-surface-fg flex items-center px-3 py-2 text-sm font-medium rounded-md';
        $inactive = 'text-surface-fg-soft 0 hover:bg-surface-2 hover:text-surface-fg flex items-center px-3 py-2 text-sm font-medium rounded-md';
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

    @can('system.azure_tokens')
        <a href="{{ route('system.azure-tokens') }}" class="@if(request()->routeIs('system.azure-tokens')) {{ $active }} @else {{ $inactive }} @endif">
        <span class="truncate">
          Azure Tokens
        </span>
        </a>
    @endcan

    @can('system.observability')
        <a href="{{ route('system.observability') }}" class="@if(request()->routeIs('system.observability')) {{ $active }} @else {{ $inactive }} @endif">
        <span class="truncate">
          Observability
        </span>
        </a>
    @endcan

</nav>
