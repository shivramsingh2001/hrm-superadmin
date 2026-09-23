<?php

namespace App\Http\Controllers;

use App\Models\SuperAdmin;
use App\Models\SuperAdminRole;
use App\Models\SuperAdminRolePermission;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Custom roles for THIS panel's own users (Phase 1 — see the "Users & Custom
 * Roles" plan). Mirrors the tenant-facing RoleController's pattern: a role has
 * a whole-matrix "replace permissions" action, not a rules engine.
 *
 * Every role (including the 3 seeded system ones — Superadmin/Support/Billing)
 * carries a `base_role`, which is what actually gets written to the still-ENUM
 * super_admins.role column when the role is assigned to a user. This is what
 * lets the user form show a single "Role" dropdown instead of two.
 */
class SuperAdminRoleController extends Controller
{
    private const BASE_ROLES = ['superadmin', 'support', 'billing'];

    public function index()
    {
        $roles = SuperAdminRole::withCount(['permissions', 'superAdmins'])
            ->orderByDesc('is_system')->orderBy('name')->get();
        $permissionsByRole = SuperAdminRolePermission::all()
            ->groupBy('role_id')
            ->map(fn ($rows) => $rows->groupBy('module')->map(fn ($g) => $g->pluck('action')->all()));

        return view('superadmin-roles.index', [
            'roles' => $roles,
            'permissionsByRole' => $permissionsByRole,
            'modules' => config('sa_rbac.modules'),
            'actions' => config('sa_rbac.actions'),
            'baseRoles' => self::BASE_ROLES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100', 'alpha_dash', 'unique:sa_roles,slug'],
            'description' => ['nullable', 'string', 'max:500'],
            'base_role' => ['required', Rule::in(self::BASE_ROLES)],
        ]);
        $slug = ($data['slug'] ?? null) ?: Str::slug($data['name'], '_');
        if (SuperAdminRole::where('slug', $slug)->exists()) {
            return back()->with('error', "A role with slug '{$slug}' already exists.");
        }

        $role = SuperAdminRole::create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'base_role' => $data['base_role'],
            'is_system' => false,
            'created_by' => $request->user()->id,
        ]);
        AuditLogger::record('superadmin_role.created', 'sa_roles', $role->id, null, $role->toArray());

        return back()->with('success', "Role '{$role->name}' created.");
    }

    public function update(Request $request, SuperAdminRole $role)
    {
        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be edited.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('sa_roles', 'slug')->ignore($role->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'base_role' => ['required', Rule::in(self::BASE_ROLES)],
        ]);

        $old = $role->only(['name', 'slug', 'description', 'base_role']);
        $role->update($data);
        AuditLogger::record('superadmin_role.updated', 'sa_roles', $role->id, $old, $data);

        return back()->with('success', "Role '{$role->name}' updated.");
    }

    /** Replace a role's whole permission set from the posted matrix. */
    public function updatePermissions(Request $request, SuperAdminRole $role)
    {
        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be edited.');
        }

        $modules = array_keys(config('sa_rbac.modules'));
        $actions = config('sa_rbac.actions');

        $before = SuperAdminRolePermission::where('role_id', $role->id)
            ->get()->map(fn ($p) => "{$p->module}:{$p->action}")->sort()->values()->all();

        $wanted = [];
        foreach ((array) $request->input('perm', []) as $module => $acts) {
            if (! in_array($module, $modules, true)) {
                continue;
            }
            foreach (array_keys((array) $acts) as $action) {
                if (in_array($action, $actions, true)) {
                    $wanted[] = ['role_id' => $role->id, 'module' => $module, 'action' => $action];
                }
            }
        }

        DB::transaction(function () use ($role, $wanted) {
            SuperAdminRolePermission::where('role_id', $role->id)->delete();
            if ($wanted) {
                SuperAdminRolePermission::insert(array_map(fn ($r) => $r + [
                    'created_at' => now(), 'updated_at' => now(),
                ], $wanted));
            }
        });

        $after = collect($wanted)->map(fn ($r) => "{$r['module']}:{$r['action']}")->sort()->values()->all();
        AuditLogger::record('superadmin_role.permissions_updated', 'sa_roles', $role->id,
            ['permissions' => $before], ['permissions' => $after]);

        return back()->with('success', "Permissions updated for '{$role->name}'.");
    }

    public function destroy(SuperAdminRole $role)
    {
        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be deleted.');
        }
        $inUse = SuperAdmin::where('role_id', $role->id)->exists();
        if ($inUse) {
            return back()->with('error', 'Cannot delete — users are assigned this role.');
        }

        AuditLogger::record('superadmin_role.deleted', 'sa_roles', $role->id, $role->toArray(), null);
        SuperAdminRolePermission::where('role_id', $role->id)->delete();
        $role->delete();

        return back()->with('success', 'Role deleted.');
    }
}
