<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Statistic tile refresh
    |--------------------------------------------------------------------------
    |
    | How often each statistic tile on the dashboard re-polls, in seconds. Every
    | tile is a query against the Intelligent Series database, and there are nine
    | of them per open dashboard, so this is the main lever on how much load the
    | dashboard puts on that server.
    |
    */

    'poll_seconds' => (int) env('DASHBOARD_POLL_SECONDS', 30),

    /*
    |--------------------------------------------------------------------------
    | Statistic tile cache
    |--------------------------------------------------------------------------
    |
    | How long a tile's result is reused, in seconds. A result is shared by every
    | viewer asking the same question -- same tile, same timeframe, same team
    | account and billing filters -- so a second open dashboard costs nothing.
    | Set to 0 to query on every poll.
    |
    */

    'cache_seconds' => (int) env('DASHBOARD_CACHE_SECONDS', 30),

];
