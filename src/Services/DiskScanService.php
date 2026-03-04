<?php

declare(strict_types=1);

namespace Larasofthu\LaravelGuardian\Services;

use Illuminate\Support\Facades\Storage;

class DiskScanService
{
    /**
     * Scan one or more storage disks for suspicious PHP functions and dangerous file extensions.
     *
     * @param  string[]  $disks
     * @return array{suspicious_php: array<int, array{disk: string, file: string, functions: string[]}>, dangerous_files: array<int, array{disk: string, file: string, extension: string}>}
     */
    public function scanDisks(array $disks): array
    {
        $suspiciousPhp = [];
        $dangerousFiles = [];

        $suspiciousFunctions = config('file-integrity.suspicious_php_functions', []);
        $dangerousExtensions = array_map('strtolower', config('file-integrity.dangerous_extensions', []));

        foreach ($disks as $diskName) {
            try {
                $disk = Storage::disk($diskName);
            } catch (\Throwable) {
                continue;
            }

            $allFiles = $this->getAllFiles($disk);

            foreach ($allFiles as $path) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                if (in_array($ext, $dangerousExtensions, true)) {
                    $dangerousFiles[] = [
                        'disk' => $diskName,
                        'file' => $path,
                        'extension' => $ext,
                    ];
                }

                if ($ext === 'php' || $ext === 'php3' || $ext === 'php4' || $ext === 'php5' || $ext === 'phtml') {
                    $found = $this->scanPhpForSuspiciousFunctions($disk, $path, $suspiciousFunctions);
                    if (! empty($found)) {
                        $suspiciousPhp[] = [
                            'disk' => $diskName,
                            'file' => $path,
                            'functions' => $found,
                        ];
                    }
                }
            }
        }

        return [
            'suspicious_php' => $suspiciousPhp,
            'dangerous_files' => $dangerousFiles,
        ];
    }

    /**
     * @return string[]
     */
    private function getAllFiles($disk): array
    {
        try {
            return $disk->allFiles('');
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param  string[]  $suspiciousFunctions
     * @return string[]
     */
    private function scanPhpForSuspiciousFunctions($disk, string $path, array $suspiciousFunctions): array
    {
        try {
            $content = $disk->get($path);
        } catch (\Throwable) {
            return [];
        }

        if (! is_string($content)) {
            return [];
        }

        $found = [];
        foreach ($suspiciousFunctions as $func) {
            if ($this->containsFunctionCall($content, $func)) {
                $found[] = $func;
            }
        }

        return $found;
    }

    private function containsFunctionCall(string $content, string $funcName): bool
    {
        $pattern = '/\b' . preg_quote($funcName, '/') . '\s*\(/';
        return (bool) preg_match($pattern, $content);
    }
}
