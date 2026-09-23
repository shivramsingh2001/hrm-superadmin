<?php

namespace App\Http\Controllers;

use App\Models\SuperAdmin;
use App\Models\SuperAdminRole;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Manage the Super Admin Panel's own users (Phase 1 — see the "Users & Custom
 * Roles" plan). The user form shows a single "Role" dropdown (every SuperAdminRole
 * row, including the 3 seeded system ones); super_admins.role — still a DB ENUM
 * everything else's access control runs on — is derived from the chosen role's
 * base_role, not entered directly.
 */
class SuperAdminUserController extends Controller
{
    public function index(Request $request)
    {
        $q = SuperAdmin::with('panelRole')
            ->when($request->input('search'), fn ($w, $s) => $w->where(fn ($x) => $x
                ->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->when($request->input('role_id'), fn ($w, $r) => $w->where('role_id', $r))
            ->orderBy('name');

        return view('superadmin-users.index', [
            'users' => $q->paginate(20)->withQueryString(),
            'panelRoles' => SuperAdminRole::orderByDesc('is_system')->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** Bare partial (no layout) when opened in the side drawer, full page otherwise. */
    public function create(Request $request)
    {
        return view($request->boolean('drawer') ? 'superadmin-users._form' : 'superadmin-users.form', [
            'user' => new SuperAdmin(['is_active' => true]),
            'panelRoles' => SuperAdminRole::orderByDesc('is_system')->orderBy('name')->get(['id', 'name', 'is_system']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['password'] = Hash::make($data['password']);
        $data['allowed_ips'] = $this->parseIps($request->input('allowed_ips'));

        $user = SuperAdmin::create($data);
        AuditLogger::record('superadmin_user.created', 'super_admins', $user->id, null,
            $user->only(['name', 'email', 'mobile', 'role', 'role_id', 'is_active']));

        return redirect()->route('superadmin-users.index')->with('success', "User '{$user->name}' created.");
    }

    public function edit(Request $request, SuperAdmin $superadmin)
    {
        return view($request->boolean('drawer') ? 'superadmin-users._form' : 'superadmin-users.form', [
            'user' => $superadmin,
            'panelRoles' => SuperAdminRole::orderByDesc('is_system')->orderBy('name')->get(['id', 'name', 'is_system']),
        ]);
    }

    public function update(Request $request, SuperAdmin $superadmin)
    {
        $data = $this->validated($request, $superadmin);
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $data['allowed_ips'] = $this->parseIps($request->input('allowed_ips'));

        $old = $superadmin->only(['name', 'email', 'mobile', 'role', 'role_id', 'is_active']);
        $superadmin->update($data);
        AuditLogger::record('superadmin_user.updated', 'super_admins', $superadmin->id,
            $old, $superadmin->only(['name', 'email', 'mobile', 'role', 'role_id', 'is_active']));

        return redirect()->route('superadmin-users.index')->with('success', "User '{$superadmin->name}' updated.");
    }

    /** Activate/deactivate — blocked if this would leave zero active superadmins. */
    public function toggle(Request $request, SuperAdmin $superadmin)
    {
        if ($superadmin->is_active && $superadmin->role === 'superadmin') {
            $otherActiveSuperadmins = SuperAdmin::where('role', 'superadmin')
                ->where('is_active', true)->where('id', '!=', $superadmin->id)->exists();
            if (! $otherActiveSuperadmins) {
                return back()->with('error', 'Cannot deactivate the last active superadmin.');
            }
        }

        $superadmin->update(['is_active' => ! $superadmin->is_active]);
        AuditLogger::record('superadmin_user.toggled', 'super_admins', $superadmin->id,
            null, ['is_active' => $superadmin->is_active]);

        return back()->with('success', $superadmin->is_active ? 'User activated.' : 'User deactivated.');
    }

    /** Clear TOTP so the user re-enrols at next sign-in (UI equivalent of the superadmin:reset-totp command). */
    public function resetTotp(SuperAdmin $superadmin)
    {
        $superadmin->forceFill(['totp_secret' => null, 'totp_enabled_at' => null])->save();
        AuditLogger::record('superadmin_user.totp_reset', 'super_admins', $superadmin->id, null, null);

        return back()->with('success', "TOTP reset for {$superadmin->email} — they'll re-enrol at next sign-in.");
    }

    private function validated(Request $request, ?SuperAdmin $user = null): array
    {
        $ignoreId = $user?->id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('super_admins', 'email')->ignore($ignoreId)],
            'mobile' => ['nullable', 'string', 'max:20'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'role_id' => ['required', 'integer', 'exists:sa_roles,id'],
        ]);

        $role = SuperAdminRole::find($data['role_id']);
        if (! $role->base_role) {
            throw ValidationException::withMessages(['role_id' => "'{$role->name}' has no access tier set yet — edit it on the Roles page first."]);
        }
        $data['role'] = $role->base_role;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function parseIps(?string $raw): ?array
    {
        if (! $raw || ! trim($raw)) {
            return null;
        }
        $ips = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw)));

        return $ips ? array_values($ips) : null;
    }
}
