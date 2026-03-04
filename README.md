# Laravel Guardian

Laravel Guardian is a security-focused integrity scanner for Laravel projects.

It combines **Git-based integrity checks** (what changed compared to a reference) with **runtime disk/public path security scanning** (what suspicious files currently exist in storage/public).  
This makes it useful for CI/CD, deployment validation, and continuous monitoring on production servers.

## What It Detects

### 1) Git integrity changes

- Modified files
- Added files
- Deleted files
- Renamed files
- Untracked files (respects `.gitignore`)

### 2) Storage/public security findings

- Suspicious PHP function usage in file content
- Known malware-like regex signatures
- Dangerous file extensions in storage disks
- Suspicious WordPress/CMS-like path patterns in storage and `public/`

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12, or 13
- Git (local repository available where scan runs)

## Installation

```bash
composer require larasofthu/laravel-guardian
```

The package is auto-discovered by Laravel.

## Publish Assets

Publish config:

```bash
php artisan vendor:publish --tag="file-integrity-config"
```

Publish mail view (optional):

```bash
php artisan vendor:publish --tag="file-integrity-views"
```

Published mail view path:

`resources/views/vendor/file-integrity/file-integrity-report.blade.php`

## Quick Start

Run the default scan:

```bash
php artisan file-integrity:scan
```

CI-friendly JSON output:

```bash
php artisan file-integrity:scan --json
```

Scan specific disks on demand:

```bash
php artisan file-integrity:scan --disks=local --disks=uploads
```

## Command Options

`file-integrity:scan` supports:

- `--base-ref=` Override Git reference (e.g. `origin/main`, `HEAD`, commit hash)
- `--json` Print JSON report output
- `--paths=*` Limit Git diff scope to specific paths
- `--exclude-paths=*` Exclude paths (glob-style patterns)
- `--disks=*` Override configured storage disks to scan
- `--no-disk-scan` Disable disk/public scan for this run
- `--no-fail` Force success exit code even if findings exist

## Configuration Reference

All options are in `config/file-integrity.php`.

### Git integrity scope

- `base_ref`  
  Git reference used for comparison. Default: `HEAD`.

- `paths`  
  Include only these paths in Git change evaluation. Empty array means no include restriction.

- `exclude_paths`  
  Additional exclusion patterns (separate from `.gitignore` handling for untracked files).

- `include_untracked`  
  Include untracked files in results (default: `true`).

### Disk/public scanning

- `disk_scan`  
  Array of disk names to scan. Example: `['local', 'uploads']`. Empty array skips storage-disk scanning (public path scanning can still run when enabled).

- `content_scan_max_bytes`  
  Max file size read for content-based scans. Default: `200 * 1024`.

- `suspicious_php_functions`  
  Function list used for suspicious PHP function matching.

- `malware_patterns`  
  Named regex signatures used for malware-like content detection.

- `dangerous_extensions`  
  Extensions that should not normally appear in upload/storage locations.

- `suspicious_path_patterns`  
  Case-insensitive path fragments indicating unexpected CMS footprint (WordPress/Joomla/Drupal-like artifacts).

- `scan_public_path`  
  Also inspect `base_path('public')` for suspicious path patterns.

### Reporting behavior

- `report.output`  
  `console`, `json`, or `both`.

- `report.fail_on_changes`  
  Exit with non-zero code when Git changes or disk findings are present.

- `report.log`  
  Log the report payload with Laravel logger.

- `report.mail` and `report.mail_to`  
  Enable and configure email notifications when findings are detected.

## Output Overview

The report includes:

- `base_ref`
- `changed_files` and Git `summary`
- `has_changes`
- `disk_scan` (`disks`, `findings`, `summary`, `has_findings`)
- `exit_code`

Example JSON:

```json
{
  "base_ref": "origin/main",
  "changed_files": {
    "added": ["config/new.php"],
    "modified": ["app/Models/User.php"],
    "deleted": ["old/file.php"],
    "renamed": [{"from": "old.php", "to": "new.php"}],
    "untracked": ["app/NewFile.php"]
  },
  "summary": {
    "total": 5,
    "added": 1,
    "modified": 1,
    "deleted": 1,
    "renamed": 1,
    "untracked": 1
  },
  "has_changes": true,
  "disk_scan": {
    "disks": ["local", "uploads", "public"],
    "findings": {
      "suspicious_php": [{"disk": "uploads", "file": "shell.php", "functions": ["eval"]}],
      "malware_patterns": [],
      "dangerous_files": [{"disk": "uploads", "file": "payload.exe", "extension": "exe"}],
      "suspicious_paths": [{"disk": "public", "file": "wp-admin/index.php", "pattern": "wp-admin"}]
    },
    "summary": {
      "suspicious_php_count": 1,
      "malware_patterns_count": 0,
      "dangerous_files_count": 1,
      "suspicious_paths_count": 1
    },
    "has_findings": true
  },
  "exit_code": 1
}
```

## Exit Code Rules

- `0` when no failing condition is active
- `1` when:
  - The project is not a Git repository, or
  - Git command execution fails, or
  - `report.fail_on_changes=true` and findings exist (unless `--no-fail` is used)

## Environment Variables

| Variable | Description |
|----------|-------------|
| `FILE_INTEGRITY_BASE_REF` | Default value for `base_ref` |
| `FILE_INTEGRITY_INCLUDE_UNTRACKED` | Set `false` to disable untracked detection |
| `FILE_INTEGRITY_DISK_SCAN` | Comma-separated disks (e.g. `local,uploads`) |
| `FILE_INTEGRITY_CONTENT_SCAN_MAX_BYTES` | Max bytes read per file for content scans |
| `FILE_INTEGRITY_SCAN_PUBLIC_PATH` | Set `false` to skip `public/` path-pattern scan |
| `FILE_INTEGRITY_OUTPUT` | `console`, `json`, or `both` |
| `FILE_INTEGRITY_FAIL_ON_CHANGES` | Set `true` for non-zero exit on findings |
| `FILE_INTEGRITY_LOG` | Set `true` to log report output |
| `FILE_INTEGRITY_MAIL` | Set `true` to send mail report |
| `FILE_INTEGRITY_MAIL_TO` | Mail recipients (single or comma-separated) |

## Scheduler Integration

Recommended for periodic monitoring (for example every hour).

Laravel 11+ (`routes/console.php`):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('file-integrity:scan')->hourly();
```

Laravel 10 (`app/Console/Kernel.php`):

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('file-integrity:scan')->hourly();
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
          fetch-depth: 0

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Run scan
        run: php artisan file-integrity:scan --base-ref=origin/main --json
        env:
          FILE_INTEGRITY_FAIL_ON_CHANGES: true
```

## Testing

```bash
composer test
```

## License

MIT
