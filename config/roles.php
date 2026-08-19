<?php

declare(strict_types=1);

use App\Enums\Capability;
use App\Enums\Utility;

/*
|--------------------------------------------------------------------------
| Default Role Template
|--------------------------------------------------------------------------
|
| Every non-personal team is seeded with these six system roles and their
| capability sets. The sets are reverse-engineered from the authorization
| gates that existed before the capability system, so behavior is unchanged
| on day one; team admins then edit their own roles from here.
|
| Capabilities reference App\Enums\Capability values — the single source of
| truth for what the code actually gates.
|
*/

// Utilities that historically had NO role check (only personal_team + feature
// flag), i.e. reachable by any non-personal team member. Seeded to every role
// to preserve that behavior.
$openUtilities = array_map(
    fn (Utility $u) => $u->capability()->value,
    array_filter(
        Utility::cases(),
        fn (Utility $u) => ! in_array($u, [
            Utility::BoardCheck,      // dispatcher/supervisor branch
            Utility::CardProcessing,  // admin|manager
            Utility::CloudFaxing,     // supervisor (+ -SUP)
            Utility::ConfigEditor,    // admin only
            Utility::InboundEmail,    // admin|manager|supervisor|dispatcher
        ], true)
    )
);

$boardSubPages = [
    Capability::BoardReview->value,
    Capability::BoardReport->value,
    Capability::BoardActivity->value,
];

return [

    'defaults' => [

        'admin' => [
            'label' => 'Administrator',
            'description' => 'Administrators can perform any action and access system-level settings.',
            'sort_order' => 10,
            // Full access — every code-defined capability.
            'capabilities' => Capability::values(),
        ],

        'manager' => [
            'label' => 'Manager',
            'description' => 'Managers have transparency into their team, including supervisors and agents.',
            'sort_order' => 20,
            'capabilities' => array_merge($openUtilities, $boardSubPages, [
                Capability::TeamManage->value,
                Capability::TeamAddMember->value,
                Capability::AnalyticsView->value,
                Capability::UtilitiesAccess->value,
                Capability::AccountsView->value,
                Capability::ApiTokensManage->value,
                Capability::UtilityCardProcessing->value,
                Capability::UtilityBoardCheck->value,
                Capability::UtilityCloudFaxing->value,
                Capability::UtilityInboundEmail->value,
            ]),
        ],

        'supervisor' => [
            'label' => 'Supervisor',
            'description' => 'Supervisors are allowed access to accounts and agents assigned to their team.',
            'sort_order' => 30,
            'capabilities' => array_merge($openUtilities, $boardSubPages, [
                Capability::TeamAddMember->value,
                Capability::UtilitiesAccess->value,
                Capability::AccountsView->value,
                Capability::UtilityBoardCheck->value,
                Capability::UtilityCloudFaxing->value,
                Capability::UtilityInboundEmail->value,
            ]),
        ],

        'technical' => [
            'label' => 'Technical',
            'description' => 'Technical users can access utilities and manage API tokens, but not individual user data.',
            'sort_order' => 40,
            'capabilities' => array_merge($openUtilities, [
                Capability::ApiTokensManage->value,
            ]),
        ],

        'dispatcher' => [
            'label' => 'Dispatcher',
            'description' => 'Dispatchers can see all call data and make use of utilities.',
            'sort_order' => 50,
            'capabilities' => array_merge($openUtilities, [
                Capability::UtilitiesAccess->value,
                Capability::AccountsView->value,
                Capability::UtilityBoardCheck->value,
                Capability::UtilityInboundEmail->value,
            ]),
        ],

        'agent' => [
            'label' => 'Agent',
            'description' => 'Agents can see their own statistics.',
            'sort_order' => 60,
            // Historically agents could reach the un-gated utilities by direct
            // navigation; nothing else.
            'capabilities' => $openUtilities,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Default Suffix Rules
    |--------------------------------------------------------------------------
    |
    | Client-specific grants keyed on the linked Intelligent agent's Name.
    | Seeded globally (team_id NULL) to reproduce the previous hard-coded
    | -SUP / -DISP behavior. Admins add/edit these per team in the UI.
    |
    */
    'suffix_rules' => [
        [
            'match_type' => 'contains',
            'pattern' => '-SUP',
            'capabilities' => [
                Capability::UtilityBoardCheck->value,
                Capability::BoardReview->value,
                Capability::BoardReport->value,
                Capability::BoardActivity->value,
                Capability::UtilityCloudFaxing->value,
            ],
        ],
        [
            'match_type' => 'contains',
            'pattern' => '-DISP',
            'capabilities' => [
                Capability::UtilityBoardCheck->value,
            ],
        ],
    ],

];
