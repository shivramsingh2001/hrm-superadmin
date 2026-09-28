<?php

use App\Services\Storage\FileStorageService;

if (! function_exists('file_storage')) {
    /** The shared FileStorageService instance. */
    function file_storage(): FileStorageService
    {
        return app(FileStorageService::class);
    }
}

if (! function_exists('file_url')) {
    /**
     * Loadable URL for a stored file path (DB value) — replaces asset($model->file).
     * Local/legacy files → asset() URL; cloud files → signed URL; null-safe.
     *
     * @param  string|null  $module  config/file_storage.php module key (sets the URL lifetime)
     */
    function file_url(?string $path, ?string $module = null, ?int $ttlMinutes = null): ?string
    {
        return app(FileStorageService::class)->url($path, $module, $ttlMinutes);
    }
}
