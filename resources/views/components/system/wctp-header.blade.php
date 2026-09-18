{{--
    The WCTP section's page heading.

    The System Settings dropdown requires `system.access`, which a technical
    operator holding only the WCTP capabilities does not have -- embedding it
    unconditionally made every page in this section 403 for exactly the people it is
    meant for. Admins keep the familiar System chrome; everyone else gets a link back
    to the section index.
--}}
<h2 class="inline font-semibold text-xl leading-tight">
    @can(\App\Enums\Capability::SystemAccess->value)
        <a href="/system">System Settings</a> <livewire:system.dropdown-navigation />
    @else
        <a href="{{ route('system.wctp') }}">WCTP Gateway</a>
    @endcan
</h2>
