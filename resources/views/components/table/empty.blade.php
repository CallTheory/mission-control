@props(['colspan' => 1])

<tr>
    <td colspan="{{ $colspan }}" class="px-6 py-4 text-center text-sm text-muted">
        {{ $slot }}
    </td>
</tr>
