<?php

declare(strict_types=1);

namespace Larasofthu\LaravelGuardian\Services;

use Symfony\Component\Process\Process;

class GitDiffService
{
    private ?string $lastError = null;

    public function runDiff(string $basePath, string $baseRef): ?string
    {
        $this->lastError = null;

        $process = new Process(
            ['git', 'diff', '--name-status', $baseRef, '--'],
            $basePath,
            null,
            null,
            30
        );

        $process->run();

        if (! $process->isSuccessful()) {
            $this->lastError = trim($process->getErrorOutput() ?: $process->getOutput());

            return null;
        }

        return $process->getOutput();
    }

    /**
     * Returns untracked files (new files not in Git, respecting .gitignore).
     *
     * @return string[] List of relative file paths
     */
    public function getUntrackedFiles(string $basePath): array
    {
        $this->lastError = null;

        $process = new Process(
            ['git', 'ls-files', '--others', '--exclude-standard'],
            $basePath,
            null,
            null,
            30
        );

        $process->run();

        if (! $process->isSuccessful()) {
            $this->lastError = trim($process->getErrorOutput() ?: $process->getOutput());

            return [];
        }

        $output = trim($process->getOutput());
        if ($output === '') {
            return [];
        }

        return array_values(array_filter(explode("\n", $output)));
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
