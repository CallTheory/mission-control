<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Allow unrestricted teams to access call media
    |--------------------------------------------------------------------------
    |
    | A team with neither `allowed_accounts` nor `allowed_billing` configured is
    | "unrestricted" and would otherwise be able to pull ANY call's recording or
    | screen capture by enumerating call ids. This defaults to false so that an
    | unconfigured team is denied media access (fail closed). Set to true only if
    | you deliberately operate trusted, unrestricted teams.
    |
    */

    'allow_unrestricted_teams' => env('RECORDINGS_ALLOW_UNRESTRICTED_TEAMS', false),

];
