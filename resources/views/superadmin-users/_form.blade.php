<style>
    .tc-compact { font-size: .8rem; }
    .tc-compact .pc-sec { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
        color: #6b7280; margin: 1rem 0 .4rem; }
    .tc-compact .pc-sec:first-child { margin-top: 0; }
    .tc-compact label.form-label { font-size: .7rem; font-weight: 500; color: #6b7280; margin-bottom: .2rem; }
    .tc-compact .form-control, .tc-compact .form-select {
        font-size: .85rem; padding: .3rem .75rem; height: auto; }
    .tc-compact .row.g-2 { --bs-gutter-x: 1.5rem; }
    .tc-compact .row.g-2 > [class*="col"] { margin-bottom: .55rem; }
    .tc-compact .pc-actions { position: sticky; bottom: 0; background: #fff; padding: .6rem 0 .2rem;
        margin-top: .7rem; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
</style>

<div class="tc-compact">

<form method="POST" action="{{ $user->exists ? route('superadmin-users.update', $user) : route('superadmin-users.store') }}">
    @csrf
    @if($user->exists) @method('PUT') @endif

    <div class="pc-sec">Account</div>
    <div class="row g-2">
        <div class="col-6"><label class="form-label">Name *</label>
            <input name="name" value="{{ old('name', $user->name) }}" class="form-control form-control-sm" placeholder="e.g. Priya Sharma" required></div>
        <div class="col-6"><label class="form-label">Email *</label>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control form-control-sm" placeholder="name@company.com" required></div>
        <div class="col-6"><label class="form-label">Mobile no.</label>
            <input name="mobile" value="{{ old('mobile', $user->mobile) }}" class="form-control form-control-sm" placeholder="e.g. 9876543210" maxlength="20"></div>
        <div class="col-6"><label class="form-label">{{ $user->exists ? 'New password' : 'Password *' }}</label>
            <input type="password" name="password" class="form-control form-control-sm" placeholder="{{ $user->exists ? 'Leave blank to keep current' : 'Min. 8 characters' }}" {{ $user->exists ? '' : 'required' }}></div>
        <div class="col-6 d-flex align-items-end">
            <div class="form-check">
                <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', $user->is_active ?? true))>
                <label for="is_active" class="form-check-label" style="font-size:.8rem">Active</label>
            </div>
        </div>
    </div>

    <div class="pc-sec">Access</div>
    <div class="row g-2">
        <div class="col-12"><label class="form-label">Role *</label>
            <select name="role_id" class="form-select form-select-sm" required>
                <option value="">— select a role —</option>
                @foreach($panelRoles as $r)
                    <option value="{{ $r->id }}" @selected((string) old('role_id', $user->role_id) === (string) $r->id)>
                        {{ $r->name }}{{ $r->is_system ? ' (system)' : '' }}
                    </option>
                @endforeach
            </select>
            <div class="text-secondary mt-1" style="font-size:.68rem">Roles are managed on the <a href="{{ route('superadmin-roles.index') }}">Roles</a> page.</div></div>
        <div class="col-12"><label class="form-label">Allowed IPs</label>
            <textarea name="allowed_ips" class="form-control form-control-sm" rows="2" placeholder="One IP per line — leave blank for no restriction">{{ old('allowed_ips', $user->allowed_ips ? implode("\n", $user->allowed_ips) : '') }}</textarea></div>
    </div>

    <div class="pc-actions">
        <a href="{{ route('superadmin-users.index') }}" class="btn btn-sm btn-link">Cancel</a>
        <button class="btn btn-sm btn-primary">{{ $user->exists ? 'Save changes' : 'Create user' }}</button>
    </div>
</form>

</div>
