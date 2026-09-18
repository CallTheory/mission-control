<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue API supports an assortment of back-ends via a single
    | API, giving you convenient access to each back-end using the same
    | syntax for every one. Here you may define a default connection.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each server that
    | is used by your application. A default configuration has been added
    | for each back-end shipped with Laravel. You are free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis", "null"
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => 'localhost',
            'queue' => 'default',
            'retry_after' => 90,
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        /*
         * `retry_after` is how long a worker's reservation of a job lasts before the queue
         * assumes the worker died and hands the job to somebody else. It MUST exceed the
         * worker's `timeout`, or a job that is merely slow gets picked up a second time
         * while the first attempt is still running — which for a fax means sending it
         * twice, and for a transcription means doing the work twice.
         *
         * Note that the `retry_after` keys in config/horizon.php's supervisor blocks are
         * inert: Horizon stores them on SupervisorOptions and never uses them. The values
         * that actually apply are the ones here, chosen per connection so each lane's
         * timeout can differ.
         */
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            // supervisor-1 times out at 90s.
            'retry_after' => 150,
            'block_for' => null,
            'after_commit' => false,
        ],

        // Consumed by horizon's supervisor-faxing, which times out at 120s. Jobs are
        // pushed onto the plain 'redis' connection; only the worker's reservation window
        // comes from here, and both connections address the same Redis queue keys.
        'redis-faxing' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'ringcentral',
            'retry_after' => 300,
            'block_for' => null,
            'after_commit' => false,
        ],

        // Consumed by horizon's supervisor-transcriptions, which times out at 1830s. The
        // shared 90s window meant any transcription running longer than a minute and a
        // half was re-reserved and transcribed again.
        'redis-transcriptions' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'transcriptions',
            'retry_after' => 1920,
            'block_for' => null,
            'after_commit' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control which database and table are used to store the jobs that
    | have failed. You may change them to any database / table you wish.
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],

];
