<?php

namespace App\Http\Controllers;

use App\Models\FeatureOverrideTemplate;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;
use App\Services\AuditLogger;
use App\Services\FeatureService;
use Illuminate\Http\Request;

/**
 * Bulk feature-override templates — a named map of feature_key => bool that can
 * be applied to many tenants in one audited operation.
 */
class FeatureTemplateController extends Controller
{
    public function __construct(private FeatureService $features)
    {
    }

    public function index()
    {
        return view('feature-templates.index', [
            'templates' => FeatureOverrideTemplate::orderBy('name')->paginate(10),
            'features' => config('features'),
            'plans' => SubscriptionPlan::orderBy('name')->pluck('name', 'id'),
            'tenants' => Tenant::orderBy('company_name')->get(['id', 'company_name', 'subscription_plan_id']),
        ]);
    }

    /** Bare partial (no layout) when opened in the side drawer, full page otherwise. */
    public function create(Request $request)
    {
        return view($request->boolean('drawer') ? 'feature-templates._form' : 'feature-templates.form', [
            'features' => config('features'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $map = [];
        foreach (array_keys(config('features')) as $key) {
            $v = $request->input("map.{$key}");
            if ($v === 'on' || $v === 'off') {
                $map[$key] = $v === 'on';
            }
        }
        if (! $map) {
            return back()->with('error', 'Select at least one feature to force on or off.');
        }

        $t = FeatureOverrideTemplate::create($data + ['map' => $map, 'created_by' => $request->user()->id]);
        AuditLogger::record('feature_template.created', 'sa_feature_override_templates', $t->id, null, $t->toArray());

        return back()->with('success', "Template '{$t->name}' saved.");
    }

    public function destroy(FeatureOverrideTemplate $template)
    {
        AuditLogger::record('feature_template.deleted', 'sa_feature_override_templates', $template->id, $template->toArray());
        $template->delete();

        return back()->with('success', 'Template deleted.');
    }

    /** Bare partial (no layout) for the side-drawer "apply to tenants" form. */
    public function applyForm(FeatureOverrideTemplate $template)
    {
        return view('feature-templates._apply', [
            'template' => $template,
            'plans' => SubscriptionPlan::orderBy('name')->pluck('name', 'id'),
            'tenants' => Tenant::orderBy('company_name')->get(['id', 'company_name', 'subscription_plan_id']),
        ]);
    }

    /** Apply a template to a set of tenants (explicit ids and/or a whole plan). */
    public function apply(Request $request, FeatureOverrideTemplate $template)
    {
        $data = $request->validate([
            'tenant_ids' => ['array'],
            'tenant_ids.*' => ['integer'],
            'plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $ids = collect($data['tenant_ids'] ?? []);
        if (! empty($data['plan_id'])) {
            $ids = $ids->merge(Tenant::where('subscription_plan_id', $data['plan_id'])->pluck('id'));
        }
        $ids = $ids->unique()->values();

        if ($ids->isEmpty()) {
            return back()->with('error', 'No tenants selected.');
        }

        $reason = $data['reason'] ?: "Template: {$template->name}";
        $applied = 0;
        foreach (Tenant::whereIn('id', $ids)->get() as $tenant) {
            foreach ($template->map as $key => $enabled) {
                TenantFeatureOverride::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'feature_key' => $key],
                    ['is_enabled' => (bool) $enabled, 'reason' => $reason, 'overridden_by' => $request->user()->id],
                );
            }
            $this->features->bust($tenant->id);
            $applied++;
        }

        AuditLogger::record('feature_template.applied', 'sa_feature_override_templates', $template->id, null, [
            'template' => $template->name,
            'tenant_ids' => $ids->all(),
            'keys' => array_keys($template->map),
        ]);

        return back()->with('success', "Applied '{$template->name}' to {$applied} tenant(s).");
    }
}
