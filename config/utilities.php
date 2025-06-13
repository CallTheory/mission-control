<?php

return [
    'board-check' => [
        'starting_callid' => env('BOARD_CHECK_STARTING_CALLID', 500000),
    ],
    'inbound-email' => [
        'days_to_keep' => env('INBOUND_EMAIL_DAYS_TO_KEEP', 30),
    ],
];
