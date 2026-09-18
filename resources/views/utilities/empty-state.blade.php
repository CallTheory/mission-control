{{--
    Shown when the Utilities grid has no tiles. $emptyReason comes from
    App\Support\UtilityAvailability and names which of the four gate conditions
    is blocking; $fixRoute is set only when this user is the one who can fix it.
--}}
@php
    use App\Support\UtilityAvailability;

    $copy = match ($emptyReason) {
        UtilityAvailability::REASON_PERSONAL_TEAM => [
            'title' => __('Utilities are not available on a personal team.'),
            'body' => __('Switch to one of your organisation\'s teams using the team menu at the top of the page, and its utilities will appear here.'),
            'cta' => null,
        ],
        UtilityAvailability::REASON_NONE_SYSTEM => [
            'title' => __('No utilities are enabled at the system level.'),
            'body' => __('A utility has to be turned on for the whole system first, then enabled for this team.'),
            'cta' => __('Enable utilities in System settings'),
        ],
        UtilityAvailability::REASON_NONE_TEAM => [
            'title' => __('This team has not enabled any utilities yet.'),
            'body' => __('Utilities are switched on for the system already; this team just has none of them turned on.'),
            'cta' => __('Choose utilities in Team Settings'),
        ],
        UtilityAvailability::REASON_MISSING_DEPENDENCY => [
            'title' => __('This team\'s utilities are not finished being set up.'),
            'body' => __('Every utility enabled for this team is still waiting on something it needs -- Cloud Faxing, for example, needs a fax provider configured before it will run.'),
            'cta' => __('Finish setup in System settings'),
        ],
        UtilityAvailability::REASON_NO_CAPABILITY => [
            'title' => __('Your role does not include access to any of this team\'s utilities.'),
            'body' => __('The team has utilities enabled, but none of them are granted to the role you hold.'),
            'cta' => __('Review roles and permissions'),
        ],
        default => null,
    };
@endphp

@if($copy !== null)
    <div class="rounded-md border border-border bg-surface-2 p-6 text-center">
        <svg class="mx-auto h-10 w-10 text-muted" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
        </svg>

        <p class="mt-3 text-sm font-semibold text-surface-fg">
            {{ $copy['title'] }}
        </p>

        <p class="mt-1 text-sm text-muted">
            {{ $copy['body'] }}
        </p>

        @if($copy['cta'] !== null)
            @if($fixRoute !== null)
                <a href="{{ $fixRoute }}" class="mt-4 inline-block text-sm font-medium text-primary hover:underline">
                    {{ $copy['cta'] }}
                </a>
            @else
                <p class="mt-4 text-sm text-muted">
                    {{ __('Ask an administrator to sort this out for you.') }}
                </p>
            @endif
        @endif
    </div>
@endif
