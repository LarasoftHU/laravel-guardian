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
    | Content Scan Max Size (bytes)
    |--------------------------------------------------------------------------
    |
    | Only read and scan file content for files smaller than this size.
    | Detects PHP disguised as other extensions (e.g. .webp containing <?php).
    | Default: 200 KB.
    |
    */
    'content_scan_max_bytes' => env('FILE_INTEGRITY_CONTENT_SCAN_MAX_BYTES', 200 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Suspicious PHP Functions
    |--------------------------------------------------------------------------
    |
    | PHP functions that are considered dangerous when found in uploaded
    | or user-accessible files (e.g. in storage). Triggers alert when detected.
    | Comprehensive list covering code execution, obfuscation, I/O, and RCE.
    |
    */
    'suspicious_php_functions' => [
        // Code execution (critical)
        'eval',
        'assert',
        'create_function',
        'preg_replace', // with /e modifier
        'call_user_func',
        'call_user_func_array',
        'call_user_method',
        'call_user_method_array',
        'forward_static_call',
        'forward_static_call_array',
        'usort',
        'uasort',
        'uksort',
        'array_filter',
        'array_map',
        'array_reduce',
        'array_walk',
        'array_walk_recursive',
        'register_shutdown_function',
        'register_tick_function',
        // Command / process execution
        'exec',
        'shell_exec',
        'system',
        'passthru',
        'popen',
        'proc_open',
        'proc_close',
        'proc_get_status',
        'proc_terminate',
        'pcntl_exec',
        'pcntl_signal',
        'pcntl_fork',
        'escapeshellarg',
        'escapeshellcmd',
        // Obfuscation / decoding (often used in malware)
        'base64_decode',
        'gzinflate',
        'gzuncompress',
        'gzdecode',
        'str_rot13',
        'convert_uuencode',
        'convert_uudecode',
        'rawurldecode',
        'bzdecompress',
        'convert_iconv',
        'hex2bin',
        'bin2hex',
        // Serialization (object injection)
        'unserialize',
        'serialize',
        // File inclusion (RFI/LFI)
        'include',
        'include_once',
        'require',
        'require_once',
        // File write (webshell persistence)
        'file_put_contents',
        'fwrite',
        'fputs',
        'ftruncate',
        'fputcsv',
        // File read (data exfil, LFI)
        'file_get_contents',
        'readfile',
        'fread',
        'fgets',
        'fgetss',
        'fgetc',
        'file',
        'parse_ini_file',
        'highlight_file',
        'show_source',
        // Stream / socket (remote code fetch)
        'fsockopen',
        'pfsockopen',
        'stream_socket_client',
        'stream_socket_server',
        'stream_socket_accept',
        'curl_exec',
        'curl_multi_exec',
        // Process / system info
        'phpinfo',
        'php_uname',
        'getenv',
        'putenv',
        'get_current_user',
        'getmyuid',
        'getmypid',
        'dl',
        // Reflection (dynamic invocation)
        'ReflectionFunction',
        'ReflectionMethod',
        'ReflectionClass',
        // XML (XXE)
        'simplexml_load_string',
        'simplexml_load_file',
        'xml_parse',
        'xml_parse_into_struct',
        // LDAP injection
        'ldap_search',
        'ldap_list',
        'ldap_read',
        // Database (SQLi vector when concatenated)
        'pg_exec',
        'pg_query',
        'mysql_query',
        'mysqli_query',
        // Mail (abuse / spam)
        'mail',
        'mb_send_mail',
        // Other
        'chmod',
        'chown',
        'chgrp',
        'symlink',
        'link',
        'touch',
        'move_uploaded_file',
        'copy',
        'rename',
        'unlink',
        'rmdir',
        'mkdir',
        'opendir',
        'readdir',
        'scandir',
        'glob',
    ],

    /*
    |--------------------------------------------------------------------------
    | Malware Regex Patterns
    |--------------------------------------------------------------------------
    |
    | Regex patterns that detect obfuscated or known malware signatures.
    | Key = human-readable name, Value = PCRE regex (without delimiters).
    |
    */
    'malware_patterns' => [
        'eval_base64_decode' => 'eval\s*\(\s*base64_decode\s*\(',
        'eval_gzinflate' => 'eval\s*\(\s*gzinflate\s*\(',
        'eval_post' => 'eval\s*\(\s*\$[a-z0-9_]*\s*\(\s*\$_(?:GET|POST|REQUEST|COOKIE)',
        'eval_variable' => '{\s*eval\s*\(\s*\$',
        'chr_eval_obfuscation' => 'chr\s*\(\s*101\s*\)\s*\.\s*chr\s*\(\s*118\s*\)\s*\.\s*chr\s*\(\s*97\s*\)\s*\.\s*chr\s*\(\s*108\s*',
        'chr_concat_obfuscation' => '(chr\s*\(\s*\d+\s*\)\s*\.\s*){4,}',
        'create_function_empty' => 'create_function\s*\(\s*[\'"]{2}\s*,\s*[\'"]',
        'preg_replace_e_modifier' => 'preg_replace\s*\([^)]*\/[eE]\s*[\)\s,]',
        'base64_long_string' => '[\'"][A-Za-z0-9+\/]{200,}={0,3}[\'"]',
        'escaped_octal_commands' => '(\\\\[0-9]{3}){6,}',
        'globals_inject' => '\$GLOBALS\s*\[\s*\$[a-z0-9_]+\s*\]\s*\[',
        'cookie_payload' => 'urldecode\s*\(\s*\$_(?:GET|POST|COOKIE|REQUEST)\s*\[',
        'file_get_contents_remote' => 'file_get_contents\s*\(\s*(?:base64_decode|urldecode|gzinflate)\s*\(\s*\$_(?:GET|POST|REQUEST)',
        'fwrite_post_data' => 'fwrite\s*\(\s*\$[a-z0-9_]+\s*,\s*(?:stripslashes\s*\(\s*)?@?\$_(?:GET|POST|REQUEST)',
        'gzinflate_payload' => 'gzinflate\s*\(\s*(?:base64_decode|str_rot13)\s*\(',
        'variable_function_call' => '\$[a-z0-9_]+\s*\(\s*\$[a-z0-9_]+\s*\(',
        'obfuscated_include' => '@\s*(?:include|require)(?:_once)?\s+[\'"][^"\']*(?:\\\\x[0-9a-f]{2}){3,}',
        'php_uname_shell' => 'php_uname\s*\(\s*[\'"][asrvm]+[\'"]\s*\)',
        'gzip_magic_payload' => '\$[a-zA-Z0-9_]+\s*\(\s*[\'"]\\\\x78\\\\x9C',
        'xor_decode_post' => '(\^\s*\$[a-z0-9_]+\s*\[\s*\$[a-z0-9_]+\s*%\s*strlen\s*\(\s*\$[a-z0-9_]+\s*\]\s*){2,}',
        'dynamic_array_call' => '@\s*\$[a-z]\s*\[\s*\d+\s*\]\s*\(\s*\$[a-z]\s*\[\s*\d+\s*\]\s*\)',
        'eval_hex_string' => 'eval\s*\(\s*[a-zA-Z0-9_]+\s*\(\s*[\'"][A-Z0-9]{16,}',
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
    'dangerous_extensions' => ['exe', 'bat', 'cmd', 'com', 'sh', 'bash', 'php', 'php3', 'php4', 'php5', 'phtml', 'phar', 'pl', 'py', 'cgi', 'asp', 'aspx', 'jsp', 'jar', 'vbs', 'vbe', 'wsf', 'wsh', 'ps1', 'psm1', 'scr', 'msi', 'dll'],

    /*
    |--------------------------------------------------------------------------
    | Suspicious Path Patterns (WordPress / CMS-like)
    |--------------------------------------------------------------------------
    |
    | File or directory path substrings that indicate WordPress or similar CMS.
    | If found in storage or public path, triggers alert. Case-insensitive.
    |
    */
    'suspicious_path_patterns' => [
        'wp-admin',
        'wp-includes',
        'wp-content',
        'wp-config',
        'wp-login',
        'wp-cron',
        'wp-load',
        'wp-settings',
        'wp-blog-header',
        'wp-activate',
        'wp-signup',
        'wp-mail',
        'wp-links-opml',
        'wp-trackback',
        'wp-comments-post',
        'xmlrpc',
        'readme.html',
        'license.txt',
        'wp-json',
        'wp-cron.php',
        'wp-links-opml.php',
        'wp-mail.php',
        'wp-signup.php',
        'wp-activate.php',
        'wp-blog-header.php',
        'wp-settings.php',
        'wp-load.php',
        'wp-login.php',
        'wp-config.php',
        'xmlrpc.php',
        'administrator',   // Joomla
        'configuration.php', // Joomla
        'sites/default',   // Drupal
        'includes/bootstrap', // Drupal
    ],

    /*
    |--------------------------------------------------------------------------
    | Scan Public Path
    |--------------------------------------------------------------------------
    |
    | When true, also scans base_path('public') for suspicious path patterns.
    |
    */
    'scan_public_path' => env('FILE_INTEGRITY_SCAN_PUBLIC_PATH', true),

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
