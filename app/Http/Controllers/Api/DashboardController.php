<?php

namespace App\Http\Controllers\Api;

use App\Models\Inquiry;
use App\Models\PlatformMetric;
use App\Models\Tenant;
use App\Models\TenantHealth;

class DashboardController extends ApiController
{
    public function index()
    {
        $metric = PlatformMetric::orderByDesc('metric_date')->first();

        return $this->ok([
            'metric' => $metric,
            'recent_tenants' => Tenant::orderByDesc('created_at')->limit(8)->get(['id', 'company_name', 'subdomain', 'status', 'created_at']),
            'open_enquiries' => Inquiry::whereIn('status', Inquiry::OPEN_STATUSES)->orderByDesc('created_at')->limit(8)->get(),
            'at_risk' => TenantHealth::where('is_stale', true)->limit(10)->get(),
        ]);
    }
}
