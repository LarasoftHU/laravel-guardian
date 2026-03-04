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

    public function getLastError(): ?string
    {
        return $this->lastError;
    }
}
