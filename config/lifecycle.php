<?php

/**
 * Tenant lifecycle policy — suspension reasons, trial reminders, deletion grace.
 */
return [

    // Reason catalogue for suspension (free text still allowed alongside).
    'suspension_reasons' => [
        'non_payment' => 'Non-payment / overdue invoice',
        'trial_ended' => 'Trial period ended without conversion',
        'customer_request' => 'Customer requested pause',
        'abuse' => 'Terms-of-service / abuse',
        'security' => 'Security concern',
        'other' => 'Other',
    ],

    // Days before trial_ends_at to email the tenant admin + raise a super-admin alert.
    'trial_reminder_days' => [7, 3, 1],

    // Subscriptions whose end_date passed this many days ago with no renewal are
    // auto-suspended (0 = suspend the day it lapses).
    'auto_suspend_grace_days' => 3,

    // Days between a deletion request and the hard-delete + anonymise job running.
    'deletion_grace_days' => 30,

    // Value written into anonymised PII columns.
    'anonymise_token' => 'deleted',
];
