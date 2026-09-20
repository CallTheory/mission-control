{{-- Option list for the dashboard timeframe selects (page header + profile).
     Sourced from App\Enums\DashboardTimeframe so the two never drift apart.
     Options/optgroups are painted explicitly: browsers otherwise fall back to
     the system popup colours, which reads as white-on-white in dark mode. --}}
@foreach(\App\Enums\DashboardTimeframe::grouped() as $group => $options)
    <optgroup label="{{ __($group) }}" class="bg-surface text-muted">
        @foreach($options as $value => $label)
            <option value="{{ $value }}" class="bg-surface text-surface-fg">{{ __($label) }}</option>
        @endforeach
    </optgroup>
@endforeach
