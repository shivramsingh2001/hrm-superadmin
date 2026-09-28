<?php

namespace App\Services\Storage;

use App\Exceptions\FileStorageException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The ONE place the app stores, deletes and links uploaded files.
 *
 * - Where files go is decided by config/file_storage.php (`disk`, from
 *   FILE_STORAGE_DISK) — Google Cloud Storage in production, public/uploads
 *   locally. Controllers never know which.
 * - Each caller names a module ('leave', 'task_voice', ...). The module sets
 *   the folder, allowed extensions and max size.
 * - Stored name = UUID + extension derived from the file's real content; the
 *   client name is kept only as display metadata.
 * - The DB stores the relative path this returns. Paths written before this
 *   service existed (public/uploads/..., storage/app/public/...) keep working:
 *   every read checks the local legacy roots first (a cheap is_file), so the
 *   bucket is only asked for files that are really there.
 * - Cloud URLs are V4 signed URLs (the bucket is private). Private modules get
 *   `signed_url_ttl` minutes, modules flagged `public` get `public_url_ttl`.
 */
class FileStorageService
{
    /** Guessed extensions that are too generic to trust over the client's (docx→zip, m4a→mp4…). */
    private const AMBIGUOUS_GUESSES = ['', 'zip', 'bin', 'txt', 'mp4', 'mpga', 'qt', 'oga', 'ogg', 'weba', 'webm', '3gp', '3gpp', 'cdf', 'heif', 'heic', 'doc', 'xls', 'ppt', 'dat', 'm4a', 'wav'];

    private const EQUIVALENT_EXTENSIONS = [
        'jpeg' => 'jpg', 'jpe' => 'jpg', 'mpga' => 'mp3', 'oga' => 'ogg', '3gpp' => '3gp', 'heif' => 'heic',
    ];

    // ------------------------------------------------------------------
    //  Config
    // ------------------------------------------------------------------

    /** @return array{folder:string, ext:array<int,string>, max:int, public?:bool, local_disk?:string} */
    public function module(string $module): array
    {
        $cfg = config("file_storage.modules.{$module}");
        if (! is_array($cfg)) {
            throw new InvalidArgumentException("Unknown file storage module [{$module}]. Add it to config/file_storage.php.");
        }

        return $cfg;
    }

    /** Disk new uploads for $module are written to. */
    public function diskName(?string $module = null): string
    {
        $disk = (string) config('file_storage.disk', 'uploads');
        if ($this->isCloudDisk($disk)) {
            return $disk;
        }

        return $module ? ($this->module($module)['local_disk'] ?? $disk) : $disk;
    }

    public function isCloud(): bool
    {
        return $this->isCloudDisk((string) config('file_storage.disk'));
    }

    private function isCloudDisk(string $disk): bool
    {
        return in_array($disk, (array) config('file_storage.cloud_disks', ['gcs']), true);
    }

    private function disk(string $name): Filesystem
    {
        return Storage::disk($name);
    }

    /**
     * Basic validation rules (presence, file, size in KB) for a module's field, for
     * $request->validate() / FormRequests. The type check happens in upload(), which
     * uses the same content-based rules for every module.
     */
    public function rules(string $module, bool $required = false): array
    {
        $cfg = $this->module($module);

        return [
            $required ? 'required' : 'nullable',
            'file',
            'max:' . (int) $cfg['max'],
        ];
    }

    /** Human list of allowed extensions, for UI hints ("PDF, JPG, PNG"). */
    public function allowedExtensionsLabel(string $module): string
    {
        return strtoupper(implode(', ', $this->module($module)['ext']));
    }

    // ------------------------------------------------------------------
    //  Write
    // ------------------------------------------------------------------

