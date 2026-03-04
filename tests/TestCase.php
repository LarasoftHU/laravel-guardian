<?php

declare(strict_types=1);

namespace Larasofthu\LaravelGuardian\Tests;

use Larasofthu\LaravelGuardian\FileIntegrityServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FileIntegrityServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('file-integrity.base_ref', 'HEAD');
        config()->set('file-integrity.paths', []);
        config()->set('file-integrity.exclude_paths', []);
        config()->set('file-integrity.report.output', 'console');
        config()->set('file-integrity.report.fail_on_changes', false);
    }
}
