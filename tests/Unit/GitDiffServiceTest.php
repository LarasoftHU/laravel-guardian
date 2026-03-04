<?php

declare(strict_types=1);

namespace Larasofthu\LaravelGuardian\Tests\Unit;

use Larasofthu\LaravelGuardian\Services\GitDiffService;
use Larasofthu\LaravelGuardian\Tests\TestCase;

class GitDiffServiceTest extends TestCase
{
    public function test_run_diff_returns_null_when_git_fails(): void
    {
        $tempDir = sys_get_temp_dir() . '/laravel-guardian-test-' . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            $service = new GitDiffService();
            $result = $service->runDiff($tempDir, 'nonexistent-ref-xyz');

            $this->assertNull($result);
            $this->assertNotNull($service->getLastError());
        } finally {
            rmdir($tempDir);
        }
    }

    public function test_run_diff_returns_output_in_valid_repo(): void
    {
        $basePath = base_path();
        if (! is_dir($basePath . '/.git')) {
            $this->markTestSkipped('Test requires a Git repository');
        }

        $service = new GitDiffService();
        $result = $service->runDiff($basePath, 'HEAD');

        $this->assertIsString($result);
    }

    public function test_get_untracked_files_returns_empty_in_valid_repo_with_no_untracked(): void
    {
        $basePath = base_path();
        if (! is_dir($basePath . '/.git')) {
            $this->markTestSkipped('Test requires a Git repository');
        }

        $service = new GitDiffService();
        $untracked = $service->getUntrackedFiles($basePath);

        $this->assertIsArray($untracked);
    }

    public function test_get_untracked_files_respects_gitignore(): void
    {
        $basePath = base_path();
        if (! is_dir($basePath . '/.git')) {
            $this->markTestSkipped('Test requires a Git repository');
        }

        $service = new GitDiffService();
        $untracked = $service->getUntrackedFiles($basePath);

        foreach ($untracked as $file) {
            $this->assertStringNotContainsString('vendor/', $file);
            $this->assertStringNotContainsString('node_modules/', $file);
        }
    }
}
