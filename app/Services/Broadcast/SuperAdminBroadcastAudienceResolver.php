<?php

namespace App\Services\Broadcast;

use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resolves one of the 5 Superadmin audience modes into a normalized list of
 * recipient descriptors (`['recipient_type', 'user_id'|'super_admin_id',
 * 'tenant_id']`). Collapses to two underlying query primitives —
 * superAdminsQuery()/tenantUsersQuery() — so "5 modes" isn't 5 copies of
 * similar logic; `selected_tenants` deliberately overlaps
 * `tenant_admins_managers`/`tenant_employees` (same primitive, two UI entry
 * points: tenant-first vs. role-first).
 *
 * `tenantUsersQuery()` reuses the exact idiom already proven in
 * TenantController::index() — App\Models\User here is already a plain,
 * unscoped, cross-tenant model over the shared `users` table.
 */
class SuperAdminBroadcastAudienceResolver
{
    /** `hr` is bucketed with admin/manager per the module's product decision. */
    public const ADMIN_MANAGER_ROLES = ['admin', 'manager', 'hr'];

    public const MODES = [
        'superadmin_users', 'tenant_admins_managers', 'tenant_employees', 'selected_tenants', 'all_eligible',
    ];

    public function superAdminsQuery(array $roles = []): Builder
    {
        $query = SuperAdmin::query()->where('is_active', 1);
        if ($roles) {
            $query->whereIn('role', $roles);
        }

        return $query;
    }

    public function tenantUsersQuery(array $tenantIds = [], array $roles = []): Builder
    {
        $query = User::query()->where('status', 1);
        if ($tenantIds) {
            $query->whereIn('tenant_id', $tenantIds);
        }
        if ($roles) {
            $query->whereIn('role', $roles);
        }

        return $query;
    }

    /** @return Collection<int, array{recipient_type: string, user_id?: int, super_admin_id?: int, tenant_id: ?int}> */
    public function resolve(string $mode, array $filters): Collection
    {
        $tenantIds = $this->cleanIds($filters['tenant_ids'] ?? []);
        $roles = $this->cleanValues($filters['role'] ?? []);

        return match ($mode) {
            'superadmin_users' => $this->superAdminRows($roles),
            'tenant_admins_managers' => $this->tenantUserRows($tenantIds, self::ADMIN_MANAGER_ROLES),
            'tenant_employees' => $this->tenantUserRows($tenantIds, ['employee']),
            'selected_tenants' => $this->tenantUserRows($tenantIds, $roles),
            'all_eligible' => $this->superAdminRows([])->concat($this->tenantUserRows([], [])),
            default => collect(),
        };
    }

    public function countFor(string $mode, array $filters): int
    {
        $tenantIds = $this->cleanIds($filters['tenant_ids'] ?? []);
        $roles = $this->cleanValues($filters['role'] ?? []);

        return match ($mode) {
            'superadmin_users' => $this->superAdminsQuery($roles)->count(),
            'tenant_admins_managers' => $this->tenantUsersQuery($tenantIds, self::ADMIN_MANAGER_ROLES)->count(),
            'tenant_employees' => $this->tenantUsersQuery($tenantIds, ['employee'])->count(),
            'selected_tenants' => $tenantIds ? $this->tenantUsersQuery($tenantIds, $roles)->count() : 0,
            'all_eligible' => $this->superAdminsQuery()->count() + $this->tenantUsersQuery()->count(),
            default => 0,
        };
    }

    private function superAdminRows(array $roles): Collection
    {
        return $this->superAdminsQuery($roles)->get(['id'])->map(fn (SuperAdmin $sa) => [
            'recipient_type' => 'super_admin',
            'super_admin_id' => $sa->id,
            'tenant_id' => null,
        ]);
    }

    private function tenantUserRows(array $tenantIds, array $roles): Collection
    {
        return $this->tenantUsersQuery($tenantIds, $roles)->get(['id', 'tenant_id'])->map(fn (User $u) => [
            'recipient_type' => 'tenant_user',
            'user_id' => $u->id,
            'tenant_id' => $u->tenant_id,
        ]);
    }

    private function cleanIds(array $values): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $values))));
    }

    private function cleanValues(array $values): array
    {
        return array_values(array_unique(array_filter(array_map('strval', $values))));
    }
}
