@props(['status' => '', 'map' => []])

@php
    $default = [
        'delivered' => 'green', 'success' => 'green', 'completed' => 'green', 'ok' => 'green', 'enabled' => 'green', 'active' => 'green',
        'sent' => 'blue', 'processing' => 'blue', 'queued' => 'yellow', 'pending' => 'yellow',
        'failed' => 'red', 'error' => 'red', 'cancelled' => 'red', 'disabled' => 'gray',
    ];
    $resolved = array_merge($default, $map);
    $color = $resolved[strtolower((string) $status)] ?? 'gray';
@endphp

<x-badge :color="$color" {{ $attributes }}>{{ $slot->isNotEmpty() ? $slot : ucfirst((string) $status) }}</x-badge>
