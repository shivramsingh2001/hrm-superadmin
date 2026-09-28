<?php

namespace App\Console\Commands;

use App\Services\Storage\FileStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Copy files written before cloud storage (public/uploads, storage/app/public,
 * storage/app/private/expense) into the cloud disk UNDER THE SAME RELATIVE KEY,
 * so the paths already saved in the database keep working with no DB update.
 *
 * Safe to re-run: objects already in the bucket with the same size are skipped.
 * Local files are only removed with --delete-local, and only after the upload
 * is verified by size. Until then FileStorageService keeps serving the local
 * copy (it checks local disk first), so running this is never a cut-over risk.
 */
class MigrateFilesToCloud extends Command
{
    protected $signature = 'files:migrate-to-cloud
        {--dry-run : List what would be copied, change nothing}
        {--prefix= : Only paths starting with this, e.g. uploads/task}
        {--disk= : Target disk (default: FILE_STORAGE_DISK)}
        {--delete-local : Delete each local file after its upload is verified}
        {--force : Re-upload even when the object already exists}';

    protected $description = 'Copy legacy local uploads to the cloud storage disk under the same paths';

    public function handle(FileStorageService $files): int
    {
        $diskName = $this->option('disk') ?: (string) config('file_storage.disk');
        if (! in_array($diskName, (array) config('file_storage.cloud_disks', []), true)) {
            $this->error("Target disk [{$diskName}] is not a cloud disk. Set FILE_STORAGE_DISK=gcs (and the GOOGLE_CLOUD_* vars) or pass --disk=gcs.");

            return self::FAILURE;
        }

        $disk = Storage::disk($diskName);
        $dry = (bool) $this->option('dry-run');
        $prefix = ltrim((string) $this->option('prefix'), '/');
        $stats = ['copied' => 0, 'skipped' => 0, 'failed' => 0, 'deleted' => 0, 'bytes' => 0];

        try {
            $disk->exists('__connectivity_check__');
        } catch (Throwable $e) {
            $this->error('Cannot reach the bucket: ' . $e->getMessage());

            return self::FAILURE;
        }

        foreach ((array) config('file_storage.legacy_roots', []) as $root) {
            $base = realpath($root['root']);
            if (! $base) {
                continue;
            }

            foreach ((array) ($root['prefixes'] ?? []) as $rootPrefix) {
                $dir = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, rtrim($rootPrefix, '/'));
                if (! is_dir($dir)) {
                    continue;
                }

                foreach (Finder::create()->files()->in($dir)->ignoreDotFiles(true) as $file) {
                    $key = str_replace('\\', '/', substr($file->getRealPath(), strlen($base) + 1));
                    if ($prefix !== '' && ! str_starts_with($key, $prefix)) {
                        continue;
                    }

                    $size = $file->getSize();

                    try {
                        if (! $this->option('force') && $disk->exists($key) && $disk->size($key) === $size) {
                            $stats['skipped']++;
                            $this->deleteLocal($file->getRealPath(), $key, $dry, $stats);
                            continue;
                        }

                        if ($dry) {
                            $this->line("would copy  {$key}  (" . number_format($size) . ' B)');
                            $stats['copied']++;
                            $stats['bytes'] += $size;
                            continue;
                        }

                        $stream = fopen($file->getRealPath(), 'rb');
                        $mime = mime_content_type($file->getRealPath()) ?: 'application/octet-stream';
                        $disk->writeStream($key, $stream, ['metadata' => ['contentType' => $mime]]);
                        if (is_resource($stream)) {
                            fclose($stream);
                        }

                        if ($disk->size($key) !== $size) {
                            throw new \RuntimeException('size mismatch after upload');
                        }

                        $stats['copied']++;
                        $stats['bytes'] += $size;
                        $this->line("copied  {$key}");
                        $this->deleteLocal($file->getRealPath(), $key, $dry, $stats);
                    } catch (Throwable $e) {
                        $stats['failed']++;
                        $this->warn("FAILED  {$key}: " . $e->getMessage());
                    }
                }
            }
        }

        $this->newLine();
        $this->table(
            ['Copied', 'Already in bucket', 'Failed', 'Local deleted', 'Size copied'],
            [[$stats['copied'], $stats['skipped'], $stats['failed'], $stats['deleted'], number_format($stats['bytes'] / 1048576, 1) . ' MB']]
        );
        if ($dry) {
            $this->info('Dry run — nothing was changed.');
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function deleteLocal(string $fullPath, string $key, bool $dry, array &$stats): void
    {
        if (! $this->option('delete-local') || $dry) {
            return;
        }
        if (@unlink($fullPath)) {
            $stats['deleted']++;
        } else {
            $this->warn("could not delete local {$key}");
        }
    }
}