    /**
     * Validate and store one uploaded file.
     *
     * @param  array  $context  values for the folder placeholders: tenant, user, id (year/month are automatic)
     *
     * @throws FileStorageException 422 on a bad file, 500 when the disk write fails
     */
    public function upload(UploadedFile $file, string $module, array $context = []): StoredFile
    {
        $cfg = $this->module($module);

        if (! $file->isValid()) {
            throw new FileStorageException($file->getErrorMessage() ?: 'The file failed to upload.', 422);
        }

        $maxKb = (int) $cfg['max'];
        if ($file->getSize() > $maxKb * 1024) {
            throw new FileStorageException('The file may not be larger than ' . $this->humanSize($maxKb) . '.', 422);
        }

        $extension = $this->resolveExtension($file, $cfg['ext']);
        $folder = $this->buildFolder($cfg['folder'], $context);
        $name = Str::uuid() . '.' . $extension;
        $diskName = $this->diskName($module);
        $mime = $file->getMimeType() ?: $file->getClientMimeType();

        try {
            $stored = $this->disk($diskName)->putFileAs($folder, $file, $name, [
                'metadata' => array_filter([
                    'contentType' => $mime,
                    'contentDisposition' => 'inline; filename="' . $this->asciiName($file, $extension) . '"',
                    'cacheControl' => ! empty($cfg['public']) ? 'private, max-age=86400' : 'private, max-age=0, no-store',
                ]),
            ]);
        } catch (Throwable $e) {
            Log::error('FileStorage upload failed', ['module' => $module, 'disk' => $diskName, 'folder' => $folder, 'error' => $e->getMessage()]);
            throw new FileStorageException('The file could not be saved. Please try again.', 500, $e);
        }

        if ($stored === false || $stored === '') {
            Log::error('FileStorage upload returned false', ['module' => $module, 'disk' => $diskName, 'folder' => $folder]);
            throw new FileStorageException('The file could not be saved. Please try again.', 500);
        }

        return new StoredFile(
            path: $folder . '/' . $name,
            originalName: $this->displayName($file),
            mimeType: $mime,
            size: (int) $file->getSize(),
            extension: $extension,
            disk: $diskName,
            module: $module,
        );
    }

    /**
     * Store raw bytes (e.g. a base64 voice recording from the browser) under a
     * module. The extension must be one the module allows.
     */
    public function storeContents(string $contents, string $module, string $extension, array $context = [], ?string $displayName = null): StoredFile
    {
        $cfg = $this->module($module);
        $extension = strtolower(ltrim($extension, '.'));

        if ($contents === '') {
            throw new FileStorageException('The file is empty.', 422);
        }
        if (! in_array($extension, array_map('strtolower', $cfg['ext']), true)
            || in_array($extension, (array) config('file_storage.blocked_extensions', []), true)) {
            throw new FileStorageException('File type not allowed. Allowed: ' . strtoupper(implode(', ', $cfg['ext'])) . '.', 422);
        }
        if (strlen($contents) > (int) $cfg['max'] * 1024) {
            throw new FileStorageException('The file may not be larger than ' . $this->humanSize((int) $cfg['max']) . '.', 422);
        }

        $folder = $this->buildFolder($cfg['folder'], $context);
        $path = $folder . '/' . Str::uuid() . '.' . $extension;
        $diskName = $this->diskName($module);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream';

        try {
            $ok = $this->disk($diskName)->put($path, $contents, ['metadata' => ['contentType' => $mime]]);
        } catch (Throwable $e) {
            Log::error('FileStorage storeContents failed', ['module' => $module, 'disk' => $diskName, 'error' => $e->getMessage()]);
            throw new FileStorageException('The file could not be saved. Please try again.', 500, $e);
        }
        if (! $ok) {
            throw new FileStorageException('The file could not be saved. Please try again.', 500);
        }

        return new StoredFile($path, $displayName ?? basename($path), $mime, strlen($contents), $extension, $diskName, $module);
    }

    /**
     * Store several files; if any one fails, the ones already stored are removed
     * and the exception is rethrown (all-or-nothing).
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, StoredFile>
     */
    public function uploadMany(array $files, string $module, array $context = []): array
    {
        $stored = [];
        try {
            foreach ($files as $file) {
                if ($file instanceof UploadedFile) {
                    $stored[] = $this->upload($file, $module, $context);
                }
            }
        } catch (Throwable $e) {
            foreach ($stored as $s) {
                $this->delete($s->path, $module);
            }
            throw $e;
        }

        return $stored;
    }

    /**
     * Upload the new file FIRST, then remove the old one — a failed upload never
     * loses the existing file. Inside a DB transaction the old file is removed only
     * after commit.
     */
    public function replace(?string $oldPath, UploadedFile $file, string $module, array $context = []): StoredFile
    {
        $new = $this->upload($file, $module, $context);
        $this->deleteAfterCommit($oldPath, $module);

        return $new;
    }

