<?php

declare(strict_types=1);

namespace Larasofthu\LaravelGuardian\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Larasofthu\LaravelGuardian\Services\GitDiffService;

class ScanFileIntegrityCommand extends Command
{
    protected $signature = 'file-integrity:scan
        {--base-ref= : Git reference to compare against (overrides config)}
        {--json : Output JSON for CI usage}
        {--paths=* : Paths to include (overrides config)}
        {--exclude-paths=* : Paths to exclude (overrides config)}
        {--no-fail : Do not exit with non-zero code when changes found}';

    protected $description = 'Scan for modified, added, or deleted files compared to Git state';

    public function __construct(
        private readonly GitDiffService $gitDiffService
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
        $changedFiles = $this->filterByPaths($changedFiles, $paths, $excludePaths);

        $summary = $this->buildSummary($changedFiles);
        $hasChanges = $summary['total'] > 0;

        $exitCode = ($hasChanges && $failOnChanges) ? self::FAILURE : self::SUCCESS;

        $report = [
            'base_ref' => $baseRef,
            'changed_files' => $changedFiles,
            'summary' => $summary,
            'has_changes' => $hasChanges,
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

        if ($hasChanges && config('file-integrity.report.mail', false)) {
            $this->sendMailReport($report);
        }

        return $exitCode;
    }

    /**
     * @return array{added: string[], modified: string[], deleted: string[], renamed: array<int, array{from: string, to: string}>}
     */
    private function parseGitOutput(string $output): array
    {
        $changedFiles = [
            'added' => [],
            'modified' => [],
            'deleted' => [],
            'renamed' => [],
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
     * @param  array{added: string[], modified: string[], deleted: string[], renamed: array<int, array{from: string, to: string}>}  $changedFiles
     * @param  string[]  $paths
     * @param  string[]  $excludePaths
     * @return array{added: string[], modified: string[], deleted: string[], renamed: array<int, array{from: string, to: string}>}
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
     * @param  array{added: string[], modified: string[], deleted: string[], renamed: array<int, array{from: string, to: string}>}  $changedFiles
     * @return array{total: int, added: int, modified: int, deleted: int, renamed: int}
     */
    private function buildSummary(array $changedFiles): array
    {
        return [
            'total' => count($changedFiles['added']) + count($changedFiles['modified'])
                + count($changedFiles['deleted']) + count($changedFiles['renamed']),
            'added' => count($changedFiles['added']),
            'modified' => count($changedFiles['modified']),
            'deleted' => count($changedFiles['deleted']),
            'renamed' => count($changedFiles['renamed']),
        ];
    }

    /**
     * @param  array{base_ref: string, changed_files: array, summary: array, has_changes: bool, exit_code: int}  $report
     */
    private function outputConsoleReport(array $report): void
    {
        $summary = $report['summary'];
        $changedFiles = $report['changed_files'];

        $this->newLine();
        $this->info('File Integrity Scan (base: ' . $report['base_ref'] . ')');
        $this->line('Total changed files: ' . $summary['total']);
        $this->newLine();

        if ($summary['total'] === 0) {
            $this->comment('No changes detected.');
            return;
        }

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
        foreach ($changedFiles['renamed'] as $pair) {
            $rows[] = ['Renamed', $pair['from'], '→ ' . $pair['to']];
        }

        $this->table(['Status', 'File', 'To'], $rows);
    }

    /**
     * @param  array{base_ref: string, changed_files: array, summary: array, has_changes: bool}  $report
     */
    private function sendMailReport(array $report): void
    {
        $recipients = config('file-integrity.report.mail_to', []);
        if (empty($recipients)) {
            return;
        }

        try {
            Mail::raw(
                "File Integrity Scan Report\n\n" .
                "Base ref: {$report['base_ref']}\n" .
                "Total changes: {$report['summary']['total']}\n\n" .
                json_encode($report['changed_files'], JSON_PRETTY_PRINT),
                function ($message) use ($recipients): void {
                    $message->to($recipients)
                        ->subject('File Integrity Scan: Changes Detected');
                }
            );
        } catch (\Throwable $e) {
            $this->warn('Failed to send mail report: ' . $e->getMessage());
        }
    }
}
