<?php

declare(strict_types=1);

namespace Larasofthu\LaravelGuardian\Tests\Unit;

use Larasofthu\LaravelGuardian\Services\DiskScanService;
use Larasofthu\LaravelGuardian\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class DiskScanServiceTest extends TestCase
{
    public function test_scan_disks_returns_empty_when_no_disks(): void
    {
        $service = $this->app->make(DiskScanService::class);
        $result = $service->scanDisks([]);

        $this->assertSame([], $result['suspicious_php']);
        $this->assertSame([], $result['dangerous_files']);
    }

    public function test_scan_disks_detects_dangerous_extensions(): void
    {
        Storage::fake('test');
        Storage::disk('test')->put('uploads/file.exe', 'binary');
        Storage::disk('test')->put('uploads/script.php', '<?php echo 1;');

        config()->set('file-integrity.dangerous_extensions', ['exe', 'php']);

        $service = $this->app->make(DiskScanService::class);
        $result = $service->scanDisks(['test']);

        $this->assertCount(2, $result['dangerous_files']);
        $extensions = array_column($result['dangerous_files'], 'extension');
        $this->assertContains('exe', $extensions);
        $this->assertContains('php', $extensions);
    }

    public function test_scan_disks_detects_suspicious_php_functions(): void
    {
        Storage::fake('test');
        Storage::disk('test')->put('uploads/shell.php', '<?php eval($_POST["cmd"]);');

        config()->set('file-integrity.suspicious_php_functions', ['eval']);

        $service = $this->app->make(DiskScanService::class);
        $result = $service->scanDisks(['test']);

        $this->assertCount(1, $result['suspicious_php']);
        $this->assertSame('uploads/shell.php', $result['suspicious_php'][0]['file']);
        $this->assertSame(['eval'], $result['suspicious_php'][0]['functions']);
    }

    public function test_scan_disks_skips_unknown_disk(): void
    {
        $service = $this->app->make(DiskScanService::class);
        $result = $service->scanDisks(['nonexistent-disk-xyz']);

        $this->assertSame([], $result['suspicious_php']);
        $this->assertSame([], $result['dangerous_files']);
    }
}
