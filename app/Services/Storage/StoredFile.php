<?php

namespace App\Services\Storage;

/**
 * Result of FileStorageService::upload(). `path` is what gets saved in the DB
 * column (relative object key, e.g. "uploads/leave/12/<uuid>.pdf").
 */
final class StoredFile
{
    public function __construct(
        public readonly string $path,
        public readonly string $originalName,
        public readonly ?string $mimeType,
        public readonly int $size,
        public readonly string $extension,
        public readonly string $disk,
        public readonly string $module,
    ) {
    }

    public function __toString(): string
    {
        return $this->path;
    }

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'original_name' => $this->originalName,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'extension' => $this->extension,
            'disk' => $this->disk,
            'module' => $this->module,
        ];
    }
}
