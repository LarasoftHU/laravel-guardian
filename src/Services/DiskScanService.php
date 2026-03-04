<?php

declare(strict_types=1);

namespace Larasofthu\LaravelGuardian\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class DiskScanService
{
    private const PHP_OPENING_TAGS = '/<\?php|<\?=|<\\?/i';

    /**
     * Scan one or more storage disks for suspicious PHP functions, malware patterns, dangerous file extensions,
     * and WordPress/CMS-like paths. Optionally scans base_path('public') for suspicious paths.
     *
     * @param  string[]  $disks
     * @return array{suspicious_php: array, malware_patterns: array, dangerous_files: array, suspicious_paths: array}
     */
    public function scanDisks(array $disks): array
    {
        $suspiciousPhp = [];
        $malwarePatterns = [];
        $dangerousFiles = [];
        $suspiciousPaths = [];

        $suspiciousFunctions = config('file-integrity.suspicious_php_functions', []);
        $malwarePatternConfig = config('file-integrity.malware_patterns', []);
        $dangerousExtensions = array_map('strtolower', config('file-integrity.dangerous_extensions', []));
        $suspiciousPathPatterns = config('file-integrity.suspicious_path_patterns', []);
        $maxContentBytes = (int) config('file-integrity.content_scan_max_bytes', 200 * 1024);

        foreach ($disks as $diskName) {
            try {
                $disk = Storage::disk($diskName);
            } catch (\Throwable) {
                continue;
            }

            $allFiles = $this->getAllFiles($disk);

            foreach ($allFiles as $path) {
                $matchedPathPattern = $this->matchSuspiciousPath($path, $suspiciousPathPatterns);
                if ($matchedPathPattern !== null) {
                    $suspiciousPaths[] = [
                        'disk' => $diskName,
                        'file' => $path,
                        'pattern' => $matchedPathPattern,
                    ];
                }

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

        if (config('file-integrity.scan_public_path', true)) {
            foreach ($this->getPublicPathFiles() as $relativePath) {
                $matchedPathPattern = $this->matchSuspiciousPath($relativePath, $suspiciousPathPatterns);
                if ($matchedPathPattern !== null) {
                    $suspiciousPaths[] = [
                        'disk' => 'public',
                        'file' => $relativePath,
                        'pattern' => $matchedPathPattern,
                    ];
                }
            }
        }

        return [
            'suspicious_php' => $suspiciousPhp,
            'malware_patterns' => $malwarePatterns,
            'dangerous_files' => $dangerousFiles,
            'suspicious_paths' => $suspiciousPaths,
        ];
    }

    /**
     * @return string|null  Matched pattern or null
     */
    private function matchSuspiciousPath(string $path, array $patterns): ?string
    {
        $normalized = str_replace('\\', '/', strtolower($path));
        foreach ($patterns as $pattern) {
            if (str_contains($normalized, strtolower($pattern))) {
                return $pattern;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function getPublicPathFiles(): array
    {
        $publicPath = base_path('public');
        if (! is_dir($publicPath)) {
            return [];
        }

        try {
            $files = File::allFiles($publicPath);
        } catch (\Throwable) {
            return [];
        }

        $base = rtrim(str_replace('\\', '/', $publicPath), '/') . '/';
        $result = [];

        foreach ($files as $file) {
            $fullPath = str_replace('\\', '/', $file->getPathname());
            if (str_starts_with($fullPath, $base)) {
                $result[] = substr($fullPath, strlen($base));
            }
        }

        return $result;
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
