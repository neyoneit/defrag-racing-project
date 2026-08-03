<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Report validation
    |--------------------------------------------------------------------------
    |
    | A flagged record only reaches the validator moderators once an admin has
    | cleared it AND enough different people have reported it independently.
    |
    | The threshold is deliberately NOT shown anywhere on the public site. If
    | it were, one person with two accounts would know exactly how far to go,
    | and the point of the number is that reporting takes more than one person.
    |
    */

    'validation' => [

        // Independent reporters required before a cleared flag is handed to a
        // validator. Admin clearance is still required on top of this - the
        // two conditions are AND, never OR.
        'min_reports' => (int) env('SERVERDEMO_VALIDATION_MIN_REPORTS', 2),

        // Only this account may clear reports for validation, no matter how
        // many admins exist. Set to null to let any admin do it.
        'clearing_admin_id' => (int) env('SERVERDEMO_VALIDATION_ADMIN_ID', 8),

    ],

];
