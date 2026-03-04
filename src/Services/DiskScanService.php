<?php

declare(strict_types=1);

namespace Larasofthu\LaravelGuardian\Services;

use Illuminate\Support\Facades\Storage;

class DiskScanService
{
    private const PHP_OPENING_TAGS = '/<\?php|<\?=|<\\?/i';

    /**
     * Scan one or more storage disks for suspicious PHP functions, malware patterns, and dangerous file extensions.
     * Files under content_scan_max_bytes are read and checked for PHP content regardless of extension
     * (e.g. .webp containing <?php). Files with dangerous extensions are not pattern-scanned (already flagged).
     *
     * @param  string[]  $disks
     * @return array{suspicious_php: array<int, array{disk: string, file: string, functions: string[]}>, malware_patterns: array<int, array{disk: string, file: string, pattern: string}>, dangerous_files: array<int, array{disk: string, file: string, extension: string}>}
     */
    public function scanDisks(array $disks): array
    {
        $suspiciousPhp = [];
        $malwarePatterns = [];
        $dangerousFiles = [];

        $suspiciousFunctions = config('file-integrity.suspicious_php_functions', []);
        $malwarePatternConfig = config('file-integrity.malware_patterns', []);
        $dangerousExtensions = array_map('strtolower', config('file-integrity.dangerous_extensions', []));
        $maxContentBytes = (int) config('file-integrity.content_scan_max_bytes', 200 * 1024);

        foreach ($disks as $diskName) {
            try {
                $disk = Storage::disk($diskName);
            } catch (\Throwable) {
                continue;
            }

            $allFiles = $this->getAllFiles($disk);

            foreach ($allFiles as $path) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $hasDangerousExtension = in_array($ext, $dangerousExtensions, true);

                if ($hasDangerousExtension) {
                    $dangerousFiles[] = [
                        'disk' => $diskName,
                        'file' => $path,
                        'extension' => $ext,
                    ];
                }

                if ($hasDangerousExtension) {
                    continue;
                }

                $size = $this->getFileSize($disk, $path);
                if ($size === null || $size > $maxContentBytes) {
                    continue;
                }

                $content = $this->getFileContent($disk, $path);
                if ($content === null || ! preg_match(self::PHP_OPENING_TAGS, $content)) {
                    continue;
                }

                $found = $this->scanContentForSuspiciousFunctions($content, $suspiciousFunctions);
                if (! empty($found)) {
                    $suspiciousPhp[] = [
                        'disk' => $diskName,
                        'file' => $path,
                        'functions' => $found,
                    ];
                }

                $matchedPatterns = $this->scanContentForMalwarePatterns($content, $malwarePatternConfig);
                foreach ($matchedPatterns as $patternName) {
                    $malwarePatterns[] = [
                        'disk' => $diskName,
                        'file' => $path,
                        'pattern' => $patternName,
                    ];
                }
            }
        }

        return [
            'suspicious_php' => $suspiciousPhp,
            'malware_patterns' => $malwarePatterns,
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

    private function getFileSize($disk, string $path): ?int
    {
        try {
            $size = $disk->size($path);
            return is_int($size) ? $size : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function getFileContent($disk, string $path): ?string
    {
        try {
            $content = $disk->get($path);
            return is_string($content) ? $content : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  string[]  $suspiciousFunctions
     * @return string[]
     */
    private function scanContentForSuspiciousFunctions(string $content, array $suspiciousFunctions): array
    {
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

    /**
     * @param  array<string, string>  $malwarePatternConfig  Pattern name => regex
     * @return string[]
     */
    private function scanContentForMalwarePatterns(string $content, array $malwarePatternConfig): array
    {
        $found = [];
        foreach ($malwarePatternConfig as $patternName => $regex) {
            $fullPattern = '#' . $regex . '#';
            if (@preg_match($fullPattern, $content) === 1) {
                $found[] = $patternName;
            }
        }

        return $found;
    }
}
