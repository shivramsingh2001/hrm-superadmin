<?php

namespace App\Services\Broadcast;

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Superadmin composer orchestration — the cross-tenant counterpart of
 * hrm (3)'s App\Services\Broadcast\BroadcastComposerService. Necessarily
 * separate code (no shared-package mechanism between these two apps — see
 * config/features.php's existing hand-synced-duplicate precedent), but
 * writes directly into the SAME shared `broadcast_notifications`/
 * `broadcast_recipients` tables, so it's one engine, not two.
 *
 * No scheduling here (unlike the Tenant Admin composer) — every superadmin
 * broadcast sends immediately; `scheduled_at` stays null.
 */
class BroadcastComposerService
{
    public function __construct(
        protected SuperAdminBroadcastAudienceResolver $resolver,
        protected BroadcastDeliveryService $delivery,
    ) {
    }

    /** @throws ValidationException */
    public function validate(Request $request, SuperAdmin $actor): array
    {
        $validator = Validator::make($request->all(), [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'action_url' => ['nullable', 'url', 'max:500'],
            'action_label' => ['nullable', 'required_with:action_url', 'string', 'max:100'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'audience_type' => ['required', 'in:' . implode(',', SuperAdminBroadcastAudienceResolver::MODES)],
            'tenant_ids' => ['array'],
            'tenant_ids.*' => ['integer', 'exists:tenants,id'],
            'role' => ['array'],
            'role.*' => ['string'],
        ]);

        $validator->after(fn ($v) => $this->applyAudienceRules($v, $request, $actor));

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Lightweight audience-only validation for the live recipient-count
     * preview, matching hrm (3)'s validateAudienceOnly() — doesn't require
     * title/body, so adjusting the audience mode before typing a title
     * still gives a real count.
     *
     * @throws ValidationException
     */
    public function validateAudienceOnly(Request $request, SuperAdmin $actor): array
    {
        $validator = Validator::make($request->all(), [
            'audience_type' => ['required', 'in:' . implode(',', SuperAdminBroadcastAudienceResolver::MODES)],
            'tenant_ids' => ['array'],
            'tenant_ids.*' => ['integer'],
            'role' => ['array'],
            'role.*' => ['string'],
        ]);

        $validator->after(fn ($v) => $this->applyAudienceRules($v, $request, $actor));

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    private function applyAudienceRules($v, Request $request, SuperAdmin $actor): void
    {
        $audienceType = $request->input('audience_type');
        $tenantIds = array_values(array_filter($request->input('tenant_ids', [])));
        $roles = array_values(array_filter($request->input('role', [])));

        if ($audienceType === 'selected_tenants' && ! $tenantIds) {
            $v->errors()->add('tenant_ids', 'Select at least one tenant.');
        }

        // Highest blast-radius mode — service-layer check, not just route
        // middleware, since the composer's route group also admits `support`.
        if ($audienceType === 'all_eligible' && $actor->role !== 'superadmin') {
            $v->errors()->add('audience_type', 'Only a Superadmin can target "All eligible users".');
        }

        if ($audienceType === 'superadmin_users') {
            foreach ($roles as $r) {
                if (! in_array($r, ['superadmin', 'support', 'billing'], true)) {
                    $v->errors()->add('role', 'Invalid super admin role filter.');
                    break;
                }
            }
        }

        if ($audienceType === 'selected_tenants') {
            foreach ($roles as $r) {
                if (! in_array($r, ['admin', 'hr', 'manager', 'employee'], true)) {
                    $v->errors()->add('role', 'Invalid role filter.');
                    break;
                }
            }
        }
    }

    public function countRecipients(array $validated): int
    {
        return $this->resolver->countFor($validated['audience_type'], $this->filtersFrom($validated));
    }

    public function create(SuperAdmin $creator, array $validated): Broadcast
    {
        $audienceType = $validated['audience_type'];
        $filters = $this->filtersFrom($validated);

        $broadcast = DB::transaction(function () use ($creator, $validated, $audienceType, $filters) {
            $broadcast = Broadcast::create([
                'origin' => 'superadmin',
                'origin_tenant_id' => null,
                'created_by_super_admin_id' => $creator->id,
                'title' => $validated['title'],
                'body' => $validated['body'],
                'action_url' => $validated['action_url'] ?? null,
                'action_label' => $validated['action_label'] ?? null,
                'audience_type' => $audienceType,
                'audience_filters' => $filters,
                'channels' => ['database'],
                'priority' => $validated['priority'] ?? 'normal',
                'status' => 'sending',
            ]);

            $this->snapshotRecipients($broadcast, $audienceType, $filters, $creator->id);

            return $broadcast;
        });

        $this->delivery->dispatchDelivery($broadcast);

        return $broadcast;
    }

    /**
     * Snapshot the resolved audience into broadcast_recipients, chunked.
     * `$excludeSuperAdminId` drops the creator out of a `superadmin_users`/
     * `all_eligible` send, mirroring the Tenant Admin composer excluding the
     * creator from their own broadcast.
     */
    public function snapshotRecipients(Broadcast $broadcast, string $audienceType, array $filters, ?int $excludeSuperAdminId = null): int
    {
        $count = 0;
        $now = now();

        $this->resolver->resolve($audienceType, $filters)
            ->reject(fn (array $r) => $excludeSuperAdminId
                && $r['recipient_type'] === 'super_admin'
                && ($r['super_admin_id'] ?? null) === $excludeSuperAdminId)
            ->chunk(500)
            ->each(function ($chunk) use ($broadcast, $now, &$count) {
                $rows = $chunk->map(fn (array $r) => [
                    'broadcast_id' => $broadcast->id,
                    // Sentinel 0 for super_admin recipients — see
                    // App\Models\BroadcastRecipient's docblock.
                    'tenant_id' => $r['tenant_id'] ?? 0,
                    'recipient_type' => $r['recipient_type'],
                    'user_id' => $r['user_id'] ?? null,
                    'super_admin_id' => $r['super_admin_id'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->values()->all();

                if ($rows) {
                    // MySQL's ON DUPLICATE KEY UPDATE fires on a conflict with
                    // ANY unique index on the table (unlike Postgres' ON
                    // CONFLICT(columns), the $uniqueBy arg here is advisory
                    // for MySQL) — so one upsert() call correctly dedupes a
                    // chunk mixing both broadcast_recipients unique
                    // constraints, UNIQUE(broadcast_id,user_id) AND
                    // UNIQUE(broadcast_id,super_admin_id).
                    BroadcastRecipient::upsert($rows, ['broadcast_id', 'user_id'], ['updated_at']);
                    $count += count($rows);
                }
            });

        $broadcast->update(['total_recipients' => $count]);

        return $count;
    }

    private function filtersFrom(array $validated): array
    {
        return [
            'tenant_ids' => array_values(array_filter($validated['tenant_ids'] ?? [])),
            'role' => array_values(array_filter($validated['role'] ?? [])),
        ];
    }
}
