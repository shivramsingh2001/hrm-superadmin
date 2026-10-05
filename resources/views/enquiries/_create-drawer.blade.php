{{--
    "New enquiry" side drawer (Bootstrap offcanvas, right). Opened by the list's
    "New enquiry" button, by /enquiries?new=1 (old /enquiries/create redirects there),
    and automatically after a failed save (old input + errors, see EnquiryController::store()).
--}}
@php($reopen = request()->boolean('new') || old('_drawer') === 'new')
<div class="offcanvas offcanvas-end enq-drawer" tabindex="-1" id="enqCreateDrawer" aria-labelledby="enqCreateTitle"
     data-open="{{ $reopen ? '1' : '0' }}">
    <div class="offcanvas-header">
        <div>
            <h5 class="offcanvas-title" id="enqCreateTitle"><i class="bi bi-plus-circle"></i> New enquiry</h5>
            <div class="sub">A lead from phone, email, referral or an event — starts as <strong>New</strong>.</div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <form method="POST" action="{{ route('enquiries.store') }}" class="offcanvas-body-wrap" id="enqCreateForm">
        @csrf
        <input type="hidden" name="_drawer" value="new">
        <div class="offcanvas-body">
            @if($reopen && $errors->any())
                <div class="enq-drawer-alert"><i class="bi bi-exclamation-circle"></i> Please fix the highlighted fields.</div>
            @endif

            <div class="enq-sec">Company &amp; contact</div>
            <div class="mb-2">
                <label for="d_company_name">Company name <span class="req">*</span></label>
                <input type="text" id="d_company_name" name="company_name" value="{{ old('company_name') }}" class="form-control form-control-sm @error('company_name') is-invalid @enderror" required maxlength="255">
                @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label for="d_contact_name">Contact person <span class="req">*</span></label>
                    <input type="text" id="d_contact_name" name="contact_name" value="{{ old('contact_name') }}" class="form-control form-control-sm @error('contact_name') is-invalid @enderror" required maxlength="255">
                    @error('contact_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6">
                    <label for="d_phone">Phone</label>
                    <input type="text" id="d_phone" name="phone" value="{{ old('phone') }}" class="form-control form-control-sm @error('phone') is-invalid @enderror" maxlength="20">
                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-7">
                    <label for="d_work_email">Work email <span class="req">*</span></label>
                    <input type="email" id="d_work_email" name="work_email" value="{{ old('work_email') }}" class="form-control form-control-sm @error('work_email') is-invalid @enderror" required maxlength="255">
                    @error('work_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-5">
                    <label for="d_country">Country</label>
                    <input type="text" id="d_country" name="country" value="{{ old('country', 'India') }}" class="form-control form-control-sm @error('country') is-invalid @enderror" maxlength="100">
                    @error('country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="enq-sec">Requirement</div>
            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label for="d_plan_interest">Plan interest</label>
                    <select id="d_plan_interest" name="plan_interest" class="form-select form-select-sm @error('plan_interest') is-invalid @enderror">
                        <option value="">— Not sure yet —</option>
                        @foreach($drawerPlans as $slug => $name)
                            <option value="{{ $slug }}" @selected(old('plan_interest') === $slug)>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('plan_interest')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-6">
                    <label for="d_employee_count">Employees</label>
                    <input type="number" id="d_employee_count" name="employee_count" value="{{ old('employee_count') }}" min="1" max="50000" class="form-control form-control-sm @error('employee_count') is-invalid @enderror">
                    @error('employee_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="mb-2">
                <label for="d_source">Source</label>
                <select id="d_source" name="source" class="form-select form-select-sm">
                    @foreach(['' => '— Select —', 'Phone call' => 'Phone call', 'Email' => 'Email', 'Referral' => 'Referral', 'Walk-in' => 'Walk-in', 'Event' => 'Event / exhibition', 'Social media' => 'Social media', 'Partner' => 'Partner', 'Other' => 'Other'] as $v => $t)
                        <option value="{{ $v }}" @selected(old('source') === $v)>{{ $t }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label for="d_message">What they need</label>
                <textarea id="d_message" name="message" rows="3" maxlength="1000" class="form-control form-control-sm @error('message') is-invalid @enderror" placeholder="Requirements, modules of interest, timeline…">{{ old('message') }}</textarea>
                @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="enq-sec">Follow-up</div>
            <div class="mb-2">
                <label for="d_assigned_admin_id">Assign to</label>
                <select id="d_assigned_admin_id" name="assigned_admin_id" class="form-select form-select-sm @error('assigned_admin_id') is-invalid @enderror">
                    <option value="">— Unassigned —</option>
                    @foreach($drawerAdmins as $id => $name)
                        <option value="{{ $id }}" @selected((string) old('assigned_admin_id', auth()->id()) === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
                @error('assigned_admin_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-1">
                <label for="d_note">Internal note</label>
                <input type="text" id="d_note" name="note" value="{{ old('note') }}" maxlength="2000" class="form-control form-control-sm" placeholder="e.g. Asked for a demo next week">
                <div class="hint">Saved to the enquiry's notes trail with your name and the time.</div>
            </div>
        </div>

        <div class="enq-drawer-foot">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="offcanvas">Cancel</button>
            <button type="submit" class="btn btn-sm btn-primary" id="enqCreateSubmit"><i class="bi bi-check2"></i> Create enquiry</button>
        </div>
    </form>
</div>

<style>
    .enq-drawer { width: 460px !important; max-width: 100vw; font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .enq-drawer .offcanvas-header { align-items: flex-start; padding: .85rem 1rem; border-bottom: 1px solid #e5e7eb; }
    .enq-drawer .offcanvas-title { font-size: .95rem; font-weight: 700; color: #111827; margin: 0; }
    .enq-drawer .offcanvas-title i { color: #2563eb; }
    .enq-drawer .sub { font-size: .7rem; color: #9ca3af; margin-top: .15rem; }
    .enq-drawer .offcanvas-body-wrap { display: flex; flex-direction: column; flex: 1; min-height: 0; }
    .enq-drawer .offcanvas-body { padding: .9rem 1rem; overflow-y: auto; flex: 1; }
    .enq-drawer label { font-size: .7rem; font-weight: 600; color: #4b5563; margin-bottom: .15rem; }
    .enq-drawer .form-control, .enq-drawer .form-select { font-size: .8rem; }
    .enq-drawer .req { color: #2563eb; }
    .enq-drawer .hint { font-size: .66rem; color: #9ca3af; margin-top: .2rem; }
    .enq-sec { font-size: .64rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #2563eb;
        padding-bottom: .3rem; margin-bottom: .55rem; border-bottom: 1px solid #eff6ff; }
    .enq-drawer-alert { font-size: .74rem; padding: .45rem .65rem; border-radius: .4rem; background: #eff6ff; color: #1d4ed8; margin-bottom: .75rem; }
    .enq-drawer-foot { display: flex; justify-content: flex-end; gap: .5rem; padding: .7rem 1rem; border-top: 1px solid #e5e7eb; background: #fafbfc; }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const el = document.getElementById('enqCreateDrawer');
        if (!el || !window.bootstrap) return;
        const drawer = bootstrap.Offcanvas.getOrCreateInstance(el);

        // Focus the first field when it opens; open straight away after a failed save or ?new=1.
        el.addEventListener('shown.bs.offcanvas', () => {
            (el.querySelector('.is-invalid') || document.getElementById('d_company_name')).focus();
        });
        if (el.dataset.open === '1') drawer.show();

        // Drop ?new=1 from the address bar so a refresh doesn't keep reopening it.
        el.addEventListener('hidden.bs.offcanvas', () => {
            const u = new URL(location.href);
            if (u.searchParams.has('new')) { u.searchParams.delete('new'); history.replaceState(null, '', u); }
        });

        // No double submit.
        document.getElementById('enqCreateForm').addEventListener('submit', function () {
            const b = document.getElementById('enqCreateSubmit');
            b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving…';
        });
    });
</script>