    /**
     * Remove a stored file wherever it lives (legacy local copy and/or the active
     * disk). Null/empty and missing files are fine. Never throws — a failed delete
     * is logged and reported as false, so it can't break the caller's flow.
     */
    public function delete(?string $path, ?string $module = null): bool
    {
        $path = $this->normalise($path);
        if ($path === null || $this->isExternalUrl($path)) {
            return false;
        }

        $deleted = false;

        if ($full = $this->legacyFullPath($path)) {
            $deleted = @unlink($full) || $deleted;
        }

        foreach ($this->candidateDisks($module) as $diskName) {
            if (! $this->diskAccepts($diskName, $path)) {
                continue;
            }
            try {
                $disk = $this->disk($diskName);
                if ($disk->exists($path)) {
                    $deleted = $disk->delete($path) || $deleted;
                }
            } catch (Throwable $e) {
                Log::warning('FileStorage delete failed', ['disk' => $diskName, 'path' => $path, 'error' => $e->getMessage()]);
            }
        }

        return $deleted;
    }

    /** delete() once the surrounding DB transaction commits (immediately when there is none). */
    public function deleteAfterCommit(?string $path, ?string $module = null): void
    {
        if (! $this->normalise($path)) {
            return;
        }

        DB::afterCommit(fn () => $this->delete($path, $module));
    }

    // ------------------------------------------------------------------
    //  Read
    // ------------------------------------------------------------------

