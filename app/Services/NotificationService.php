<?php

namespace App\Services;

use App\Models\SuperAdminNotification;

/**
 * Fan-out helper for super_admin_notifications. super_admin_id = null → visible
 * to every super admin (the panel's header badge and /notifications use
 * SuperAdminNotification::visibleTo()).
 */
class NotificationService
{
    public static function broadcast(string $type, string $title, string $body, array $data = []): void
    {
        SuperAdminNotification::create([
            'super_admin_id' => null,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }
}
