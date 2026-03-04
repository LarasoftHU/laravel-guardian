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
    | Include Untracked Files
    |--------------------------------------------------------------------------
    |
    | When enabled, new files that exist on disk but are not yet committed
    | (and not in .gitignore) will be reported as "untracked".
    |
    */
    'include_untracked' => env('FILE_INTEGRITY_INCLUDE_UNTRACKED', true),

    /*
    |--------------------------------------------------------------------------
    | Disk Scan (Storage Disks)
    |--------------------------------------------------------------------------
    |
    | Optionally scan one or more Laravel storage disks for security issues.
    | Empty array = no disk scan. Example: ['local', 'public', 'uploads']
    |
    */
    'disk_scan' => array_filter(array_map('trim', explode(',', env('FILE_INTEGRITY_DISK_SCAN', '')))),

    /*
    |--------------------------------------------------------------------------
    | Suspicious PHP Functions
    |--------------------------------------------------------------------------
    |
    | PHP functions that are considered dangerous when found in uploaded
    | or user-accessible files (e.g. in storage). Triggers alert when detected.
    |
    */
    'suspicious_php_functions' => [
        'eval',
        'exec',
        'shell_exec',
        'system',
        'passthru',
        'popen',
        'proc_open',
        'assert',
        'create_function',
        'unserialize',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dangerous File Extensions
    |--------------------------------------------------------------------------
    |
    | File extensions that should not typically exist in storage (uploads).
    | If found, triggers an email alert. Example: exe, php, sh, bash
    |
    */
    'dangerous_extensions' => ['exe', 'bat', 'cmd', 'com', 'sh', 'bash', 'php', 'php3', 'php4', 'php5', 'phtml', 'pl', 'py', 'cgi'],

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
