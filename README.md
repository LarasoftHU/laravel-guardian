# Laravel Guardian

Laravel package for file integrity checking. Compares local files against the Git state (as stored locally), while respecting `.gitignore`. Detects modified, added, deleted, renamed, and **untracked** files (new files not yet committed). Ideal for CI/CD pipelines and deployment verification.

## Requirements

- PHP 8.2+
- Laravel 10+, 11+, 12+, or 13+
- Git (local repository)

## Installation

```bash
composer require larasofthu/laravel-guardian
```

The package will auto-register via Laravel's package discovery.

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag="file-integrity-config"
```

To customize the HTML mail template (when using mail reports):

```bash
php artisan vendor:publish --tag="file-integrity-views"
```

This copies the mail template to `resources/views/vendor/file-integrity/file-integrity-report.blade.php`.

This creates `config/file-integrity.php` with the following options:

### `base_ref`

The Git reference to compare against. Can be:

- `origin/main`, `origin/master` (remote branch)
- `main`, `master` (local branch)
- A concrete commit hash (e.g. `abc123def`)

Default: `HEAD`

### `paths`

Only scan changes in these directories. Empty array = scan all tracked files.

```php
'paths' => ['app', 'routes', 'resources/views', 'config'],
```

### `exclude_paths`

Extra paths to skip (in addition to `.gitignore`). Supports glob patterns.

```php
'exclude_paths' => ['vendor', 'node_modules', 'storage/logs/*'],
```

### `include_untracked`

When `true`, new files that exist on disk but are not yet committed (and not in `.gitignore`) are reported as "untracked". Default: `true`.

### `disk_scan`

Optionally scan one or more Laravel storage disks for security issues. Scans for:
- **Suspicious PHP functions** (eval, exec, shell_exec, etc.) in PHP files
- **Dangerous file extensions** (exe, php, sh, bash, etc.) that should not typically exist in upload storage

Empty array = no disk scan. Example: `['local', 'public', 'uploads']`

### `suspicious_php_functions`

List of PHP functions considered dangerous when found in storage. Customize in config. Default includes: eval, exec, shell_exec, system, passthru, popen, proc_open, assert, create_function, unserialize.

### `dangerous_extensions`

File extensions that trigger an alert when found in storage. Default: exe, bat, cmd, com, sh, bash, php, php3, php4, php5, phtml, pl, py, cgi.

### `report`

- **output**: `'console'`, `'json'`, or `'both'`
- **fail_on_changes**: If `true`, the command exits with code 1 when changes are detected (useful for CI)
- **log**: Log scan results to Laravel log
- **mail**: Send report via mail when changes detected
- **mail_to**: Recipients for mail report

## Usage

### Basic scan

```bash
php artisan file-integrity:scan
```

### Custom base ref

```bash
php artisan file-integrity:scan --base-ref=origin/main
```

### JSON output (for CI)

```bash
php artisan file-integrity:scan --json
```

### Custom include/exclude paths

```bash
php artisan file-integrity:scan --paths=app --paths=routes --exclude-paths=app/Console
```

### Disable fail on changes

```bash
php artisan file-integrity:scan --no-fail
```

### Disk scan (storage security)

Scan storage disks for suspicious PHP and dangerous file extensions (configure `disk_scan` in config, or override via CLI):

```bash
php artisan file-integrity:scan --disks=local --disks=uploads
```

Skip disk scan even when configured:

```bash
php artisan file-integrity:scan --no-disk-scan
```

## Scheduled Execution

It's recommended to run the command regularly (e.g. hourly) to detect unexpected file changes in time. Using Laravel Scheduler:

**Laravel 11+** (`routes/console.php`):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('file-integrity:scan')->hourly();
```

**Laravel 10** (`app/Console/Kernel.php`):

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('file-integrity:scan')->hourly();
}
```

To receive email notifications when changes are detected, enable the `report.mail` and `report.mail_to` options in the config.

## JSON Output Structure

```json
{
  "base_ref": "origin/main",
  "changed_files": {
    "added": ["config/new.php"],
    "modified": ["app/Models/User.php"],
    "deleted": ["old/file.php"],
    "untracked": ["app/NewFile.php"],
    "renamed": [{"from": "old.php", "to": "new.php"}]
  },
  "summary": {
    "total": 4,
    "added": 1,
    "modified": 1,
    "deleted": 1,
    "untracked": 1,
    "renamed": 0
  },
  "has_changes": true,
  "disk_scan": {
    "disks": ["local", "uploads"],
    "findings": {
      "suspicious_php": [{"disk": "uploads", "file": "shell.php", "functions": ["eval"]}],
      "dangerous_files": [{"disk": "uploads", "file": "malware.exe", "extension": "exe"}]
    },
    "summary": {"suspicious_php_count": 1, "dangerous_files_count": 1},
    "has_findings": true
  },
  "exit_code": 1
}
```

## CI Example (GitHub Actions)

```yaml
name: File Integrity Check

on: [push, pull_request]

jobs:
  file-integrity:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0  # Full history for diff against origin/main

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Run file integrity scan
        run: php artisan file-integrity:scan --base-ref=origin/main --json
        env:
          FILE_INTEGRITY_FAIL_ON_CHANGES: true
```

## Environment Variables

| Variable | Description |
|----------|-------------|
| `FILE_INTEGRITY_BASE_REF` | Override default base ref |
| `FILE_INTEGRITY_INCLUDE_UNTRACKED` | `false` to skip untracked file detection |
| `FILE_INTEGRITY_DISK_SCAN` | Comma-separated disk names to scan (e.g. `local,uploads`) |
| `FILE_INTEGRITY_OUTPUT` | `console`, `json`, or `both` |
| `FILE_INTEGRITY_FAIL_ON_CHANGES` | `true` to exit 1 on changes or disk findings |
| `FILE_INTEGRITY_LOG` | `true` to log results |
| `FILE_INTEGRITY_MAIL` | `true` to send mail report |
| `FILE_INTEGRITY_MAIL_TO` | Comma-separated email addresses |

## Error Handling

- **Not a Git repository**: Exits with code 1 and shows an error message.
- **Git command failed**: Exits with code 1 (e.g. invalid base ref, Git not installed).

## Testing

```bash
composer test
```

## Publishing to Packagist

1. Push the package to GitHub.
2. Create a release (optional, for versioning).
3. Submit the repository URL at [packagist.org](https://packagist.org).
4. Enable auto-update via GitHub webhook.

## License

MIT
