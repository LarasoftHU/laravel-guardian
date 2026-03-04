<?php

declare(strict_types=1);

namespace Larasofthu\LaravelGuardian\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Larasofthu\LaravelGuardian\Services\DiskScanService;
use Larasofthu\LaravelGuardian\Services\GitDiffService;

class ScanFileIntegrityCommand extends Command
{
    protected $signature = 'file-integrity:scan
        {--base-ref= : Git reference to compare against (overrides config)}
        {--json : Output JSON for CI usage}
        {--paths=* : Paths to include (overrides config)}
        {--exclude-paths=* : Paths to exclude (overrides config)}
        {--disks=* : Storage disks to scan for suspicious PHP and dangerous extensions (overrides config)}
        {--no-disk-scan : Skip disk scan even when configured}
        {--no-fail : Do not exit with non-zero code when changes found}';

    protected $description = 'Scan for modified, added, or deleted files compared to Git state; optionally scan storage disks for suspicious PHP and dangerous file extensions';

    public function __construct(
        private readonly GitDiffService $gitDiffService,
        private readonly DiskScanService $diskScanService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $basePath = base_path();
        $gitDir = $basePath . DIRECTORY_SEPARATOR . '.git';

        if (! is_dir($gitDir)) {
            $this->error('This project is not a Git repository (no .git directory found).');
            return self::FAILURE;
        }

        $baseRef = $this->option('base-ref') ?: config('file-integrity.base_ref', 'HEAD');
        $paths = $this->option('paths') ?: config('file-integrity.paths', []);
        $excludePaths = $this->option('exclude-paths') ?: config('file-integrity.exclude_paths', []);
        $outputJson = $this->option('json') || config('file-integrity.report.output') === 'json';
        $outputBoth = config('file-integrity.report.output') === 'both';
        $failOnChanges = ! $this->option('no-fail') && config('file-integrity.report.fail_on_changes', false);

        $result = $this->gitDiffService->runDiff($basePath, $baseRef);
        if ($result === null) {
            $this->error('Git command failed: ' . ($this->gitDiffService->getLastError() ?: 'Unknown error'));
            $this->error('Make sure Git is installed and the base ref "' . $baseRef . '" exists.');

            return self::FAILURE;
        }

        $changedFiles = $this->parseGitOutput($result);

        $includeUntracked = config('file-integrity.include_untracked', true);
        if ($includeUntracked) {
            $untracked = $this->gitDiffService->getUntrackedFiles($basePath);
            $changedFiles['untracked'] = $untracked;
        } else {
            $changedFiles['untracked'] = [];
        }

        $changedFiles = $this->filterByPaths($changedFiles, $paths, $excludePaths);

        $summary = $this->buildSummary($changedFiles);
        $hasChanges = $summary['total'] > 0;

        $diskScanFindings = null;
        $noDiskScan = $this->option('no-disk-scan');
        $disksToScan = $noDiskScan ? [] : ($this->option('disks') ?: config('file-integrity.disk_scan', []));
        $disksToScan = is_array($disksToScan) ? $disksToScan : [];
        $scanPublicPath = ! $noDiskScan && config('file-integrity.scan_public_path', true);
        $disksForReport = $disksToScan;
        if ($scanPublicPath && ! in_array('public', $disksForReport, true)) {
            $disksForReport = array_merge($disksForReport, ['public']);
        }

        if (! empty($disksToScan) || $scanPublicPath) {
            $diskScanFindings = $this->diskScanService->scanDisks($disksToScan);
            $diskSummary = $this->buildDiskSummary($diskScanFindings);
            $hasDiskFindings = $diskSummary['suspicious_php_count'] > 0
                || $diskSummary['malware_patterns_count'] > 0
                || $diskSummary['dangerous_files_count'] > 0
                || $diskSummary['suspicious_paths_count'] > 0;
        } else {
            $diskScanFindings = null;
            $diskSummary = ['suspicious_php_count' => 0, 'malware_patterns_count' => 0, 'dangerous_files_count' => 0, 'suspicious_paths_count' => 0];
            $hasDiskFindings = false;
        }

        $shouldFail = $failOnChanges && ($hasChanges || $hasDiskFindings);
        $exitCode = $shouldFail ? self::FAILURE : self::SUCCESS;

        $report = [
            'base_ref' => $baseRef,
            'changed_files' => $changedFiles,
            'summary' => $summary,
            'has_changes' => $hasChanges,
            'disk_scan' => [
                'disks' => $disksForReport,
                'findings' => $diskScanFindings,
                'summary' => $diskSummary,
                'has_findings' => $hasDiskFindings,
            ],
            'exit_code' => $exitCode,
        ];

        if ($outputJson || $outputBoth) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        if (! $outputJson || $outputBoth) {
            $this->outputConsoleReport($report);
        }

        if (config('file-integrity.report.log', false)) {
            Log::info('File integrity scan completed', $report);
        }

        $shouldMail = ($hasChanges || $hasDiskFindings) && config('file-integrity.report.mail', false);
        if ($shouldMail) {
            $this->sendMailReport($report);
        }

        return $exitCode;
    }

