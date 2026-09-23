<?php

namespace App\Http\Controllers;

use App\Models\TenantHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /** Per-tenant health table (auth). */
    public function index(Request $request)
    {
        $sort = $request->input('sort', 'stale');
        $rows = TenantHealth::with('tenant')
            ->when($sort === 'stale', fn ($q) => $q->orderByDesc('is_stale')->orderBy('last_activity_at'))
            ->when($sort === 'utilisation', fn ($q) => $q->orderByDesc('seat_utilisation'))
            ->when($sort === 'logins', fn ($q) => $q->orderByDesc('logins_30d'))
            ->paginate(25)->withQueryString();

        return view('health.index', compact('rows', 'sort'));
    }

    /** Ops probe (public, no auth). */
    public function check()
    {
        $db = $cache = false;
        try {
            DB::select('select 1');
            $db = true;
        } catch (\Throwable $e) {
        }
        try {
            Cache::put('sa:health', 1, 10);
            $cache = Cache::get('sa:health') === 1;
        } catch (\Throwable $e) {
        }

        $ok = $db && $cache;

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'db' => $db ? 'ok' : 'down',
            'cache' => $cache ? 'ok' : 'down',
            'time' => now()->toIso8601String(),
        ], $ok ? 200 : 503);
    }
}
