<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => null,

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => 'queue',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | This is the name of the Redis connection where Horizon will store the
    | meta information required for it to function. It includes the list
    | of supervisors, failed jobs, job metrics, and other information.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis. You
    | may modify the prefix when you are running multiple installations
    | of Horizon on the same server so that they don't have problems.
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached onto each Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option allows you to configure when the LongWaitDetected event
    | will be fired. Every connection / queue combination may have its
    | own, unique threshold (in seconds) before this event is fired.
    |
    */

    'waits' => [
        'redis:default' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) you desire Horizon to
    | persist the recent and failed jobs. Typically, recent jobs are kept
    | for one hour while all failed jobs are stored for an entire week.
    |
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Here you can configure how many snapshots should be kept to display in
    | the metrics graph. This will get used in combination with Horizon's
    | `horizon:snapshot` schedule to define how long to retain metrics.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon's "terminate" command will not
    | wait on all of the workers to terminate unless the --wait option
    | is provided. Fast termination can shorten deployment delay by
    | allowing a new instance of Horizon to start while the last
    | instance will continue to terminate each of its workers.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | This value describes the maximum amount of memory the Horizon master
    | supervisor may consume before it is terminated and restarted. For
    | configuring these limits on your workers, see the next section.
    |
    */

    'memory_limit' => 1024,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration\
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application
    | in all environments. These supervisors and settings handle all your
    | queued jobs and will be provisioned by Horizon during deployment.
    |
    */

    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'inbound-email', 'outbound-email', 'better-emails', 'ffmpeg', 'sox', 'people-praise', 'copia', 'genesis', 'voicemail-digest', 'message-export'],
            'balance' => 'auto',
            'maxProcesses' => 1,
            'memory' => 128,
            'tries' => 5,
            'nice' => 0,
            'timeout' => 90,
        ],

        // Outbound faxing gets its own lane.
        //
        // These queues used to share supervisor-1 with audio transcoding, email and
        // export work, so a burst of media jobs could leave faxes waiting behind them
        // while their retry windows ran down. The dedicated supervisor also lets the
        // fax timeout be sized for a document upload without affecting everything else.
        'supervisor-faxing' => [
            // A dedicated queue connection purely so the reservation window can exceed
            // this supervisor's timeout — see the comments in config/queue.php.
            'connection' => 'redis-faxing',
            'queue' => ['ringcentral', 'mfax'],
            'balance' => 'auto',
            'maxProcesses' => 2,
            'memory' => 128,
            // The jobs define their own retry behaviour: SendFaxRingCentral bounds itself
            // with retryUntil so a throttled fax keeps waiting, and SendFaxJob sets
            // $tries = 3. This is only the fallback for anything that doesn't.
            'tries' => 3,
            'nice' => 0,
            'timeout' => 120,
        ],
        'supervisor-transcriptions' => [
            'connection' => 'redis-transcriptions',
            'queue' => ['transcriptions'],
            'balance' => 'simple',
            'maxProcesses' => 1,
            'memory' => 256,
            'tries' => 10,
            'nice' => 0,
            'timeout' => 1830,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses' => 10,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            // Three is ample: the RingCentral limiter allows 10 submissions a minute, so
            // the constraint is the provider's rate limit, not worker count.
            'supervisor-faxing' => [
                'maxProcesses' => 3,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
            'supervisor-transcriptions' => [
                'maxProcesses' => 1,
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'maxProcesses' => 3,
            ],
            'supervisor-faxing' => [
                'maxProcesses' => 2,
            ],
            'supervisor-transcriptions' => [
                'maxProcesses' => 1,
            ],
        ],
    ],
];
