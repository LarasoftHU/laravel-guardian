<?php

declare(strict_types=1);

namespace Larasofthu\LaravelGuardian;

use Larasofthu\LaravelGuardian\Commands\ScanFileIntegrityCommand;
use Larasofthu\LaravelGuardian\Services\GitDiffService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FileIntegrityServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('file-integrity')
            ->hasConfigFile('file-integrity')
            ->hasViews()
            ->hasCommand(ScanFileIntegrityCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(GitDiffService::class);
    }
}
