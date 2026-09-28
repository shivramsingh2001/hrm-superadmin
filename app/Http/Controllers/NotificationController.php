<?php

namespace App\Http\Controllers;

use App\Models\BroadcastRecipient;
use App\Models\SuperAdminNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = SuperAdminNotification::visibleTo($request->user()->id)
            ->orderByDesc('created_at')->paginate(30);

        // Broadcast Notification module: a `superadmin_users`/`all_eligible`
        // broadcast targeting this admin lands here too (recipient_type=
        // super_admin rows only ever get their own broadcast_recipients
        // snapshot — no dual-write into super_admin_notifications, per the
        // module's "one engine" design). Separate paginator page name
        // ('bpage') so it doesn't collide with $notifications' own 'page'.
        $broadcastNotifications = BroadcastRecipient::where('recipient_type', 'super_admin')
            ->where('super_admin_id', $request->user()->id)
            ->with('broadcast')
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'bpage');

        return view('notifications.index', compact('notifications', 'broadcastNotifications'));
    }

    public function markRead(Request $request, SuperAdminNotification $notification)
    {
        if ($notification->super_admin_id && $notification->super_admin_id !== $request->user()->id) {
            abort(403);
        }
        $notification->update(['read_at' => now()]);

        return back();
    }

    public function markBroadcastRead(Request $request, BroadcastRecipient $recipient)
    {
        if ($recipient->recipient_type !== 'super_admin' || $recipient->super_admin_id !== $request->user()->id) {
            abort(403);
        }
        $recipient->update(['read_at' => now()]);

        return back();
    }
}