    /**
     * URL a browser / the mobile app can load. Returns full URLs unchanged, local
     * files as plain asset() URLs, and cloud objects as signed URLs.
     *
     * @param  int|null  $ttlMinutes  override the module's lifetime
     */
    public function url(?string $path, ?string $module = null, ?int $ttlMinutes = null): ?string
    {
        $path = $this->normalise($path);
        if ($path === null) {
            return null;
        }
        if ($this->isExternalUrl($path)) {
            return $path;
        }

        if ($legacy = $this->legacyUrl($path)) {
            return $legacy;
        }

        $diskName = $this->diskName($module);

        if (! $this->isCloudDisk($diskName)) {
            // Local fallback: public/uploads files are plain URLs; a disk with a
            // configured `url` (e.g. `public`) uses it. Private local disks (expense)
            // have no public URL — their owners use signed routes.
            if ($diskName === 'uploads') {
                return asset($path);
            }

            return config("filesystems.disks.{$diskName}.url") ? $this->disk($diskName)->url($path) : null;
        }

        $ttl = $ttlMinutes ?? $this->ttlFor($module);

        try {
            return $this->disk($diskName)->temporaryUrl($path, now()->addMinutes($ttl), ['version' => 'v4']);
        } catch (Throwable $e) {
            Log::warning('FileStorage signed URL failed', ['disk' => $diskName, 'path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Turn stored paths into URLs in place on query results — the replacement for
     * `CONCAT('$baseUrl/', col) as x_url` in SQL. Select the raw column under the
     * output name, then: mapUrls($rows, ['file_url' => 'leave']).
     *
     * @param  iterable|object  $rows      Collection / paginator / array of models, stdClass or arrays
     * @param  array<string, string|array{0:?string,1:?string}>  $fields  attribute => module, or attribute => [module, fallbackUrl]
     */
    public function mapUrls($rows, array $fields)
    {
        $apply = function (&$row) use ($fields) {
            foreach ($fields as $attr => $spec) {
                [$module, $fallback] = is_array($spec) ? [$spec[0] ?? null, $spec[1] ?? null] : [$spec, null];
                if (is_array($row)) {
                    if (array_key_exists($attr, $row)) {
                        $row[$attr] = $this->url($row[$attr], $module) ?? $fallback;
                    }
                } elseif (is_object($row)) {
                    $row->{$attr} = $this->url($row->{$attr} ?? null, $module) ?? $fallback;
                }
            }
        };

        if ($rows instanceof \Illuminate\Contracts\Pagination\Paginator) {
            $rows->getCollection()->transform(function ($r) use ($apply) { $apply($r); return $r; });
        } elseif ($rows instanceof \Illuminate\Support\Collection) {
            $rows->transform(function ($r) use ($apply) { $apply($r); return $r; });
        } elseif (is_array($rows)) {
            foreach ($rows as &$r) {
                $apply($r);
            }
            unset($r);
        } elseif (is_object($rows)) {
            $apply($rows);
        }

        return $rows;
    }

    public function exists(?string $path, ?string $module = null): bool
    {
        $path = $this->normalise($path);
        if ($path === null || $this->isExternalUrl($path)) {
            return false;
        }
        if ($this->legacyFullPath($path)) {
            return true;
        }

        $diskName = $this->diskName($module);
        try {
            return $this->diskAccepts($diskName, $path) && $this->disk($diskName)->exists($path);
        } catch (Throwable $e) {
            Log::warning('FileStorage exists failed', ['disk' => $diskName, 'path' => $path, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Raw file contents, or null when missing. */
    public function contents(?string $path, ?string $module = null): ?string
    {
        $path = $this->normalise($path);
        if ($path === null || $this->isExternalUrl($path)) {
            return null;
        }
        if ($full = $this->legacyFullPath($path)) {
            return (string) file_get_contents($full);
        }

        $diskName = $this->diskName($module);
        try {
            if (! $this->diskAccepts($diskName, $path) || ! $this->disk($diskName)->exists($path)) {
                return null;
            }

            return $this->disk($diskName)->get($path);
        } catch (Throwable $e) {
            Log::warning('FileStorage read failed', ['disk' => $diskName, 'path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Stream the file through the app (for routes that check permissions first).
     * 404s when the file does not exist.
     */
    public function stream(?string $path, ?string $module = null, ?string $downloadName = null, bool $attachment = false): Response
    {
        $path = $this->normalise($path);
        abort_if($path === null || $this->isExternalUrl($path), 404);

        $headers = ['X-Content-Type-Options' => 'nosniff'];
        $disposition = $attachment ? 'attachment' : 'inline';

        if ($full = $this->legacyFullPath($path)) {
            $response = response()->file($full, $headers);
            $response->setContentDisposition($disposition, $downloadName ?: basename($full), Str::ascii($downloadName ?: basename($full)));

            return $response;
        }

        $diskName = $this->diskName($module);
        abort_unless($this->diskAccepts($diskName, $path), 404);

        try {
            abort_unless($this->disk($diskName)->exists($path), 404);

            return $this->disk($diskName)->response($path, $downloadName, $headers, $disposition);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('FileStorage stream failed', ['disk' => $diskName, 'path' => $path, 'error' => $e->getMessage()]);
            abort(404);
        }
    }

    /** `data:` URI, for PDFs rendered by dompdf (which can't fetch signed cloud URLs). */
    public function dataUri(?string $path, ?string $module = null): ?string
    {
        $contents = $this->contents($path, $module);
        if ($contents === null || $contents === '') {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream';

        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    /** Client-supplied file name kept ONLY for display: no path/control chars, length-capped. */
    public function displayName(UploadedFile $file, string $fallback = 'file'): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', basename((string) $file->getClientOriginalName())) ?? '';

        return mb_substr(trim($name) !== '' ? $name : $fallback, 0, 200);
    }

    // ------------------------------------------------------------------
    //  Internals
    // ------------------------------------------------------------------

    private function ttlFor(?string $module): int
    {
        $public = $module && ! empty($this->module($module)['public']);

        return max(1, (int) config($public ? 'file_storage.public_url_ttl' : 'file_storage.signed_url_ttl', 60));
    }

    /**
     * Extension from the file's real content. When the content guess is generic
     * (docx detected as zip, m4a as mp4, unknown), the client's extension is used
     * if the module allows it. Blocked extensions are never accepted.
     */
    private function resolveExtension(UploadedFile $file, array $allowed): string
    {
        $allowed = array_map('strtolower', $allowed);
        $blocked = array_map('strtolower', (array) config('file_storage.blocked_extensions', []));
        $allows = function (string $ext) use ($allowed, $blocked): bool {
            if ($ext === '' || in_array($ext, $blocked, true)) {
                return false;
            }
            if (in_array($ext, $allowed, true)) {
                return true;
            }
            $canonical = self::EQUIVALENT_EXTENSIONS[$ext] ?? $ext;
            foreach ($allowed as $a) {
                if ((self::EQUIVALENT_EXTENSIONS[$a] ?? $a) === $canonical) {
                    return true;
                }
            }

            return false;
        };

        $guessed = strtolower((string) $file->guessExtension());
        $client = strtolower((string) $file->getClientOriginalExtension());

        // The real content decides: a genuine PNG named "x.html" is stored as .png.
        // The client's extension is only ever used when the content guess is
        // generic, and never when it is a blocked (executable/markup) type.
        // Keep the client's spelling when it names the same type as the content (jpeg vs jpg).
        if ($allows($guessed)) {
            $sameType = $client !== '' && (self::EQUIVALENT_EXTENSIONS[$client] ?? $client) === (self::EQUIVALENT_EXTENSIONS[$guessed] ?? $guessed);

            return $sameType ? $client : $guessed;
        }

        if ($allows($client) && in_array($guessed, self::AMBIGUOUS_GUESSES, true)) {
            return $client;
        }

        throw new FileStorageException('File type not allowed. Allowed: ' . strtoupper(implode(', ', $allowed)) . '.', 422);
    }

    private function buildFolder(string $pattern, array $context): string
    {
        $values = [
            'user' => $context['user'] ?? null,
            'id' => $context['id'] ?? null,
            'year' => $context['year'] ?? date('Y'),
            'month' => $context['month'] ?? date('m'),
        ];

        $folder = preg_replace_callback('/\{(\w+)\}/', function ($m) use ($values, $pattern, $context) {
            $v = $m[1] === 'tenant'
                ? ($context['tenant'] ?? $this->currentTenantId())
                : ($values[$m[1]] ?? null);
            if ($v === null || $v === '') {
                throw new InvalidArgumentException("File storage folder [{$pattern}] needs a '{$m[1]}' context value.");
            }

            // Only safe path segment characters.
            return preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $v);
        }, $pattern);

        return trim((string) $folder, '/');
    }

    private function currentTenantId(): ?int
    {
        if (app()->bound('current_tenant') && app('current_tenant')) {
            return (int) app('current_tenant')->id;
        }

        $user = auth()->user();
        if (! $user && config('auth.guards.api')) {
            try {
                $user = auth('api')->user();
            } catch (Throwable) {
                $user = null;
            }
        }

        return $user?->tenant_id ? (int) $user->tenant_id : 0;
    }

    /** Trim, drop leading slash; reject traversal. */
    private function normalise(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }
        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') {
            return null;
        }
        if ($this->isExternalUrl($path)) {
            return $path;
        }
        $path = ltrim($path, '/');
        if (str_contains('/' . $path . '/', '/../') || str_contains($path, "\0")) {
            return null;
        }

        return $path;
    }

    private function isExternalUrl(string $path): bool
    {
        return (bool) preg_match('#^(https?:)?//#i', $path) || str_starts_with($path, 'data:');
    }

    /** Disks a delete should look at: the module's local disk and the active disk. */
    private function candidateDisks(?string $module): array
    {
        $disks = [$this->diskName($module)];
        $local = $module ? ($this->module($module)['local_disk'] ?? config('file_storage.local_disk')) : config('file_storage.local_disk');
        $disks[] = $local;

        return array_values(array_unique(array_filter($disks)));
    }

    /** The `uploads` disk is rooted at public/ — never touch anything outside uploads/ there. */
    private function diskAccepts(string $diskName, string $path): bool
    {
        return $diskName !== 'uploads' || str_starts_with($path, 'uploads/');
    }

    /** Real path of a legacy/local copy, confined to its root and allowed prefixes. */
    private function legacyFullPath(string $path): ?string
    {
        foreach ((array) config('file_storage.legacy_roots', []) as $root) {
            if (! $this->matchesPrefix($path, $root['prefixes'] ?? [])) {
                continue;
            }
            $base = realpath($root['root']);
            $full = $base ? realpath($base . DIRECTORY_SEPARATOR . $path) : false;
            if ($base && $full && is_file($full) && str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
                return $full;
            }
        }

        return null;
    }

    private function legacyUrl(string $path): ?string
    {
        foreach ((array) config('file_storage.legacy_roots', []) as $root) {
            if (! array_key_exists('url', $root) || $root['url'] === null || ! $this->matchesPrefix($path, $root['prefixes'] ?? [])) {
                continue;
            }
            if (is_file(rtrim($root['root'], '/\\') . DIRECTORY_SEPARATOR . $path)) {
                return asset(($root['url'] ?? '') . $path);
            }
        }

        return null;
    }

    private function matchesPrefix(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $p) {
            if (str_starts_with($path, $p)) {
                return true;
            }
        }

        return $prefixes === [];
    }

    private function asciiName(UploadedFile $file, string $extension): string
    {
        $base = pathinfo($this->displayName($file), PATHINFO_FILENAME);
        $ascii = preg_replace('/[^A-Za-z0-9._\- ]/', '_', Str::ascii($base)) ?: 'file';

        return mb_substr($ascii, 0, 100) . '.' . $extension;
    }

    private function humanSize(int $kb): string
    {
        return $kb >= 1024 ? rtrim(rtrim(number_format($kb / 1024, 1), '0'), '.') . ' MB' : $kb . ' KB';
    }
}
