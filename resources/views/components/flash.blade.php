{{-- Renders session flash messages through the shared alert components.
     Reads: success|message, error, warning, info. Drop once into the layout. --}}
@php
    $success = session('success') ?? session('message');
@endphp

@if($success)
    <x-alert-success :description="$success" />
@endif

@if(session('error'))
    <x-alert-danger :description="session('error')" />
@endif

@if(session('warning'))
    <x-alert-warning :description="session('warning')" />
@endif

@if(session('info'))
    <x-alert-info :description="session('info')" />
@endif