    /**
     * @return array{added: string[], modified: string[], deleted: string[], renamed: array<int, array{from: string, to: string}>, untracked: string[]}
     */
    private function parseGitOutput(string $output): array
    {
        $changedFiles = [
            'added' => [],
            'modified' => [],
            'deleted' => [],
            'renamed' => [],
            'untracked' => [],
        ];

        $lines = array_filter(explode("\n", trim($output)));

        foreach ($lines as $line) {
            if (strlen($line) < 2) {
                continue;
            }

            $status = $line[0];
            $rest = trim(substr($line, 1));

            if ($status === 'R' || $status === 'C') {
                $parts = preg_split('/\s+/', $rest, 2);
                if (count($parts) >= 2) {
                    $changedFiles['renamed'][] = ['from' => $parts[0], 'to' => $parts[1]];
                }
            } else {
                $file = $rest;
                match ($status) {
                    'A' => $changedFiles['added'][] = $file,
                    'M' => $changedFiles['modified'][] = $file,
                    'D' => $changedFiles['deleted'][] = $file,
                    default => null,
                };
            }
        }

        return $changedFiles;
    }

    /**
     * @param  array{added: string[], modified: string[], deleted: string[], renamed: array<int, array{from: string, to: string}>, untracked: string[]}  $changedFiles
     * @param  string[]  $paths
     * @param  string[]  $excludePaths
     * @return array{added: string[], modified: string[], deleted: string[], renamed: array<int, array{from: string, to: string}>, untracked: string[]}
     */
    private function filterByPaths(array $changedFiles, array $paths, array $excludePaths): array
    {
        $filter = function (string $file) use ($paths, $excludePaths): bool {
            $normalized = str_replace('\\', '/', $file);

            foreach ($excludePaths as $pattern) {
                if ($this->matchGlob($normalized, $pattern)) {
                    return false;
                }
            }

            if (empty($paths)) {
                return true;
            }

            foreach ($paths as $path) {
                $path = rtrim(str_replace('\\', '/', $path), '/');
                if (str_starts_with($normalized, $path . '/') || $normalized === $path) {
                    return true;
                }
            }

            return false;
        };

        $changedFiles['added'] = array_values(array_filter($changedFiles['added'], $filter));
        $changedFiles['modified'] = array_values(array_filter($changedFiles['modified'], $filter));
        $changedFiles['deleted'] = array_values(array_filter($changedFiles['deleted'], $filter));
        $changedFiles['untracked'] = array_values(array_filter($changedFiles['untracked'] ?? [], $filter));

        $changedFiles['renamed'] = array_values(array_filter(
            $changedFiles['renamed'],
            function (array $pair) use ($filter): bool {
                return $filter($pair['from']) || $filter($pair['to']);
            }
        ));

        return $changedFiles;
    }

    private function matchGlob(string $path, string $pattern): bool
    {
        $pattern = str_replace('\\', '/', $pattern);
        $regex = preg_quote($pattern, '#');
        $regex = str_replace('\*\*', '.*', $regex);
        $regex = str_replace('\*', '[^/]*', $regex);

        return (bool) preg_match('#^' . $regex . '$#', $path);
    }

    /**
     * @param  array{added: string[], modified: string[], deleted: string[], renamed: array<int, array{from: string, to: string}>, untracked: string[]}  $changedFiles
     * @return array{total: int, added: int, modified: int, deleted: int, renamed: int, untracked: int}
     */
    private function buildSummary(array $changedFiles): array
    {
        $untracked = count($changedFiles['untracked'] ?? []);
        return [
            'total' => count($changedFiles['added']) + count($changedFiles['modified'])
                + count($changedFiles['deleted']) + count($changedFiles['renamed']) + $untracked,
            'added' => count($changedFiles['added']),
            'modified' => count($changedFiles['modified']),
            'deleted' => count($changedFiles['deleted']),
            'renamed' => count($changedFiles['renamed']),
            'untracked' => $untracked,
        ];
    }

    /**
     * @param  array{suspicious_php: array, malware_patterns: array, dangerous_files: array, suspicious_paths: array}  $diskScanFindings
     * @return array{suspicious_php_count: int, malware_patterns_count: int, dangerous_files_count: int, suspicious_paths_count: int}
     */
    private function buildDiskSummary(array $diskScanFindings): array
    {
        return [
            'suspicious_php_count' => count($diskScanFindings['suspicious_php'] ?? []),
            'malware_patterns_count' => count($diskScanFindings['malware_patterns'] ?? []),
            'dangerous_files_count' => count($diskScanFindings['dangerous_files'] ?? []),
            'suspicious_paths_count' => count($diskScanFindings['suspicious_paths'] ?? []),
        ];
    }

