<?php

declare(strict_types=1);

namespace Larasofthu\LaravelGuardian\Services;

class FileMetadataService
{
    /**
     * Get file metadata (creation time, modification time, owner, group) for a given path.
     * Returns null if file does not exist or metadata cannot be read.
     *
     * @return array{created_at: string|null, modified_at: string|null, owner: string|null, group: string|null}|null
     */
    public function getMetadata(string $fullPath): ?array
    {
        $fullPath = $this->normalizePath($fullPath);
        if (! is_file($fullPath)) {
            return null;
        }

        clearstatcache(true, $fullPath);

        $createdAt = null;
        $modifiedAt = null;
        $owner = null;
        $group = null;

        $ctime = @filectime($fullPath);
        if ($ctime !== false) {
            $createdAt = date('Y-m-d H:i:s', $ctime);
        }

        $mtime = @filemtime($fullPath);
        if ($mtime !== false) {
            $modifiedAt = date('Y-m-d H:i:s', $mtime);
        }

        if (function_exists('fileowner')) {
            $uid = @fileowner($fullPath);
            if ($uid !== false) {
                $owner = $this->resolveOwner($uid);
            }
        }

        if (function_exists('filegroup')) {
            $gid = @filegroup($fullPath);
            if ($gid !== false) {
                $group = $this->resolveGroup($gid);
            }
        }

        return [
            'created_at' => $createdAt,
            'modified_at' => $modifiedAt,
            'owner' => $owner,
            'group' => $group,
        ];
    }

    private function normalizePath(string $path): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    private function resolveOwner(int $uid): string
    {
        if (function_exists('posix_getpwuid')) {
            $info = @posix_getpwuid($uid);
            if ($info !== false && isset($info['name'])) {
                return $info['name'] . ' (' . $uid . ')';
            }
        }

        return (string) $uid;
    }

    private function resolveGroup(int $gid): string
    {
        if (function_exists('posix_getgrgid')) {
            $info = @posix_getgrgid($gid);
            if ($info !== false && isset($info['name'])) {
                return $info['name'] . ' (' . $gid . ')';
            }
        }

        return (string) $gid;
    }
}
