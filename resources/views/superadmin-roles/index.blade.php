@extends('layouts.app')
@section('title', 'Panel Roles')

@section('content')
<style>
    .sr-wrap { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .sr-head { display: flex; align-items: center; justify-content: space-between;
        padding: .55rem .9rem; background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; margin-bottom: .75rem; }
    .sr-head h2 { font-size: .92rem; font-weight: 600; margin: 0; color: #1f2937; }
    .sr-head .sub { font-size: .72rem; color: #9ca3af; max-width: 620px; }
    .sr-head .new-hint { font-size: .68rem; color: #adb3ba; margin-top: .15rem; }
    table.sr-table { font-size: .8rem; margin: 0; }
    table.sr-table thead th { font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
        color: #9ca3af; background: #fafbfc; border-bottom: 1px solid #e5e7eb; padding: .55rem .7rem; white-space: nowrap; }
    table.sr-table tbody td { padding: .55rem .7rem; vertical-align: middle; color: #374151; }
    table.sr-table tbody tr:hover { background: #fafbfc; }
    table.sr-table .sr { color: #adb3ba; width: 2.6rem; white-space: nowrap; }
    table.sr-table .desc { max-width: 12rem; font-size: .72rem; color: #9ca3af; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    table.sr-table .mod-badge { font-size: .7rem; font-weight: 600; background: #eef2ff; color: #4338ca; padding: .1rem .4rem; border-radius: .3rem; }
    table.sr-table .base-pill { font-size: .68rem; font-weight: 600; background: #f3f4f6; color: #374151; padding: .1rem .4rem; border-radius: .3rem; }
    table.sr-table .system-badge { font-size: .62rem; font-weight: 700; text-transform: uppercase; letter-spacing: .02em;
        background: #f3f4f6; color: #9ca3af; padding: .1rem .4rem; border-radius: .3rem; margin-left: .3rem; }
    table.sr-table .btn-icon { border: 0; background: transparent; color: #6b7280; padding: .2rem .4rem; line-height: 1; }
    table.sr-table .btn-icon:hover { color: #2563eb; }
    table.sr-table .btn-icon.danger:hover { color: #dc2626; }
    table.sr-table .btn-icon:disabled { color: #d1d5db; cursor: not-allowed; }
    .perm-panel { background: #fafbfc; }
    .perm-matrix { font-size: .76rem; }
    .perm-matrix th, .perm-matrix td { text-align: center; vertical-align: middle; }
    .perm-matrix td:first-child, .perm-matrix th:first-child { text-align: left; }
    .modal-compact label.form-label { font-size: .7rem; font-weight: 500; color: #6b7280; margin-bottom: .2rem; }
    .modal-compact .form-control, .modal-compact .form-select { font-size: .85rem; }
</style>

<div class="sr-wrap">
    <div class="sr-head">
        <div>
            <h2>Panel Roles</h2>
            <span class="sub">{{ $roles->count() }} role(s) — a single role list, including the built-in Superadmin/Support/Billing tiers, each with a per-module View/Create/Edit/Delete permission set assignable to a panel user.</span>
        </div>
        <div class="text-end">
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                <i class="bi bi-plus-lg"></i> New Role
            </button>
            <div class="new-hint">Define a name, access tier, and permission set.</div>
        </div>
    </div>

    <div class="card"><div class="table-responsive">
        <table class="table sr-table align-middle mb-0">
            <thead>
                <tr>
                    <th class="sr">Sr. No.</th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Description</th>
                    <th>Access tier</th>
                    <th>Permissions</th>
                    <th>Users</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($roles as $r)
                @php($rolePerms = $permissionsByRole[$r->id] ?? collect())
                <tr>
                    <td class="sr">{{ $loop->iteration }}</td>
                    <td class="fw-semibold">{{ $r->name }}@if($r->is_system)<span class="system-badge">system</span>@endif</td>
                    <td><code>{{ $r->slug }}</code></td>
                    <td><div class="desc" title="{{ $r->description }}">{{ $r->description ?: '—' }}</div></td>
                    <td>@if($r->base_role)<span class="base-pill">{{ ucfirst($r->base_role) }}</span>@else<span class="text-danger" style="font-size:.7rem">not set</span>@endif</td>
                    <td><span class="mod-badge">{{ $r->permissions_count }}</span></td>
                    <td class="text-secondary">{{ $r->super_admins_count }}</td>
                    <td class="text-end text-nowrap">
                        <button class="btn-icon" title="{{ $r->is_system ? 'System roles cannot be edited' : 'Edit role' }}" type="button"
                                data-bs-toggle="modal" data-bs-target="#editRoleModal{{ $r->id }}" {{ $r->is_system ? 'disabled' : '' }}>
                            <i class="bi bi-pencil fs-6"></i>
                        </button>
                        <button class="btn-icon" title="Edit permissions" type="button" data-bs-toggle="collapse" data-bs-target="#permPanel{{ $r->id }}" {{ $r->is_system ? 'disabled' : '' }}>
                            <i class="bi bi-sliders fs-6"></i>
                        </button>
                        <form method="POST" action="{{ route('superadmin-roles.destroy', $r) }}" class="d-inline"
                              onsubmit="return confirm('Delete role {{ $r->name }}?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-icon danger" title="{{ $r->is_system ? 'System roles cannot be deleted' : 'Delete' }}" {{ $r->is_system ? 'disabled' : '' }}>
                                <i class="bi bi-trash fs-6"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @unless($r->is_system)
                <tr class="collapse" id="permPanel{{ $r->id }}">
                    <td colspan="8" class="perm-panel p-3">
                        <form method="POST" action="{{ route('superadmin-roles.permissions', $r) }}">
                            @csrf
                            <div class="table-responsive">
                                <table class="table table-sm perm-matrix mb-2">
                                    <thead><tr><th>Module</th>@foreach($actions as $a)<th>{{ ucfirst($a) }}</th>@endforeach</tr></thead>
                                    <tbody>
                                    @foreach($modules as $mkey => $mlabel)
                                        <tr>
                                            <td>{{ $mlabel }} <code class="text-secondary" style="font-size:.65rem">{{ $mkey }}</code></td>
                                            @foreach($actions as $a)
                                                <td><input type="checkbox" name="perm[{{ $mkey }}][{{ $a }}]" value="1"
                                                    @checked(in_array($a, $rolePerms[$mkey] ?? []))></td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <button class="btn btn-sm btn-primary">Save matrix</button>
                        </form>
                    </td>
                </tr>
                @endunless
            @empty
                <tr><td colspan="8" class="text-secondary">No roles yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
</div>

{{-- Create role — small centered modal --}}
<div class="modal fade" id="createRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
        <div class="modal-content">
            <form method="POST" action="{{ route('superadmin-roles.store') }}" class="modal-compact">
                @csrf
                <div class="modal-header py-2">
                    <h6 class="modal-title mb-0" style="font-size:.9rem">New role</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Name *</label>
                    <input name="name" class="form-control form-control-sm mb-2" placeholder="e.g. Read-only Auditor" required>
                    <label class="form-label">Slug</label>
                    <input name="slug" class="form-control form-control-sm mb-2" placeholder="auto from name">
                    <label class="form-label">Access tier *</label>
                    <select name="base_role" class="form-select form-select-sm mb-2" required>
                        <option value="">— select —</option>
                        @foreach($baseRoles as $b)<option value="{{ $b }}">{{ ucfirst($b) }}</option>@endforeach
                    </select>
                    <div class="text-secondary mb-2" style="font-size:.68rem">Sets what this role can access at the broadest level (matches today's login gate). Fine-grained module permissions are set after creating the role.</div>
                    <label class="form-label">Description</label>
                    <input name="description" class="form-control form-control-sm" placeholder="Description (optional)">
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-link" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-sm btn-primary">Create role</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit role — small centered modal, one per non-system role --}}
@foreach($roles as $r)
    @unless($r->is_system)
    <div class="modal fade" id="editRoleModal{{ $r->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
            <div class="modal-content">
                <form method="POST" action="{{ route('superadmin-roles.update', $r) }}" class="modal-compact">
                    @csrf @method('PUT')
                    <div class="modal-header py-2">
                        <h6 class="modal-title mb-0" style="font-size:.9rem">Edit · {{ $r->name }}</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">Name *</label>
                        <input name="name" value="{{ $r->name }}" class="form-control form-control-sm mb-2" required>
                        <label class="form-label">Slug *</label>
                        <input name="slug" value="{{ $r->slug }}" class="form-control form-control-sm mb-2" required>
                        <label class="form-label">Access tier *</label>
                        <select name="base_role" class="form-select form-select-sm mb-2" required>
                            <option value="">— select —</option>
                            @foreach($baseRoles as $b)<option value="{{ $b }}" @selected($r->base_role === $b)>{{ ucfirst($b) }}</option>@endforeach
                        </select>
                        <label class="form-label">Description</label>
                        <input name="description" value="{{ $r->description }}" class="form-control form-control-sm" placeholder="Description (optional)">
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-sm btn-link" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-sm btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endunless
@endforeach
@endsection