    /**
     * @param  array{base_ref: string, changed_files: array, summary: array, has_changes: bool, disk_scan: array, exit_code: int}  $report
     */
    private function outputConsoleReport(array $report): void
    {
        $summary = $report['summary'];
        $changedFiles = $report['changed_files'];
        $diskScan = $report['disk_scan'] ?? null;

        $this->newLine();
        $this->info('File Integrity Scan (base: ' . $report['base_ref'] . ')');
        $this->line('Total changed files: ' . $summary['total']);

        if ($diskScan && ! empty($diskScan['disks'])) {
            $ds = $diskScan['summary'] ?? [];
            $this->line('Disk scan (' . implode(', ', $diskScan['disks']) . '): '
                . ($ds['suspicious_php_count'] ?? 0) . ' suspicious PHP, '
                . ($ds['malware_patterns_count'] ?? 0) . ' malware patterns, '
                . ($ds['dangerous_files_count'] ?? 0) . ' dangerous extensions, '
                . ($ds['suspicious_paths_count'] ?? 0) . ' WordPress/CMS-like paths');
        }
        $this->newLine();

        if ($summary['total'] > 0) {
            $rows = [];
            foreach ($changedFiles['added'] as $file) {
                $rows[] = ['Added', $file, ''];
            }
            foreach ($changedFiles['modified'] as $file) {
                $rows[] = ['Modified', $file, ''];
            }
            foreach ($changedFiles['deleted'] as $file) {
                $rows[] = ['Deleted', $file, ''];
            }
            foreach ($changedFiles['untracked'] ?? [] as $file) {
                $rows[] = ['Untracked', $file, ''];
            }
            foreach ($changedFiles['renamed'] as $pair) {
                $rows[] = ['Renamed', $pair['from'], '→ ' . $pair['to']];
            }
            $this->table(['Status', 'File', 'To'], $rows);
        }

        if ($diskScan && ($diskScan['has_findings'] ?? false)) {
            $findings = $diskScan['findings'] ?? [];
            if (! empty($findings['suspicious_php'])) {
                $this->newLine();
                $this->warn('Suspicious PHP functions detected:');
                $rows = [];
                foreach ($findings['suspicious_php'] as $item) {
                    $rows[] = [$item['disk'], $item['file'], implode(', ', $item['functions'])];
                }
                $this->table(['Disk', 'File', 'Functions'], $rows);
            }
            if (! empty($findings['malware_patterns'])) {
                $this->newLine();
                $this->warn('Malware patterns detected:');
                $rows = [];
                foreach ($findings['malware_patterns'] as $item) {
                    $rows[] = [$item['disk'], $item['file'], $item['pattern']];
                }
                $this->table(['Disk', 'File', 'Pattern'], $rows);
            }
            if (! empty($findings['dangerous_files'])) {
                $this->newLine();
                $this->warn('Dangerous file extensions found:');
                $rows = [];
                foreach ($findings['dangerous_files'] as $item) {
                    $rows[] = [$item['disk'], $item['file'], $item['extension']];
                }
                $this->table(['Disk', 'File', 'Extension'], $rows);
            }
            if (! empty($findings['suspicious_paths'])) {
                $this->newLine();
                $this->warn('WordPress/CMS-like paths found:');
                $rows = [];
                foreach ($findings['suspicious_paths'] as $item) {
                    $rows[] = [$item['disk'], $item['file'], $item['pattern']];
                }
                $this->table(['Location', 'File', 'Pattern'], $rows);
            }
        }

        if ($summary['total'] === 0 && ! ($diskScan['has_findings'] ?? false)) {
            $this->comment('No changes or security findings detected.');
        }
    }

    /**
     * @param  array{base_ref: string, changed_files: array, summary: array, has_changes: bool}  $report
     */
    private function sendMailReport(array $report): void
    {
        $recipients = config('file-integrity.report.mail_to', []);
        $recipients = is_array($recipients)
            ? $recipients
            : array_filter(array_map('trim', explode(',', (string) $recipients)));
        if (empty($recipients)) {
            return;
        }

        try {
            Mail::send('file-integrity::file-integrity-report', ['report' => $report], function ($message) use ($recipients): void {
                $message->to($recipients)
                    ->subject('File Integrity Scan: Changes Detected');
            });
        } catch (\Throwable $e) {
            $this->warn('Failed to send mail report: ' . $e->getMessage());
        }
    }
}
