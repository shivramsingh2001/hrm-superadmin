<?php

namespace App\Http\Controllers;

use App\Models\BroadcastRecipient;
use App\Models\SuperAdminNotification;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Header bell + "View all" page, same behaviour as the HRM app's bell: one list of
 * everything addressed to this super admin — system notifications
 * (super_admin_notifications: new enquiry, tenant provisioned / suspended,
 * payment, security…) and broadcasts (broadcast_recipients, recipient_type
 * super_admin) — newest first, each linking to the tenant / enquiry it is about.
 *
 * Item ids are prefixed by source: "n-12" (notification) / "b-5" (broadcast).
 */
class NotificationController extends Controller
{
    /** Older rows are not merged into the list (keeps the in-memory merge cheap). */
    private const MERGE_LIMIT = 500;

    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['unread', 'read'], true) ? $request->query('status') : null;

        $items = $this->items($request->user()->id)
            ->when($status === 'unread', fn ($c) => $c->where('is_read', false))
            ->when($status === 'read', fn ($c) => $c->where('is_read', true))
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $notifications = new LengthAwarePaginator(
            $items->forPage($page, 30)->values(), $items->count(), 30, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('notifications.index', [
            'notifications' => $notifications,
            'status' => $status,
            'unreadCount' => $this->unreadCount($request->user()->id),
        ]);
    }

    /** Bell dropdown: latest items + unread count (JSON). */
    public function latest(Request $request)
    {
        $limit = min(20, max(1, (int) $request->query('limit', 10)));

        return response()->json([
            'data' => $this->items($request->user()->id, $limit)->take($limit)->values()->map(fn ($i) => array_merge($i, [
                'created_at' => $i['created_at']?->toIso8601String(),
            ])),
            'unread_count' => $this->unreadCount($request->user()->id),
        ]);
    }

    public function unread(Request $request)
    {
        return response()->json(['unread_count' => $this->unreadCount($request->user()->id)]);
    }

    public function markRead(Request $request, SuperAdminNotification $notification)
    {
        if ($notification->super_admin_id && $notification->super_admin_id !== $request->user()->id) {
            abort(403);
        }
        $notification->update(['read_at' => now()]);

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function markBroadcastRead(Request $request, BroadcastRecipient $recipient)
    {
        if ($recipient->recipient_type !== 'super_admin' || $recipient->super_admin_id !== $request->user()->id) {
            abort(403);
        }
        $recipient->update(['read_at' => now()]);

        return $request->expectsJson() ? response()->json(['ok' => true]) : back();
    }

    public function readAll(Request $request)
    {
        $id = $request->user()->id;
        SuperAdminNotification::visibleTo($id)->whereNull('read_at')->update(['read_at' => now()]);
        BroadcastRecipient::where('recipient_type', 'super_admin')->where('super_admin_id', $id)
            ->whereNull('read_at')->update(['read_at' => now()]);

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('success', 'All notifications marked as read.');
    }

    private function unreadCount(int $adminId): int
    {
        return SuperAdminNotification::visibleTo($adminId)->whereNull('read_at')->count()
            + BroadcastRecipient::where('recipient_type', 'super_admin')->where('super_admin_id', $adminId)->whereNull('read_at')->count();
    }

    /** Both sources as one newest-first list of display-ready arrays. */
    private function items(int $adminId, int $limit = self::MERGE_LIMIT): Collection
    {
        $notes = SuperAdminNotification::visibleTo($adminId)->orderByDesc('created_at')->limit($limit)->get()
            ->map(fn ($n) => [
                'id' => 'n-' . $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'type' => str_replace('_', ' ', (string) $n->type),
                'icon' => $this->icon((string) $n->type),
                'url' => $this->urlFor($n->data ?? []),
                'is_read' => $n->read_at !== null,
                'read_url' => route('notifications.read', $n),
                'created_at' => $n->created_at,
                'when' => $n->created_at?->diffForHumans(),
            ]);

        $broadcasts = BroadcastRecipient::where('recipient_type', 'super_admin')->where('super_admin_id', $adminId)
            ->with('broadcast')->orderByDesc('created_at')->limit($limit)->get()
            ->map(fn ($r) => [
                'id' => 'b-' . $r->id,
                'title' => $r->broadcast->title ?? 'Notification',
                'body' => $r->broadcast->body ?? '',
                'type' => 'broadcast',
                'icon' => 'bi-megaphone',
                'url' => $r->broadcast->action_url ?? null,
                'is_read' => $r->read_at !== null,
                'read_url' => route('notifications.broadcast-read', $r),
                'created_at' => $r->created_at,
                'when' => $r->created_at?->diffForHumans(),
            ]);

        return $notes->concat($broadcasts)->sortByDesc(fn ($i) => $i['created_at']?->getTimestamp() ?? 0)->values();
    }

    /** Where a notification should take you, from its data payload. */
    private function urlFor(array $data): ?string
    {
        return match (true) {
            ! empty($data['inquiry_id']) => route('enquiries.show', $data['inquiry_id']),
            ! empty($data['tenant_id']) => route('tenants.show', $data['tenant_id']),
            ! empty($data['url']) => $data['url'],
            default => null,
        };
    }

    private function icon(string $type): string
    {
        return match (true) {
            str_contains($type, 'enquiry') => 'bi-inbox',
            str_contains($type, 'payment') => 'bi-currency-rupee',
            str_contains($type, 'security') => 'bi-shield-exclamation',
            str_contains($type, 'suspend'), str_contains($type, 'deletion') => 'bi-building-exclamation',
            str_contains($type, 'tenant') => 'bi-building-check',
            default => 'bi-bell',
        };
    }
}
