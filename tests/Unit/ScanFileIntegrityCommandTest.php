<?php

declare(strict_types=1);

namespace Larasofthu\LaravelGuardian\Tests\Unit;

use Larasofthu\LaravelGuardian\Commands\ScanFileIntegrityCommand;
use Larasofthu\LaravelGuardian\Services\GitDiffService;
use Larasofthu\LaravelGuardian\Tests\TestCase;
use Illuminate\Support\Facades\File;

class ScanFileIntegrityCommandTest extends TestCase
{
    public function test_command_can_be_resolved(): void
    {
        $command = $this->app->make(ScanFileIntegrityCommand::class);
        $this->assertInstanceOf(ScanFileIntegrityCommand::class, $command);
        $this->assertSame('file-integrity:scan', $command->getName());
    }

    public function test_command_fails_when_not_git_repository(): void
    {
        $tempDir = sys_get_temp_dir() . '/laravel-guardian-test-' . uniqid();
        File::makeDirectory($tempDir, 0755, true);

        try {
            $this->app->setBasePath($tempDir);

            $gitDiffService = $this->createMock(GitDiffService::class);
            $gitDiffService->expects($this->never())->method('runDiff');

            $this->app->instance(GitDiffService::class, $gitDiffService);

            $command = $this->app->make(ScanFileIntegrityCommand::class);
            $command->setLaravel($this->app);

            $exitCode = $command->run(
                new \Symfony\Component\Console\Input\ArrayInput([]),
                new \Symfony\Component\Console\Output\BufferedOutput()
            );

            $this->assertSame(ScanFileIntegrityCommand::FAILURE, $exitCode);
        } finally {
            File::deleteDirectory($tempDir);
        }
    }

    public function test_command_parses_git_output_and_outputs_json(): void
    {
        $tempDir = sys_get_temp_dir() . '/laravel-guardian-test-' . uniqid();
        File::makeDirectory($tempDir, 0755, true);
        exec("cd {$tempDir} && git init 2>/dev/null");

        try {
            $this->app->setBasePath($tempDir);

            $sampleOutput = "M\tapp/Models/User.php\nA\tconfig/new.php\nD\told/file.php";

            $gitDiffService = $this->createMock(GitDiffService::class);
            $gitDiffService->method('runDiff')->willReturn($sampleOutput);
            $gitDiffService->method('getUntrackedFiles')->willReturn([]);

            $this->app->instance(GitDiffService::class, $gitDiffService);

            $command = $this->app->make(ScanFileIntegrityCommand::class);
            $command->setLaravel($this->app);

            $exitCode = $command->run(
                new \Symfony\Component\Console\Input\ArrayInput(['--json' => true]),
                new \Symfony\Component\Console\Output\BufferedOutput()
            );

            $this->assertSame(ScanFileIntegrityCommand::SUCCESS, $exitCode);
        } finally {
            exec("rm -rf {$tempDir}");
        }
    }

    public function test_command_respects_config_override(): void
    {
        $tempDir = sys_get_temp_dir() . '/laravel-guardian-test-' . uniqid();
        File::makeDirectory($tempDir, 0755, true);
        exec("cd {$tempDir} && git init 2>/dev/null");

        try {
            $this->app->setBasePath($tempDir);
            config()->set('file-integrity.base_ref', 'origin/main');
            config()->set('file-integrity.report.fail_on_changes', true);

            $gitDiffService = $this->createMock(GitDiffService::class);
            $gitDiffService->method('runDiff')->willReturn("M\tapp/Test.php");
            $gitDiffService->method('getUntrackedFiles')->willReturn([]);

            $this->app->instance(GitDiffService::class, $gitDiffService);

            $command = $this->app->make(ScanFileIntegrityCommand::class);
            $command->setLaravel($this->app);

            $exitCode = $command->run(
                new \Symfony\Component\Console\Input\ArrayInput(['--json' => true]),
                new \Symfony\Component\Console\Output\BufferedOutput()
            );

            $this->assertSame(ScanFileIntegrityCommand::FAILURE, $exitCode);
        } finally {
            exec("rm -rf {$tempDir}");
        }
    }

    public function test_no_fail_option_overrides_config(): void
    {
        $tempDir = sys_get_temp_dir() . '/laravel-guardian-test-' . uniqid();
        File::makeDirectory($tempDir, 0755, true);
        exec("cd {$tempDir} && git init 2>/dev/null");

        try {
            $this->app->setBasePath($tempDir);
            config()->set('file-integrity.report.fail_on_changes', true);

            $gitDiffService = $this->createMock(GitDiffService::class);
            $gitDiffService->method('runDiff')->willReturn("M\tapp/Test.php");
            $gitDiffService->method('getUntrackedFiles')->willReturn([]);

            $this->app->instance(GitDiffService::class, $gitDiffService);

            $command = $this->app->make(ScanFileIntegrityCommand::class);
            $command->setLaravel($this->app);

            $exitCode = $command->run(
                new \Symfony\Component\Console\Input\ArrayInput(['--json' => true, '--no-fail' => true]),
                new \Symfony\Component\Console\Output\BufferedOutput()
            );

            $this->assertSame(ScanFileIntegrityCommand::SUCCESS, $exitCode);
        } finally {
            exec("rm -rf {$tempDir}");
        }
    }

    public function test_command_includes_untracked_files_in_report(): void
    {
        $tempDir = sys_get_temp_dir() . '/laravel-guardian-test-' . uniqid();
        File::makeDirectory($tempDir, 0755, true);
        exec("cd {$tempDir} && git init 2>/dev/null");

        try {
            $this->app->setBasePath($tempDir);

            $gitDiffService = $this->createMock(GitDiffService::class);
            $gitDiffService->method('runDiff')->willReturn('');
            $gitDiffService->method('getUntrackedFiles')->willReturn(['app/NewFile.php', 'config/extra.php']);

            $this->app->instance(GitDiffService::class, $gitDiffService);

            $command = $this->app->make(ScanFileIntegrityCommand::class);
            $command->setLaravel($this->app);

            $output = new \Symfony\Component\Console\Output\BufferedOutput();
            $exitCode = $command->run(
                new \Symfony\Component\Console\Input\ArrayInput(['--json' => true]),
                $output
            );

            $report = json_decode($output->fetch(), true);
            $this->assertSame(2, $report['summary']['untracked']);
            $this->assertSame(['app/NewFile.php', 'config/extra.php'], $report['changed_files']['untracked']);
            $this->assertSame(2, $report['summary']['total']);
        } finally {
            exec("rm -rf {$tempDir}");
        }
    }
}
