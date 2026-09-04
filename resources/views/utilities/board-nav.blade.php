@php

    $boardCheckActive = basename(request()->header('referer')) == 'board-check';
    $boardReviewActive = basename(request()->header('referer')) == 'board-review';
    $boardReportActive = basename(request()->header('referer')) == 'board-report';
    $boardActivityActive = basename(request()->header('referer')) == 'board-activity';

@endphp
<div class="border-b border-border">
    <div class="sm:flex sm:items-baseline">
        <h3 class="text-base font-semibold leading-6 text-surface-fg">Board Check</h3>
        <div class="mt-4 sm:ml-10 sm:mt-0">
            <nav class="-mb-px flex space-x-8">
                @php
                    $current = "border-primary text-primary";
                    $default = "border-transparent text-muted hover:border-border hover:text-surface-fg-soft";
                    $aria_current = 'aria-current="page"';
                @endphp

                @can('utility.board_check')
                    @if($boardCheckActive)
                        <a href="/utilities/board-check"
                           {{ $aria_current }} class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium {{ $current }}">Board
                            Check</a>
                    @else
                        <a href="/utilities/board-check"
                           class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium {{ $default }}">Board Check</a>
                    @endif
                @endcan


                @can('board.review')
                    @if($boardReviewActive)
                        <a href="/utilities/board-review"
                           {{ $aria_current }} class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium {{ $current }}">Board
                            Review</a>
                    @else
                        <a href="/utilities/board-review"
                           class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium {{$default}}">Board Review</a>
                    @endif

                    @if($boardReportActive)
                        <a href="/utilities/board-report"
                           {{ $aria_current }} class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium {{ $current }}">Board
                            Report</a>
                    @else
                        <a href="/utilities/board-report"
                           class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium {{ $default }}">Board Report</a>
                    @endif

                    @if($boardActivityActive)
                        <a href="/utilities/board-activity"
                           {{ $aria_current }} class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium {{ $current }}">Board
                            Activity</a>
                    @else
                        <a href="/utilities/board-activity"
                           class="whitespace-nowrap border-b-2 px-1 pb-4 text-sm font-medium {{ $default }}">Board Activity</a>
                    @endif
                @endcan

            </nav>
        </div>
    </div>
</div>
