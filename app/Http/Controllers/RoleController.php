<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Services\PlatformClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manage a tenant's roles + permission matrix on their behalf. System roles
 * (config('rbac.system_slugs')) are locked — no edit, no delete.
 */
class RoleController extends Controller
{
    public function store(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100', 'alpha_dash'],
        ]);
        $slug = ($data['slug'] ?? null) ?: Str::slug($data['name'], '_');

        if (in_array($slug, config('rbac.system_slugs'), true)) {
            return back()->with('error', "'{$slug}' is a reserved system role slug.");
        }
        if (Role::where('tenant_id', $tenant->id)->where('slug', $slug)->exists()) {
            return back()->with('error', "A role with slug '{$slug}' already exists for this tenant.");
        }

        $role = Role::create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'slug' => $slug,
            'description' => 'Custom role',
            'is_system' => 0,
        ]);
        AuditLogger::record('role.created', 'roles', $role->id, null, $role->toArray(), $tenant->id);

        return back()->with('success', "Role '{$role->name}' created.");
    }

    /** Replace a role's whole permission set from the posted matrix. */
    public function updatePermissions(Request $request, Tenant $tenant, Role $role)
    {
        abort_unless((int) $role->tenant_id === (int) $tenant->id, 404);
        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be edited.');
        }

        $modules = array_keys(config('rbac.modules'));
        $actions = config('rbac.actions');
        $scopes = array_keys(config('rbac.scopes'));
        $scopeInput = (array) $request->input('scope', []);

        $before = RolePermission::where('role_id', $role->id)
            ->get()->map(fn ($p) => "{$p->module}:{$p->action}:" . ($p->scope ?? 'company'))->sort()->values()->all();

        $wanted = [];
        foreach ((array) $request->input('perm', []) as $module => $acts) {
            if (! in_array($module, $modules, true)) {
                continue;
            }
            foreach (array_keys((array) $acts) as $action) {
                if (! in_array($action, $actions, true)) {
                    continue;
                }
                $scope = $scopeInput[$module][$action] ?? 'company';
                if (! in_array($scope, $scopes, true)) {
                    $scope = 'company';
                }
                $wanted[] = ['role_id' => $role->id, 'module' => $module, 'action' => $action, 'scope' => $scope];
            }
        }

        DB::transaction(function () use ($role, $wanted) {
            RolePermission::where('role_id', $role->id)->delete();
            if ($wanted) {
                RolePermission::insert(array_map(fn ($r) => $r + [
                    'created_at' => now(), 'updated_at' => now(),
                ], $wanted));
            }
        });

        $after = collect($wanted)->map(fn ($r) => "{$r['module']}:{$r['action']}:{$r['scope']}")->sort()->values()->all();
        AuditLogger::record('role.permissions_updated', 'roles', $role->id,
            ['permissions' => $before], ['permissions' => $after], $tenant->id);
        PlatformClient::bustRole($role->id);

        return back()->with('success', "Permissions updated for '{$role->name}'.");
    }

    public function destroy(Tenant $tenant, Role $role)
    {
        abort_unless((int) $role->tenant_id === (int) $tenant->id, 404);
        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be deleted.');
        }
        $inUse = DB::table('users')->where('tenant_id', $tenant->id)->where('role_id', $role->id)->exists();
        if ($inUse) {
            return back()->with('error', 'Cannot delete — users are assigned this role.');
        }

        AuditLogger::record('role.deleted', 'roles', $role->id, $role->toArray(), null, $tenant->id);
        RolePermission::where('role_id', $role->id)->delete();
        $role->delete();

        return back()->with('success', 'Role deleted.');
    }

    /** Copy a role (name + permissions) into another tenant. */
    public function clone(Request $request, Tenant $tenant, Role $role)
    {
        abort_unless((int) $role->tenant_id === (int) $tenant->id, 404);
        $data = $request->validate(['target_tenant_id' => ['required', 'integer', 'exists:tenants,id']]);
        $target = Tenant::findOrFail($data['target_tenant_id']);

        $slug = $role->slug;
        if (in_array($slug, config('rbac.system_slugs'), true)) {
            $slug .= '_copy';
        }
        if (Role::where('tenant_id', $target->id)->where('slug', $slug)->exists()) {
            return back()->with('error', "Target tenant already has a role '{$slug}'.");
        }

        $new = Role::create([
            'tenant_id' => $target->id,
            'name' => $role->name,
            'slug' => $slug,
            'description' => "Cloned from {$tenant->company_name}",
            'is_system' => 0,
        ]);
        foreach (RolePermission::where('role_id', $role->id)->get() as $p) {
            RolePermission::firstOrCreate(['role_id' => $new->id, 'module' => $p->module, 'action' => $p->action]);
        }
        AuditLogger::record('role.cloned', 'roles', $new->id, null,
            ['from_tenant' => $tenant->id, 'from_role' => $role->id], $target->id);

        return back()->with('success', "Cloned '{$role->name}' into {$target->company_name}.");
    }
}
