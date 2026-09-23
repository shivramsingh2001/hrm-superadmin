<?php

namespace App\Http\Controllers;

use App\Models\SuperAdminNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = SuperAdminNotification::visibleTo($request->user()->id)
            ->orderByDesc('created_at')->paginate(30);

        return view('notifications.index', compact('notifications'));
    }

    public function markRead(Request $request, SuperAdminNotification $notification)
    {
        if ($notification->super_admin_id && $notification->super_admin_id !== $request->user()->id) {
            abort(403);
        }
        $notification->update(['read_at' => now()]);

        return back();
    }
}
