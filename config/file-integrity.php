<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Base Git Reference
    |--------------------------------------------------------------------------
    |
    | The Git reference to compare against. Can be:
    | - origin/main, origin/master (remote branch)
    | - main, master (local branch)
    | - A concrete commit hash (e.g. abc123def)
    |
    */
    'base_ref' => env('FILE_INTEGRITY_BASE_REF', 'HEAD'),

    /*
    |--------------------------------------------------------------------------
    | Paths to Include
    |--------------------------------------------------------------------------
    |
    | Only scan changes in these directories. Empty array = scan all tracked files.
    | Examples: ['app', 'routes', 'resources/views', 'config']
    |
    */
    'paths' => [],

    /*
    |--------------------------------------------------------------------------
    | Paths to Exclude
    |--------------------------------------------------------------------------
    |
    | Extra paths to skip (in addition to .gitignore). Supports glob patterns.
    | Examples: ['vendor', 'node_modules', 'storage/logs/*']
    |
    */
    'exclude_paths' => [],

    /*
    |--------------------------------------------------------------------------
    | Report Configuration
    |--------------------------------------------------------------------------
    */
    'report' => [
        /*
        | Output mode: 'console', 'json', or 'both'
        */
        'output' => env('FILE_INTEGRITY_OUTPUT', 'console'),

        /*
        | Exit with non-zero code when changes are detected (useful for CI)
        */
        'fail_on_changes' => env('FILE_INTEGRITY_FAIL_ON_CHANGES', false),

        /*
        | Log scan results to Laravel log
        */
        'log' => env('FILE_INTEGRITY_LOG', false),

        /*
        | Send report via mail when changes detected (requires mail config)
        */
        'mail' => env('FILE_INTEGRITY_MAIL', false),

        /*
        | Mail recipient when mail report is enabled
        */
        'mail_to' => env('FILE_INTEGRITY_MAIL_TO', []),
    ],
];
