<?php

/*
|--------------------------------------------------------------------------
| File storage — shared with the HRM app (D:\HRMNEW\hrm (3)\config\file_storage.php)
|--------------------------------------------------------------------------
|
| Both apps use the same Google Cloud Storage bucket (same GOOGLE_CLOUD_* values
| in each .env), so a logo uploaded here is readable by the HRM app. Only the
| module this panel writes is listed. Changing project / bucket / key = edit .env
| and run `php artisan config:clear`.
|
*/

return [

    // 'gcs' = Google Cloud Storage, 'public' = local storage/app/public (default until GCS is set up)
    'disk' => env('FILE_STORAGE_DISK', 'public'),

    'cloud_disks' => ['gcs'],

    'local_disk' => 'public',

    'signed_url_ttl' => (int) env('FILE_STORAGE_SIGNED_URL_TTL', 60),

    'public_url_ttl' => min((int) env('FILE_STORAGE_PUBLIC_URL_TTL', 10080), 10080),

    'blocked_extensions' => [
        'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar', 'pht', 'html', 'htm', 'shtml', 'xhtml',
        'js', 'mjs', 'svg', 'svgz', 'xml', 'exe', 'msi', 'bat', 'cmd', 'com', 'sh', 'bash', 'ps1',
        'vbs', 'jar', 'cgi', 'pl', 'py', 'rb', 'asp', 'aspx', 'jsp', 'htaccess', 'dll', 'scr',
    ],

    'modules' => [
        // Same key/folder as in the HRM app's config — keep them identical.
        'tenant_logo' => [
            'folder' => 'uploads/tenants/logos',
            'ext' => ['jpg', 'jpeg', 'png', 'webp'],
            'max' => 2048,
            'public' => true,
        ],
    ],

    // Logos stored before cloud storage: storage/app/public/tenant-logos/... (served at /storage/...).
    'legacy_roots' => [
        ['root' => storage_path('app/public'), 'url' => 'storage/', 'prefixes' => ['tenant-logos/', 'uploads/']],
    ],
];
