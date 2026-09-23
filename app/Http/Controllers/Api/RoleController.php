<?php

namespace App\Http\Controllers\Api;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Services\PlatformClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleController extends ApiController
{
    public function index(Request $request)
    {
        $request->validate(['tenant_id' => ['required', 'integer', 'exists:tenants,id']]);

        return $this->ok(Role::where('tenant_id', $request->input('tenant_id'))->with('permissions')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'alpha_dash', 'max:100'],
        ]);
        $slug = ($data['slug'] ?? null) ?: Str::slug($data['name'], '_');
        if (in_array($slug, config('rbac.system_slugs'), true)) {
            return $this->fail('reserved_slug', "'{$slug}' is a reserved system slug.");
        }
        if (Role::where('tenant_id', $data['tenant_id'])->where('slug', $slug)->exists()) {
            return $this->fail('duplicate', "Role '{$slug}' already exists for this tenant.", 409);
        }
        $role = Role::create([
            'tenant_id' => $data['tenant_id'], 'name' => $data['name'], 'slug' => $slug,
            'description' => 'Custom role', 'is_system' => 0,
        ]);
        AuditLogger::record('role.created', 'roles', $role->id, null, $role->toArray(), $data['tenant_id']);

        return $this->ok($role, 201);
    }

    public function updatePermissions(Request $request, Role $role)
    {
        if ($role->is_system) {
            return $this->fail('locked', 'System roles cannot be edited.', 403);
        }
        $data = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*.module' => ['required', 'in:' . implode(',', array_keys(config('rbac.modules')))],
            'permissions.*.action' => ['required', 'in:' . implode(',', config('rbac.actions'))],
        ]);

        $before = RolePermission::where('role_id', $role->id)->get()->map(fn ($p) => "{$p->module}:{$p->action}")->all();

        DB::transaction(function () use ($role, $data) {
            RolePermission::where('role_id', $role->id)->delete();
            $rows = collect($data['permissions'])->unique(fn ($p) => "{$p['module']}:{$p['action']}")
                ->map(fn ($p) => ['role_id' => $role->id, 'module' => $p['module'], 'action' => $p['action'], 'created_at' => now(), 'updated_at' => now()])
                ->all();
            if ($rows) {
                RolePermission::insert($rows);
            }
        });

        $after = collect($data['permissions'])->map(fn ($p) => "{$p['module']}:{$p['action']}")->all();
        AuditLogger::record('role.permissions_updated', 'roles', $role->id, ['permissions' => $before], ['permissions' => $after], $role->tenant_id);
        PlatformClient::bustRole($role->id);

        return $this->ok($role->load('permissions'));
    }
}
