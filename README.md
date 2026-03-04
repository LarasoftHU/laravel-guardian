# Laravel Guardian

Laravel package for file integrity checking. Compares local files against the Git state (as stored locally), while respecting `.gitignore`. Ideal for CI/CD pipelines and deployment verification.

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
    "renamed": [{"from": "old.php", "to": "new.php"}]
  },
  "summary": {
    "total": 3,
    "added": 1,
    "modified": 1,
    "deleted": 1,
    "renamed": 0
  },
  "has_changes": true,
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
| `FILE_INTEGRITY_OUTPUT` | `console`, `json`, or `both` |
| `FILE_INTEGRITY_FAIL_ON_CHANGES` | `true` to exit 1 on changes |
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
