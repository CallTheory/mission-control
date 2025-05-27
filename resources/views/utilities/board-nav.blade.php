@php

    use Illuminate\Support\Facades\Auth;

    $user = Auth::user();
    $agent = null;

    if(!is_null($user))
    {
        $agent = $user->getIntelligentAgent();
        $dispatcher = str_contains($agent->Name ?? '', "-DISP") || str_contains($agent->Name ?? '', "-SUP") || $user->hasTeamRole($user->currentTeam, 'dispatcher');
        $supervisor = str_contains($agent->Name ?? '', "-SUP") || $user->hasTeamRole($user->currentTeam, 'admin') || $user->hasTeamRole($user->currentTeam, 'manager') || $user->hasTeamRole($user->currentTeam, 'supervisor');
    }
    else
    {
        $dispatcher = false;
        $supervisor = false;
    }

    $boardCheckActive = basename(request()->header('referer')) == 'board-check';
    $boardReviewActive = basename(request()->header('referer')) == 'board-review';
    $boardReportActive = basename(request()->header('referer')) == 'board-report';
    $boardActivityActive = basename(request()->header('referer')) == 'board-activity';

@endphp
<div class="border-b border-gray-300">
    <div class="sm:flex sm:items-baseline">
        <h3 class="text-base font-semibold leading-6 text-gray-900">Board Check</h3>
        <div class="mt-4 sm:ml-10 sm:mt-0">
            <nav class="-mb-px flex space-x-8">
                @php
                    $current = "border-indigo-500 text-indigo-600";
                    $default = "order-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700";
                    $aria_current = 'aria-current="page"';
                @endphp

                @if( $dispatcher || $supervisor )
                    @if($boardCheckActive)
                        <a href="/utilities/board-check"
                           {{ $aria_current }} class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium {{ $current }}">Board
                            Check</a>
                    @else
                        <a href="/utilities/board-check"
                           class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium">Board Check</a>
                    @endif
                @endif


                @if( $supervisor )
                    @if($boardReviewActive)
                        <a href="/utilities/board-review"
                           {{ $aria_current }} class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium {{ $current }}">Board
                            Review</a>
                    @else
                        <a href="/utilities/board-review"
                           class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium">Board Review</a>
                    @endif

                    @if($boardReportActive)
                        <a href="/utilities/board-report"
                           {{ $aria_current }} class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium {{ $current }}">Board
                            Report</a>
                    @else
                        <a href="/utilities/board-report"
                           class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium">Board Report</a>
                    @endif

                    @if($boardActivityActive)
                        <a href="/utilities/board-activity"
                           {{ $aria_current }} class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium {{ $current }}">Board
                            Activity</a>
                    @else
                        <a href="/utilities/board-activity"
                           class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium">Board Activity</a>
                    @endif
                @endif

            </nav>
        </div>
    </div>
</div>
