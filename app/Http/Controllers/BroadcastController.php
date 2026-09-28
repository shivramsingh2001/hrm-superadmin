<?php

namespace App\Http\Controllers;

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Services\Broadcast\BroadcastComposerService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Superadmin composer — thin controller (validate → service → respond),
 * matching FeatureTemplateController's structural weight. No `cancel`
 * action: unlike the Tenant Admin composer, this one has no scheduling, so
 * there's never a `draft`/`scheduled` broadcast to cancel — every send is
 * immediate (queued for delivery, but not cancellable once created).
 */
class BroadcastController extends Controller
{
    public function __construct(private BroadcastComposerService $composer)
    {
    }

    /**
     * Single page: history list (stat cards + filters + paginated table)
     * AND the "Send Broadcast" composer, which now lives in a slide-over
     * drawer on this same page (resources/views/broadcast/_send-drawer.blade.php)
     * instead of a separate /broadcast/create route — same UI pattern as
     * the Tenant Admin composer in hrm (3).
     */
    public function index(Request $request)
    {
        $query = Broadcast::where('origin', 'superadmin');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('audience_type')) {
            $query->where('audience_type', $request->query('audience_type'));
        }
        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $query->where('title', 'like', "%{$search}%");
        }
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->query('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->query('to_date'));
        }

        $broadcasts = $query->orderByDesc('created_at')->paginate(15)->withQueryString();

        $statsBase = Broadcast::where('origin', 'superadmin');
        $stats = [
            'total' => (clone $statsBase)->count(),
            'sent' => (clone $statsBase)->where('status', 'sent')->count(),
            'sending' => (clone $statsBase)->where('status', 'sending')->count(),
            'recipients' => (int) (clone $statsBase)->sum('total_recipients'),
        ];

        return view('broadcast.index', [
            'broadcasts' => $broadcasts,
            'stats' => $stats,
            'filters' => $request->query(),
            'statuses' => ['sending', 'sent', 'failed'],
            'audienceTypes' => \App\Services\Broadcast\SuperAdminBroadcastAudienceResolver::MODES,
            'tenants' => Tenant::orderBy('company_name')->get(['id', 'company_name']),
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->composer->validate($request, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        $broadcast = $this->composer->create($request->user(), $validated);

        AuditLogger::record('broadcast.sent', 'broadcast_notifications', $broadcast->id, null, [
            'title' => $broadcast->title,
            'audience_type' => $broadcast->audience_type,
            'total_recipients' => $broadcast->total_recipients,
        ]);

        return redirect()->route('broadcast.show', $broadcast->id)
            ->with('success', "Broadcast queued for {$broadcast->total_recipients} recipient(s).");
    }

    public function previewCount(Request $request)
    {
        try {
            $validated = $this->composer->validateAudienceOnly($request, $request->user());
        } catch (ValidationException $e) {
            return response()->json(['recipient_count' => 0]);
        }

        return response()->json(['recipient_count' => $this->composer->countRecipients($validated)]);
    }

    public function show(int $id)
    {
        $broadcast = Broadcast::where('origin', 'superadmin')->findOrFail($id);

        $stats = [
            'total' => BroadcastRecipient::where('broadcast_id', $broadcast->id)->count(),
            'delivered' => BroadcastRecipient::where('broadcast_id', $broadcast->id)->whereNotNull('delivered_at')->count(),
            'read' => BroadcastRecipient::where('broadcast_id', $broadcast->id)->whereNotNull('read_at')->count(),
        ];

        return view('broadcast.show', compact('broadcast', 'stats'));
    }
}
