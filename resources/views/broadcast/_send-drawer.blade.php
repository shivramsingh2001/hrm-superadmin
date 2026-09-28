{{--
    "Send Broadcast" composer, now a slide-over drawer on the history page
    itself (resources/views/broadcast/index.blade.php) instead of a
    separate /broadcast/create page — same pattern as the Tenant Admin
    composer in hrm (3). Hand-rolled Bootstrap offcanvas (this app has no
    <x-ui.drawer> component) — same anatomy as the existing
    #templateDrawer/#applyDrawer pair in feature-templates/index.blade.php,
    except rendered directly here rather than fetch()-loaded, since the
    form doesn't need lazy loading.
--}}
<div class="offcanvas offcanvas-end" tabindex="-1" id="sendBroadcastDrawer" aria-labelledby="sendBroadcastDrawerLabel" style="width:480px;max-width:96vw">
    <div class="offcanvas-header px-4 border-bottom" style="padding-top:.9rem;padding-bottom:.9rem">
        <h6 class="offcanvas-title mb-0" id="sendBroadcastDrawerLabel" style="font-size:.9rem">Send Broadcast</h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body px-4 py-3">
        <div class="bc-card">
            <h6><i class="bi bi-pencil-square"></i> Message</h6>
            <form method="POST" action="{{ route('broadcast.store') }}" id="broadcastForm">
                @csrf

                <div class="mb-3">
                    <label class="bc-label">Title *</label>
                    <input type="text" name="title" class="form-control form-control-sm" maxlength="255" required placeholder="e.g. Platform maintenance on Nov 1" value="{{ old('title') }}">
                </div>
                <div class="mb-3">
                    <label class="bc-label">Message *</label>
                    <textarea name="body" class="form-control form-control-sm" rows="3" required placeholder="Write the notification message…">{{ old('body') }}</textarea>
                </div>
                <div class="row g-3 mb-1">
                    <div class="col-12">
                        <label class="bc-label">Priority</label>
                        <select name="priority" class="form-select form-select-sm">
                            <option value="normal" selected>Normal</option>
                            <option value="low">Low</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="bc-label">Action link (optional)</label>
                        <input type="url" name="action_url" class="form-control form-control-sm" placeholder="https://…" value="{{ old('action_url') }}">
                    </div>
                    <div class="col-12">
                        <label class="bc-label">Button label</label>
                        <input type="text" name="action_label" class="form-control form-control-sm" maxlength="100" placeholder="e.g. View Details" value="{{ old('action_label') }}">
                    </div>
                </div>
            </form>
        </div>

        <div class="bc-card">
            <h6><i class="bi bi-broadcast"></i> Audience</h6>

            <label class="bc-mode" data-mode="superadmin_users">
                <input type="radio" name="audience_type" value="superadmin_users" form="broadcastForm">
                <span class="title">Superadmin users</span> <span class="desc">— platform staff (superadmin / support / billing)</span>
                <div class="bc-mode-fields">
                    <label class="bc-label">Narrow by role (optional)</label>
                    @foreach (['superadmin' => 'Superadmin', 'support' => 'Support', 'billing' => 'Billing'] as $val => $label)
                        <label class="bc-role-check"><input type="checkbox" name="role[]" value="{{ $val }}" form="broadcastForm" class="bc-audience-input"> {{ $label }}</label>
                    @endforeach
                </div>
            </label>

            <label class="bc-mode" data-mode="tenant_admins_managers">
                <input type="radio" name="audience_type" value="tenant_admins_managers" form="broadcastForm">
                <span class="title">Tenant Admins / Managers</span> <span class="desc">— across all tenants, or narrowed to some</span>
                <div class="bc-mode-fields">
                    <label class="bc-label">Narrow to tenants (optional — empty means all tenants)</label>
                    <select name="tenant_ids[]" class="form-select form-select-sm bc-tenant-select bc-audience-input" multiple form="broadcastForm" data-mode="tenant_admins_managers">
                        @foreach ($tenants as $t)
                            <option value="{{ $t->id }}">{{ $t->company_name }}</option>
                        @endforeach
                    </select>
                </div>
            </label>

            <label class="bc-mode" data-mode="tenant_employees">
                <input type="radio" name="audience_type" value="tenant_employees" form="broadcastForm">
                <span class="title">Tenant Employees</span> <span class="desc">— across all tenants, or narrowed to some</span>
                <div class="bc-mode-fields">
                    <label class="bc-label">Narrow to tenants (optional — empty means all tenants)</label>
                    <select name="tenant_ids[]" class="form-select form-select-sm bc-tenant-select bc-audience-input" multiple form="broadcastForm" data-mode="tenant_employees">
                        @foreach ($tenants as $t)
                            <option value="{{ $t->id }}">{{ $t->company_name }}</option>
                        @endforeach
                    </select>
                </div>
            </label>

            <label class="bc-mode" data-mode="selected_tenants">
                <input type="radio" name="audience_type" value="selected_tenants" form="broadcastForm">
                <span class="title">Selected tenants</span> <span class="desc">— every user (optionally by role) in the tenants you pick</span>
                <div class="bc-mode-fields">
                    <label class="bc-label">Tenants *</label>
                    <select name="tenant_ids[]" class="form-select form-select-sm bc-tenant-select bc-audience-input" multiple form="broadcastForm" data-mode="selected_tenants">
                        @foreach ($tenants as $t)
                            <option value="{{ $t->id }}">{{ $t->company_name }}</option>
                        @endforeach
                    </select>
                    <label class="bc-label mt-2">Role (optional)</label>
                    @foreach (['admin' => 'Admin', 'hr' => 'HR', 'manager' => 'Manager', 'employee' => 'Employee'] as $val => $label)
                        <label class="bc-role-check"><input type="checkbox" name="role[]" value="{{ $val }}" form="broadcastForm" class="bc-audience-input"> {{ $label }}</label>
                    @endforeach
                </div>
            </label>

            @if (auth()->user()->isSuperadmin())
                <label class="bc-mode" data-mode="all_eligible">
                    <input type="radio" name="audience_type" value="all_eligible" form="broadcastForm">
                    <span class="title">All eligible users</span> <span class="desc">— every active tenant user AND every active superadmin. Superadmin only.</span>
                </label>
            @endif
        </div>

        <div class="bc-card bc-submit-bar">
            <div class="bc-count-chip"><i class="bi bi-people"></i> <span id="bcCountValue">0</span> recipient(s)</div>
            <button type="submit" form="broadcastForm" class="btn btn-sm btn-primary" id="bcSubmitBtn"><i class="bi bi-send"></i> Send Broadcast</button>
        </div>
    </div>
</div>
