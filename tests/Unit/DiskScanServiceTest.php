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
        $this->assertSame([], $result['malware_patterns']);
        $this->assertSame([], $result['dangerous_files']);
        $this->assertSame([], $result['suspicious_paths']);
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
        config()->set('file-integrity.dangerous_extensions', ['exe']);

        $service = $this->app->make(DiskScanService::class);
        $result = $service->scanDisks(['test']);

        $this->assertCount(1, $result['suspicious_php']);
        $this->assertSame('uploads/shell.php', $result['suspicious_php'][0]['file']);
        $this->assertSame(['eval'], $result['suspicious_php'][0]['functions']);
    }

    public function test_scan_disks_detects_malware_patterns(): void
    {
        Storage::fake('test');
        Storage::disk('test')->put('uploads/backdoor.php', '<?php eval(base64_decode($_POST["x"]));');

        config()->set('file-integrity.malware_patterns', [
            'eval_base64_decode' => 'eval\s*\(\s*base64_decode\s*\(',
        ]);
        config()->set('file-integrity.dangerous_extensions', ['exe']);

        $service = $this->app->make(DiskScanService::class);
        $result = $service->scanDisks(['test']);

        $this->assertCount(1, $result['malware_patterns']);
        $this->assertSame('uploads/backdoor.php', $result['malware_patterns'][0]['file']);
        $this->assertSame('eval_base64_decode', $result['malware_patterns'][0]['pattern']);
    }

    public function test_scan_disks_detects_php_disguised_as_webp(): void
    {
        Storage::fake('test');
        Storage::disk('test')->put('uploads/fake-image.webp', "<?php eval(\$_POST['x']);");

        config()->set('file-integrity.suspicious_php_functions', ['eval']);
        config()->set('file-integrity.malware_patterns', []);
        config()->set('file-integrity.dangerous_extensions', ['exe', 'php']);

        $service = $this->app->make(DiskScanService::class);
        $result = $service->scanDisks(['test']);

        $this->assertCount(1, $result['suspicious_php']);
        $this->assertSame('uploads/fake-image.webp', $result['suspicious_php'][0]['file']);
    }

    public function test_scan_disks_skips_pattern_scan_for_dangerous_extension(): void
    {
        Storage::fake('test');
        Storage::disk('test')->put('uploads/shell.php', '<?php eval(base64_decode($_POST["x"]));');

        config()->set('file-integrity.malware_patterns', [
            'eval_base64_decode' => 'eval\s*\(\s*base64_decode\s*\(',
        ]);
        config()->set('file-integrity.dangerous_extensions', ['exe', 'php']);

        $service = $this->app->make(DiskScanService::class);
        $result = $service->scanDisks(['test']);

        $this->assertCount(1, $result['dangerous_files']);
        $this->assertSame('uploads/shell.php', $result['dangerous_files'][0]['file']);
        $this->assertCount(0, $result['malware_patterns']);
        $this->assertCount(0, $result['suspicious_php']);
    }

    public function test_scan_disks_skips_large_files(): void
    {
        Storage::fake('test');
        $largeContent = str_repeat('x', 250 * 1024);
        Storage::disk('test')->put('uploads/large.webp', "<?php eval('x');" . $largeContent);

        config()->set('file-integrity.suspicious_php_functions', ['eval']);
        config()->set('file-integrity.dangerous_extensions', ['exe']);
        config()->set('file-integrity.content_scan_max_bytes', 200 * 1024);

        $service = $this->app->make(DiskScanService::class);
        $result = $service->scanDisks(['test']);

        $this->assertCount(0, $result['suspicious_php']);
    }

    public function test_scan_disks_skips_unknown_disk(): void
    {
        $service = $this->app->make(DiskScanService::class);
        $result = $service->scanDisks(['nonexistent-disk-xyz']);

        $this->assertSame([], $result['suspicious_php']);
        $this->assertSame([], $result['malware_patterns']);
        $this->assertSame([], $result['dangerous_files']);
        $this->assertSame([], $result['suspicious_paths']);
    }

    public function test_scan_disks_detects_suspicious_paths_in_storage(): void
    {
        Storage::fake('test');
        Storage::disk('test')->put('uploads/wp-admin/shell.php', '<?php echo 1;');

        config()->set('file-integrity.suspicious_path_patterns', ['wp-admin']);
        config()->set('file-integrity.dangerous_extensions', ['exe']);

        $service = $this->app->make(DiskScanService::class);
        $result = $service->scanDisks(['test']);

        $this->assertCount(1, $result['suspicious_paths']);
        $this->assertSame('uploads/wp-admin/shell.php', $result['suspicious_paths'][0]['file']);
        $this->assertSame('wp-admin', $result['suspicious_paths'][0]['pattern']);
    }
}
