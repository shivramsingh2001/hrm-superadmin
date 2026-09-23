# HRM Super Admin Panel — Phase-wise Execution Plan

> Platform-staff console for the HRM SaaS. Separate Laravel 12 app
> (`D:\HRMNEW\hrm-superadmin`) running on the **shared `hrm_22_04` database**.
> Companion to the SRS at `D:\HRMNEW\DOCS\SRS_SuperAdminPanel_revised (1).docx`.
>
> This document is the build roadmap. Phase 1 is done; Phases 2–9 are the plan.

---

## 1. Overview & Current State

### 1.1 What the panel is for

One place for **platform staff** (not tenant users) to run the commercial and operational
lifecycle of every tenant company on the HRM:

- Capture sales enquiries, record manual payments, and **create (provision) new tenants**.
- Manage **subscription plans** and **per-tenant feature toggles**.
- Monitor tenant health, **suspend / reactivate / delete** tenants, handle renewals and trials.
- **Impersonate** a tenant admin for support (the way staff change a tenant's day-to-day HRM
  settings — the panel itself does not rebuild those screens).
- Keep an **immutable audit trail** of every platform action.

### 1.2 Architecture

```
                 ┌─────────────────────────┐         ┌──────────────────────────────┐
  platform staff │  Super Admin Panel      │  reads/ │  Tenant HRM app  (hrm (3))   │  tenant users
  ───────────────▶  hrm-superadmin (L12)   │  writes │  {subdomain}.<domain>        ◀───────────────
                 │  :8001, own APP_KEY     │────┐    │  session auth, users table   │
                 └─────────────────────────┘    │    └──────────────────────────────┘
                             │                  │                    │
                             ▼                  ▼                    ▼
                       ┌──────────────────────────────────────────────────┐
                       │   MySQL  hrm_22_04   (single shared database)     │
                       │   tenants, users, companies, super_admins,        │
                       │   subscription_plans, tenant_subscriptions,       │
                       │   tenant_feature_overrides, audit_logs, ...       │
                       └──────────────────────────────────────────────────┘
```

### 1.3 Principles (hold across every phase)

1. **HRM owns tenant config; the panel owns the platform lifecycle.** Attendance policy, leave
   types, shifts, holidays, org structure, etc. are edited inside the HRM (via impersonation for
   staff). The panel directly manages only plan, features, employee limit, provisioning defaults,
   status, and billing.
2. **View here, edit there.** Every tenant setting is *visible* in the panel; changing a non-platform
   setting is done by impersonating the tenant admin.
3. **Audit every mutation.** Each create/update/delete by a super admin writes one immutable
   `audit_logs` row (`AuditLogger::record`).
4. **Idempotent provisioning.** A provisioning run that fails half-way can be re-run safely
   (upsert / existence checks, a per-tenant `provisioning_status`).
5. **The panel never migrates the shared DB.** `database/migrations/` stays empty. Any table the
   panel needs for itself is prefixed `sa_` and documented.

### 1.4 Shared tables already present in `hrm_22_04`

The SRS "new tables" were already applied: `super_admins`, `subscription_plans`,
`tenant_subscriptions`, `tenant_feature_overrides`, `inquiries`, `payment_logs`, `roles`,
`role_permissions`, `audit_logs`, `feature_registry`, `super_admin_notifications`,
`tenant_default_config`. `tenants` already carries `trial_ends_at`, `subscription_plan_id`,
`deletion_requested_at`, `deletion_requested_by`.

### 1.5 Phase 1 — Foundation ✅ DONE (what exists now)

| Area | Delivered |
|---|---|
| Project | `hrm-superadmin` (Laravel 12), `.env` → `hrm_22_04`, file session/cache, sync queue, own `APP_KEY`. No migrations. |
| Auth | `super_admins` session login; 5 attempts → 15-min `RateLimiter` lockout; `EnsureSuperAdminRole` middleware alias `sa_role`; `superadmin:set-password` / `superadmin:list` artisan commands. |
| Models | `SuperAdmin`, `SubscriptionPlan` (+`monthlyPrice()`), `TenantSubscription` (+`scopeActive`), `TenantFeatureOverride`, `Tenant`, `Company`, `Inquiry`, `PaymentLog`, `Role`, `RolePermission`, `AuditLog` (`$timestamps=false`), `FeatureRegistry` (string PK), `SuperAdminNotification`, `TenantDefaultConfig`, `User` (read-only). |
| Feature resolution | `config/features.php` (19 keys) → `FeatureRegistrySeeder` → `feature_registry`. `App\Services\FeatureService`: `enabled()`, `matrixForTenant()`, `bust()` — override → active subscription snapshot → config default → false; deprecated keys follow `replaced_by`; 5-min cache. |
| Audit | `App\Services\AuditLogger::record()` on every mutation. |
| Screens (Blade + Bootstrap) | Dashboard (tenant counts, open enquiries, est. MRR, recent tenants, expiring trials); Tenants list + detail tabs (Overview / Features / Users / Billing / Roles / Audit); feature override write+clear; suspend / reactivate (`sa_role:superadmin`); Plan CRUD (`sa_role:superadmin`, deactivate blocked while active subs exist); Feature Registry view + re-seed; Audit Log viewer + CSV export; Notifications list. |

Run: `composer install` → `php artisan db:seed --class=FeatureRegistrySeeder` →
`php artisan superadmin:set-password rajesh@shurt.io <pw>` → `php artisan serve --port=8001`.

---

## 2. Actors & Capability Matrix

### 2.1 Super-admin roles (`super_admins.role`)

| Capability | superadmin | support | billing |
|---|:--:|:--:|:--:|
| Login, dashboard, notifications | ✅ | ✅ | ✅ |
| View tenants, users, audit | ✅ | ✅ | ✅ |
| Enquiry queue: view / add note / change status | ✅ | ✅ | ✅ |
| Record payment | ✅ | ❌ | ✅ |
| Provision a new tenant | ✅ | ❌ | ❌ |
| Feature overrides (write) | ✅ | ❌ | ❌ |
| Subscription plan CRUD | ✅ | ❌ | ✅ (edit only, no delete) |
| Change a tenant's plan | ✅ | ❌ | ✅ |
| Suspend / reactivate tenant | ✅ | ❌ | ❌ |
| Request / cancel tenant deletion | ✅ | ❌ | ❌ |
| Impersonate a tenant user | ✅ | ✅ | ❌ |
| Manage super-admin accounts | ✅ | ❌ | ❌ |
| Feature registry add / deprecate | ✅ | ❌ | ❌ |

`superadmin` always passes `sa_role`. The matrix above is the target; Phase 1 enforces the subset
it needs (`sa_role:superadmin` on plan CRUD, suspend, activate, registry re-seed).

### 2.2 Tenant admin (`users.role = 'admin'` in the HRM)

The panel never logs in *as* a super admin into the HRM. What the panel does relative to a tenant
admin:

- **Creates** the first tenant-admin user during provisioning (random password, welcome email).
- **Impersonates** them for support (Phase 6) — the only way staff change HRM-side settings.
- **Views** their config (all tabs) read-only.
- Can **deactivate** a tenant user from the Users tab (Phase 3 add-on).

### 2.3 System actor

Scheduled jobs (trial-expiry alerts, auto-suspend, health snapshots, deletion executor) act as
`actor_type = 'system'` in `audit_logs` with `actor_id = NULL`.

---

## 3. Scope Boundary — who manages what

| Setting area | Panel (direct) | Impersonation | Provisioning default | HRM only |
|---|:--:|:--:|:--:|:--:|
| Subscription plan / features / employee limit | ✅ | | ✅ | |
| Tenant status (active/suspended/deleted) | ✅ | | | |
| Company identity (name, logo, subdomain, GST/PAN, timezone, currency) | view + set at provisioning | ✅ | ✅ | |
| Default shift / working days / grace / OT threshold | view | ✅ | ✅ (`tenant_default_config`) | |
| Attendance policy (`attendance_policies`) | view | ✅ | ✅ (seed row) | |
| Overtime settings (`overtime_settings`) | view | ✅ | ✅ (seed row) | |
| Leave types & balances | view | ✅ | ✅ (seed set) | |
| Holidays | view | ✅ | optional seed | |
| Departments / designations / branches | view | ✅ | | |
| Weekoffs (`user_weekoffs`) | view | ✅ | ✅ (default mask) | |
| Approval workflows | view | ✅ | | |
| API clients / webhooks | view | ✅ | | |
| Biometric terminals + enrollments | view | ✅ | | |
| Field-tracking add-on (seats, ping, retention) | ✅ (it's a paid add-on = a feature) | ✅ (tune values) | | |
| Tenant users / roles (RBAC) | view; create-role on behalf (Phase 5) | ✅ | ✅ (seed system roles) | |

---

## 4. Phase Plan

Each phase below lists: **Goal · Modules · Key tasks · DB tables touched · Acceptance criteria ·
Add-on points**. Phases are independently shippable; §8 gives the recommended order.

---

### Phase 1 — Foundation ✅ DONE

Recap only — see §1.5. Everything below builds on the auth guard, `FeatureService`, `AuditLogger`,
and the tenant/plan screens delivered here.

---

### Phase 2 — Enquiry → Payment → Tenant Creation (Provisioning) ✅ DONE

**Goal:** a super admin can take a company from "submitted a contact form" to a fully working tenant
(`{subdomain}.<domain>` live, admin can log in) without touching the DB by hand.

**Delivered (2026-09-10, verified over HTTP):**
`ContactController` (public `/contact`, honeypot); `EnquiryController` (queue + filters + SLA badges,
state-machine transitions, notes, assign, CSV export); payment recording → `payment_logs` +
status→`paid` + notification; `ProvisioningService` (single transaction, idempotent — re-run makes
no duplicates) writing `tenants`, `companies`, `tenant_subscriptions` (+ 19-key snapshot),
`tenant_feature_overrides`, `tenant_default_config`, a `shifts` row, the admin `users` row
(role=admin, generated `SHxxxxxx` employee id, one-time password), seeded `leave_types` (4) and
system `roles` + `role_permissions` (6 roles), welcome email (log mailer), `tenant.provisioned`
audit, and `inquiries.status = provisioned`; `ProvisioningController` single-form UI (prefilled from
the enquiry, JS plan-vs-override diff highlight); subdomain blocklist + format + uniqueness guards;
`config/provisioning.php` (defaults) + panel-only `sa_provisioning_runs` table (idempotency +
inquiry↔tenant link + step timeline). `config/database.php` uses a separate `sa_migrations` table so
this app's one migration never touches the HRM's migration history.
Still pending from the module list below: the 5-step wizard polish, receipt upload, bulk enquiry
actions, dry-run preview, re-send-welcome — all tracked as add-ons.

#### Modules

**2.1 Public enquiry form**
- Route `GET|POST /contact` (no auth) in the panel, or a small standalone page. Fields: company
  name, contact name, work email, phone, country, plan interest (dropdown of active
  `subscription_plans`), employee count (1–50000), message (≤1000).
- Spam protection: honeypot field + simple time-trap for MVP; reCAPTCHA v3 as an add-on.
- On submit → `inquiries` row (`status = 'new'`) + `super_admin_notifications` broadcast
  (`type = 'new_enquiry'`).

**2.2 Enquiry queue & lifecycle**
- `GET /enquiries` — filterable table (status, plan interest, date range, assigned admin, employee
  range). Columns: company, contact, plan interest, employees, status badge, created, days since
  last contact.
- SLA colouring: no contact > 24h amber, > 48h red (uses `last_contacted_at`).
- Row actions per the **state machine** (Appendix A): `new → contacted → negotiating →
  payment_sent → paid → provisioned → lost`; `lost → new` re-open (superadmin only).
- `PATCH /enquiries/{id}/status` — validates the transition, appends a timestamped note, sets
  `last_contacted_at` where relevant.
- Bulk: assign to admin, mark lost, export CSV.

**2.3 Payment recording**
- `POST /enquiries/{id}/payment` — amount, currency, `payment_mode` (upi/bank_transfer/cheque/
  cash/card), reference number (required), payment date, optional receipt upload, notes.
- Writes `payment_logs` (`inquiry_id` set, `collected_by` = actor), moves enquiry to `paid`,
  broadcasts `payment_confirmed`, surfaces a prominent **"Create tenant"** button.

**2.4 `ProvisioningService` — the core**
`provision(Inquiry|array $input): Tenant`, wrapped in a DB transaction, **idempotent** (each step
`updateOrCreate` / existence-checked), tracked by a `provisioning_status` value
(`pending → running → done → failed`) stored on the enquiry or a small `sa_provisioning_runs` table.

Steps, in order:
1. `tenants` row — company_name, display_name, subdomain (unique, validated), email, phone, country,
   timezone, date/time format, currency + symbol, week_start, `status = 'active'`,
   `subscription_plan_id`, `max_employees`, `trial_ends_at` (if trial plan).
2. `companies` row linked by `tenant_id` — legal_name, GST/PAN, address parts, logo.
3. `tenant_subscriptions` row — `plan_id`, `features_snapshot` = the plan's `features` JSON at this
   instant, `start_date`, `status` (`active` / `trial`), `trial_ends_at`, `created_by`.
4. `tenant_feature_overrides` — one row per toggle that differs from the plan default (with `reason`
   "provisioning override").
5. `tenant_default_config` — `default_shift_id`, `working_days`, `working_hours_per_day`,
   `grace_minutes`, `overtime_threshold_minutes`.
6. `shifts` row if the operator chose "create new shift" (name, start, end, grace, break).
7. Admin `users` row — `role = 'admin'`, `tenant_id`, generated `employee_id`, `status = 1`,
   `Hash::make(random)` password. Match the HRM's existing user-creation conventions
   (`hrm (3)/app/Http/Controllers/User/UserController.php`). **Note `users.tenant_id` is guarded** —
   use `forceFill` / `forceCreate`.
8. Seed `leave_types` — a default set (Casual, Sick, Earned…) with `credit_type` / `credit_value`;
   optionally seed opening `leave_balances`.
9. Optionally seed `holidays` — a national set for the tenant's country.
10. Seed `roles` + `role_permissions` — system roles `admin / hr / manager / employee`,
    `is_system = 1`, with the Appendix B permission matrix. (Data model only until Phase 5.4.)
11. Welcome email (`Mailable`) to the tenant admin — login URL `https://{subdomain}.<domain>`,
    temporary password, first-run checklist.
12. `audit_logs` — one `tenant.provisioned` row with the full input as `new_values`.

On failure: mark `provisioning_status = 'failed'` with the failed step; the retry re-runs from step
1 and every completed step is a no-op.

**2.5 Provisioning UI**
- MVP: a **single form** (`GET /tenants/create`, `POST /tenants`) with sections for company basics,
  plan, feature toggles (pre-filled from the plan), shift & working days, review.
- Enhancement: the SRS 5-step wizard with a debounced live subdomain-uniqueness check and a
  plan-vs-override diff view.

**2.6 Close the loop**
- On success set `inquiries.status = 'provisioned'`, store the new `tenant_id` on the enquiry (or a
  link row), redirect to the tenant detail page, broadcast `tenant_provisioned`.

#### DB tables touched
`inquiries`, `payment_logs`, `tenants`, `companies`, `tenant_subscriptions`,
`tenant_feature_overrides`, `tenant_default_config`, `shifts`, `users`, `leave_types`,
`leave_balances` (opt), `holidays` (opt), `roles`, `role_permissions`, `audit_logs`,
`super_admin_notifications`. New panel-only: `sa_provisioning_runs` (optional).

#### Acceptance criteria
- Submitting `/contact` creates an `inquiries` row and a notification.
- An enquiry can be walked `new → … → paid`; illegal transitions are rejected.
- Recording a payment creates a `payment_logs` row and flips status to `paid`.
- "Create tenant" from a paid enquiry produces: a `tenants` row with a unique subdomain, a
  `companies` row, an `active` `tenant_subscriptions` row whose `features_snapshot` matches the
  plan, a `tenant_default_config` row, an admin `users` row that can log into the HRM, seeded
  `leave_types` and system `roles`, a welcome email in the log, and a `tenant.provisioned`
  audit row.
- Re-running provisioning for the same enquiry creates **no duplicates**.
- The HRM at `{subdomain}.<domain>` resolves the new tenant (`TenantMiddleware`).

#### Add-on points
- Subdomain **reserved-word blocklist** (`www`, `api`, `admin`, `app`, `mail`, …) + format rules.
- Wildcard-DNS / local `hosts` note for dev; a "subdomain not yet routable" warning banner.
- **Dry-run** provisioning that returns the plan of writes without committing.
- **Re-send welcome email** button on tenant detail.
- Provisioning **timeline** widget (step-by-step, with the failed step highlighted).
- Auto-generate a strong admin password and show it **once** (like the bridge-key pattern).

---

### Phase 3 — Tenant Lifecycle ✅ DONE

**Goal:** everything after "active" — plan changes, trials, renewals, suspension, deletion.

**Delivered (2026-09-10, verified over HTTP):**
`App\Services\TenantLifecycleService` (changePlan / updateLimits / suspend / activate / renew /
extendTrial / convertTrial / requestDeletion / cancelDeletion / executeDeletion — each audits +
busts the feature cache where relevant). `TenantController` gained the matching actions with a
**Lifecycle** tab (change-plan form, employee-limit form, trial extend/convert, renew, subscription
history timeline, danger-zone deletion request/cancel + grace countdown badge on the header).
Suspension now takes a reason-code from `config/lifecycle.php`. **HRM side:**
`hrm (3)/app/Http/Controllers/Auth/AuthController::login` now rejects login when
`tenant.status` is not `active`/`trial` — so Suspend actually blocks tenant users (verified).
Scheduled commands (`routes/console.php`, needs the platform `schedule:run` cron):
`tenants:trial-expiry` (7/3/1-day admin email + `super_admin_notifications`, deduped per
tenant/day-count/day), `tenants:auto-suspend` (lapsed subscription / expired trial past grace),
`tenants:execute-deletions` (30-day grace → soft-delete + anonymise `users`/`companies` PII, keep
audit). All three take `--dry-run`. Add-ons still open: subscription-timeline polish, MRR/ARR
history, at-risk flag, churn-reason capture.

#### Modules

**3.1 Change plan** — `POST /tenants/{id}/change-plan` (`sa_role:superadmin` / billing).
Creates a **new** `tenant_subscriptions` row with a fresh `features_snapshot`, sets the previous
row's `end_date = today` and `status = 'expired'`, updates `tenants.subscription_plan_id`, calls
`FeatureService::bust($tenantId)`, audits `tenant.plan_changed`. Option: *effective now* vs
*effective next billing period*.

**3.2 Employee-limit override** — edit `tenants.max_employees` or
`tenant_subscriptions.max_employees_override`; audited. **Dependency:** the HRM must check the
effective limit on user-create (small guard in `UserController`).

**3.3 Suspension hardening** (extends Phase 1 suspend/activate)
- Reason catalogue (non-payment, abuse, request, trial-ended, other) + free text.
- **HRM prerequisite:** `hrm (3)/app/Http/Controllers/Auth/AuthController::login` must reject login
  when `tenant.status !== 'active'`, and show a branded "account suspended" page. Until this lands,
  suspension only flags the DB.
- Scheduled `auto-suspend` job: subscription past `end_date` with no renewal → `suspended` +
  notification.

**3.4 Trial management**
- Extend trial (`tenants.trial_ends_at` + active sub `trial_ends_at`), convert trial → paid
  (record payment, flip `status`).
- Daily job: 7 / 3 / 1 days before `trial_ends_at` → email tenant admin + `super_admin_notifications`
  (`trial_expiring`) + dashboard alert panel.

**3.5 Renewal** — `POST /tenants/{id}/renew`: record a `payment_logs` row (`tenant_id` set) and
either extend the active subscription's `end_date` or open a new row.

**3.6 Deletion lifecycle**
- `POST /tenants/{id}/request-deletion` (`sa_role:superadmin`, reason) → set
  `deletion_requested_at` / `deletion_requested_by`; tenant continues to work during grace.
- `POST /tenants/{id}/cancel-deletion` → clear the columns.
- Daily `execute-deletion` job: 30 days after the request → soft-delete the tenant, anonymise PII in
  `users` / `companies`, revoke sessions; keep `audit_logs`. All transitions audited.

#### DB tables touched
`tenant_subscriptions`, `tenants`, `payment_logs`, `super_admin_notifications`, `audit_logs`,
`users`/`companies` (anonymise). New: `sa_tenant_notes` (optional, shared with Phase 6).

#### Acceptance criteria
- Change-plan: new active sub row, previous expired, features re-resolved, cache busted, audited.
- Suspended tenant: DB flagged + (once the HRM guard ships) tenant logins blocked.
- Trial ending in ≤ 7 days appears on the dashboard and generates the notification schedule.
- Deletion request sets the columns; cancel clears them; the executor job soft-deletes + anonymises
  only after 30 days and never earlier.

#### Add-on points
- Subscription **timeline** on tenant detail (every plan/status change).
- **MRR/ARR history** and month-over-month movement.
- "**At-risk**" flag: no tenant logins in 14 days, or < 30% of licensed seats active.
- Capture a **churn reason** on suspension/deletion for reporting.
- **Grace-period countdown** badge on the tenant list for pending deletions.

---

### Phase 4 — Feature & Plan Governance ✅ DONE

**Goal:** feature overrides and plan edits become *real* controls, not just rows.

**Delivered (2026-09-10, verified over HTTP):**
- **4.1** `FeatureRegistryController@update` — activate / deactivate / deprecate (with `replaced_by`) /
  undeprecate a key; the registry page shows a config↔table diff (missing keys, orphan rows).
  `FeatureService` already follows `replaced_by` and treats deprecated as disabled (verified chain).
- **4.2** `sa_feature_override_templates` + `FeatureOverrideTemplate` model + `FeatureTemplateController`
  (index/store/destroy/apply) + a "Feature Templates" sidebar page. **Apply** writes
  `tenant_feature_overrides` for a set of tenants (explicit ids and/or a whole plan) in one audited
  op (`feature_template.applied` lists every tenant), busting each tenant's cache.
- **4.3** `sa_plan_versions` + `PlanVersion` model. Every `PlanController` create/update/revert
  snapshots the plan; the edit form shows "**N tenants on this plan — feature edits affect NEW
  subscriptions only**", a feature diff (added/removed) in the flash + audit, and a **version history
  table with per-version Revert**.
- **4.4 — FeatureService wired into the HRM app.** Copied `App\Services\FeatureService` +
  `config/features.php` into `hrm (3)` (DB-backed, `Cache::remember` 300 s). `feature:` route
  middleware (`EnsureFeatureEnabled`) gates the payroll and recruitment route groups; a `@feature`
  Blade directive gates the Payrolls sidebar block. Cross-app cache bust:
  `POST /internal/superadmin/feature-cache/bust` (shared-secret `X-Internal-Token`, CSRF-exempt);
  the panel's `FeatureService::bust()` fires it after every override/subscription write
  (`config/platform.php` + `SUPERADMIN_INTERNAL_TOKEN` / `HRM_INTERNAL_URL` in both `.env`s).
  Verified end-to-end: warm HRM cache → panel toggle → HRM reads the new value immediately.
  Remaining: extend `feature:` middleware + `@feature` gating to the other optional modules
  (projects, loans, expenses, kpi) — mechanical; and feature-adoption analytics (add-on).

#### Modules

**4.1 Feature registry management** — `feature_registry` CRUD (superadmin): add a key, mark
`deprecated_at` + `replaced_by`, toggle `is_active`. Keep `config/features.php` and the table in
sync (a diff view + "import missing from config" action; `FeatureService` already treats deprecated
keys as disabled and follows `replaced_by`).

**4.2 Bulk feature-override templates** — new `sa_feature_override_templates` (name, JSON map
`feature_key => bool`). "Apply template" writes/updates `tenant_feature_overrides` for a selected set
of tenants in **one audited operation** and busts each tenant's cache. Example: "enable
`recruitment` + `onboarding` for all Enterprise tenants".

**4.3 Plan versioning & snapshot semantics** — editing a plan's `features` must **not** retro-change
existing tenants (they hold their own `features_snapshot`). Show a "this changes the plan for *new*
subscriptions only; N existing tenants unaffected" note and a before/after diff. Optionally keep a
`sa_plan_versions` history.

**4.4 Wire `FeatureService` into the HRM app** — *the milestone that makes overrides matter.*
Pick one integration:
- **(a) Copy** `FeatureService` + `config/features.php` into `hrm (3)` and resolve against the same
  tables (simplest; two copies to keep aligned).
- **(b) Internal API**: the HRM calls `GET {panel}/internal/features/{tenantId}` (shared secret,
  cached) — one source of truth, network dependency.
- **(c) Shared composer package** — cleanest long-term, most setup.
Then in the HRM: a `feature:{key}` route middleware and nav-item gating that call the resolver;
**cache invalidation across both apps** (shared Redis, or the panel calls an HRM bust endpoint after
every override/subscription write).

#### DB tables touched
`feature_registry`, `tenant_feature_overrides`, `tenant_subscriptions`, `subscription_plans`,
`audit_logs`. New: `sa_feature_override_templates`, `sa_plan_versions` (optional).

#### Acceptance criteria
- A deprecated key with `replaced_by` resolves to the replacement everywhere.
- Applying a template to 3 tenants writes 3× overrides, busts 3 caches, and logs one audit row that
  names all 3.
- Editing a plan's features leaves every existing tenant's resolved features unchanged.
- After 4.4: turning `payroll` off for a tenant in the panel hides the Payroll module in that
  tenant's HRM within the cache TTL (or immediately if a bust endpoint is wired).

#### Add-on points
- **Feature adoption report** — which tenants actually use each module (join to the feature's tables
  from Appendix C).
- "**Tenants affected**" preview before saving a plan edit.
- Per-feature **kill switch** (disable a broken module across all tenants fast).

---

### Phase 5 — Tenant RBAC ✅ DONE (5.4 = foundation, not yet enforced)

**Goal:** real roles & permissions per tenant, replacing the HRM's `users.role` string.

**Delivered (2026-09-10, verified):**
- **5.1** System roles + the SRS §9.2 matrix are seeded at provisioning (Phase 2). New HRM command
  `rbac:sync-roles` backfilled the 4 pre-existing tenants (16 roles seeded, 46 users linked).
- **5.2 / 5.3** Panel: the tenant **Roles** tab is now a full **module × action permission matrix**
  per role. `RoleController` (superadmin) — create custom role, save matrix (system roles locked),
  **clone a role into another tenant**, delete (blocked when users hold the role).
  `config/rbac.php` (modules / actions / system slugs). Every change audits
  (`role.created` / `role.permissions_updated` / `role.cloned` / `role.deleted`).
- **5.4 — HRM foundation (additive, safe).** Migration adds `users.role_id` (nullable, alongside the
  varchar `role`). `App\Services\RbacService::can(user, module, action)` — reads the shared
  `roles` / `role_permissions` (600 s cache), `admin` keeps god-mode. `@permission('payroll','view')`
  Blade directive + a `permission:` route-middleware alias — **registered but applied nowhere yet**
  (`role:` middleware still guards everything; migrate screen-by-screen later).
  `UserRoleObserver` dual-writes `role_id` when the varchar `role` changes. The panel's
  `RoleController` calls the HRM's `/internal/superadmin/feature-cache/bust` with `role_id` so
  permission edits are visible immediately (`PlatformClient::bustRole`).
  Verified `can()` for employee / manager / admin / a custom role, the dual-write observer, clone,
  and the system-role delete guard.
  Remaining: the actual `role:` → `permission:` swap across HRM screens (incremental); permission
  presets (add-on).

#### Modules

**5.1 Seed on provisioning** — system roles `admin / hr / manager / employee` (+ `finance`,
`recruiter` as non-system) with the Appendix B `(module, action)` matrix into `roles` /
`role_permissions`.

**5.2 Panel: view RBAC** — tenant detail "Roles" tab shows every role and its permission matrix
(rows = modules, columns = view/create/edit/delete/approve/export). Read-only first.

**5.3 Panel: manage RBAC on a tenant's behalf** — create custom roles, edit the matrix; **system
roles are locked** (no edit / delete). Every grant/revoke audited.

**5.4 HRM migration `users.role` → `role_id`** (highest-risk item):
- Add `users.role_id` (FK → `roles.id`, nullable) via an **HRM** migration.
- Backfill: map each existing `role` string to the tenant's seeded system role.
- **Dual-write** period: keep `role` in sync while code migrates.
- Implement the SRS §9.1 `can(user, module, action, tenantId)` resolver in the HRM
  (super-admin bypass, tenant check, `FeatureService::enabled`, then role-permission union).
- Swap `role:` middleware usages to `can:` incrementally.

#### DB tables touched
`roles`, `role_permissions`, `users` (HRM migration adds `role_id`), `audit_logs`.

#### Acceptance criteria
- A freshly provisioned tenant has 4 system roles with the correct permission matrix.
- Panel can add "HR Manager (custom)" to a tenant and grant `payroll:view` + `leave:approve`.
- After 5.4: an HRM user with only `attendance:view` cannot open the payroll screen; a super admin
  still can (bypass).

#### Add-on points
- **Permission presets** (e.g. "Finance", "Recruiter") one-click.
- **Clone a role** from one tenant to another.
- Diff a tenant's roles against the default matrix ("drifted from standard").

---

### Phase 6 — Impersonation & Support ✅ DONE

**Goal:** the sanctioned way staff change a tenant's HRM settings and reproduce issues — unlocks the
"manage all tenant settings" requirement without duplicating every HRM screen.

**Delivered (2026-09-10, verified end-to-end):**
- **6.1** Panel `ImpersonationController` — `start` (pick a tenant user → `impersonation_sessions`
  row: 64-char token, `expires_at = now()+60 min` from `config('platform.impersonation_ttl_minutes')`,
  IP; auto-closes the admin's prior live session), `end`, `index` (all sessions + Force-end).
  `sa_role:support` (support + superadmin, not billing). Audits `impersonation.started`.
- **6.2 cross-app handoff.** `start` redirects to `{HRM}/impersonate/consume?token=…&tenant=…`.
  HRM `Impersonation\ImpersonationController@consume` — validates against the shared table
  (not ended, not past, timestamps parsed as **UTC** since the panel runs UTC and the HRM
  Asia/Kolkata), **single-use** via `Cache::put("impersonation:consumed:{token}")`, then
  `Auth::login($target)` + session tenant + an `impersonation` session bag. `POST /impersonate/end`
  ends the row, logs out, redirects to `config('services.superadmin.panel_url')/impersonation`.
  Persistent banner (`client/layout/impersonation-banner`, `@includeWhen`) — "You are impersonating
  X — End session".
- **6.3 guardrails.** `EnforceImpersonationExpiry` middleware (appended to the HRM `web` group):
  session fast-path + an authoritative `impersonation_sessions` lookup each request — hard-expires at
  60 min **and** kills the HRM session the instant a super admin Force-ends from the panel (verified).
  Consume/end write `audit_logs` with `actor_type = super_admin_impersonating` +
  `impersonating_user_id`. HRM already hides passwords / one-shot API secrets; no card data exists.
- **6.4 Support tab** on the tenant page — support notes (`sa_tenant_notes` + `TenantNote`, add/
  delete), a tenant-user picker that starts impersonation, recent impersonations, recent logins.
  Config: `SUPERADMIN_PANEL_URL` (HRM `.env`), `HRM_WEB_URL` + `IMPERSONATION_TTL_MINUTES` (panel).
  Add-ons still open: read-only impersonation, support-role needs approval, per-action audit of
  everything done while impersonating (needs a global terminate hook).

#### Modules

**6.1 Sessions** — `POST /tenants/{id}/impersonate` (choose a tenant user) → `impersonation_sessions`
row (`super_admin_id`, `tenant_user_id`, `tenant_id`, `session_token`, `started_at`,
`expires_at = +60 min`, `ip_address`). `DELETE /impersonation/{token}` ends it (`end_reason =
manual`); a job expires stale rows (`expired`).

**6.2 Cross-app handoff** — the panel redirects to
`https://{subdomain}.<domain>/impersonate/consume?token=…`. The HRM validates the token (shared
secret / signed payload, single-use, not expired), starts a session **as the tenant user**, and
shows a persistent banner: *"You are impersonating {Name} — End session"*. Ending returns to the
panel.

**6.3 Guardrails** — during impersonation the HRM hides passwords, payment card details, and secret
keys; every action is audited `actor_type = super_admin_impersonating` with
`impersonating_user_id`; hard 60-min expiry regardless of activity; the super admin's IP is on the
session row.

**6.4 Support tab** — per-tenant notes (`sa_tenant_notes`), a one-click "Impersonate admin" button,
recent tenant activity, and an "open HRM as read-only" option (add-on).

#### DB tables touched
`impersonation_sessions`, `audit_logs`. New: `sa_tenant_notes`. HRM side: session handling +
banner + audit.

#### Acceptance criteria
- Starting impersonation creates a session row with a 60-min expiry and lands the operator in the
  tenant's HRM as the chosen user with the banner visible.
- Every change made while impersonating writes an audit row tagged `super_admin_impersonating`.
- The session cannot be used after 60 minutes or after "End session".

#### Add-on points
- **Read-only impersonation** mode (view without write).
- `support` role requires a `superadmin` approval to start impersonation.
- Export the full action log of one impersonation session.

---

### Phase 7 — Security Hardening ✅ DONE (7.3 deferred to Phase 9)

**Goal:** meet the SRS security NFRs before the panel handles real customer money and data at scale.

**Delivered (2026-09-10, verified over HTTP):**
- **7.1 Mandatory TOTP.** `App\Services\Totp` (pure-PHP RFC 6238, SHA-1/6-digit/30 s, ±1 step).
  Login is now email+password → **`/2fa/setup`** (first login: QR + manual key, confirm a code) or
  **`/2fa`** (verify). `Auth::login` only happens after the code checks out. `super_admins.totp_secret`
  is `encrypted` (model cast) — column widened to VARCHAR(512) via a panel-owned migration.
  `superadmin:reset-totp {email}` for recovery; no UI disable path.
- **7.2 IP allowlist.** `SuperAdminGuard` middleware (appended to the `web` group) — per-account
  `super_admins.allowed_ips` **and** a global `SUPER_ADMIN_IP_WHITELIST`; a request from a
  disallowed IP is logged out + redirected. Verified.
- **7.4 Audit integrity.** `AuditLogger` HMAC-signs every row into the side table
  `sa_audit_signatures` (`AUDIT_LOG_HMAC_KEY`, shared with the HRM, which also signs its
  impersonation writes). `php artisan audit:verify [--since=] [--sign-missing]` recomputes and
  reports OK / UNSIGNED / **TAMPERED** with row ids. Verified: altering one row's `action` is
  detected. Partitioned 5-yr retention + append-only DB user are DBA tasks — out of app scope.
- **7.5 Session hardening.** `SESSION_LIFETIME=240` (4 h idle) + a **12 h hard cap** enforced by
  `SuperAdminGuard` via `sa_login_at`. Brute-force lockout (5/15 min) now sends `SecurityAlertMail`
  + a `security_alert` notification (verified). New-IP sign-in raises the same alert.
  "Invalidate all sessions on password change" — noted, needs an in-app change form (none yet).
- **7.6 Payment-ref encryption.** `payment_logs.reference_number` uses a `TolerantEncrypted` cast
  (encrypts on write; on read decrypts or returns legacy plaintext). `php artisan payment:encrypt-refs`
  backfilled existing rows. Verified: model reads plaintext, DB stores ciphertext.
- **7.3 Separate JWT secret** — ✅ paid in Phase 9. `SUPER_ADMIN_JWT_SECRET` (80 hex) generated,
  asserted distinct from `APP_KEY` and the HRM JWT secret; used by `App\Services\Jwt`.

#### Modules
- **7.1 Mandatory TOTP** — RFC 6238, 6 digits, 30 s. `totp_secret` **encrypted at rest**
  (`Crypt`), enrolment forced on first login, cannot be disabled. Login becomes email+password →
  TOTP step.
- **7.2 IP allowlist** — honour `super_admins.allowed_ips` (JSON) in `EnsureSuperAdminRole`; plus a
  load-balancer / firewall restriction in production. `SUPER_ADMIN_IP_WHITELIST` env for a global
  fallback.
- **7.3 Separate JWT secret** — delivered in Phase 9; `SUPER_ADMIN_JWT_SECRET` differs from the HRM's.
- **7.4 Audit integrity** — HMAC-sign each `audit_logs` row (`AUDIT_LOG_HMAC_KEY`), a
  `audit:verify` command to detect tampering, a yearly-partitioned retention table (5 years), and a
  DB user with no `UPDATE`/`DELETE` on `audit_logs`.
- **7.5 Session hardening** — 4 h idle / 12 h hard expiry; invalidate all sessions on password
  change; brute-force lockout already exists — add the alert email; login-from-new-IP alert.
- **7.6 Payment reference encryption** — encrypt `payment_logs.reference_number` at rest.

#### DB tables touched
`super_admins`, `audit_logs` (+ partitioned archive), `payment_logs`.

#### Acceptance criteria
- A super admin cannot complete login without a valid TOTP code.
- A request from an IP outside a super admin's `allowed_ips` is rejected.
- `audit:verify` flags a row whose stored HMAC doesn't match its content.
- Changing a password logs out every other session for that account.

#### Add-on points
- CSP + HSTS headers; per-session request rate limit (100/min).
- Audit **reads** of sensitive data (payment details, TOTP enrolment).
- Optional WebAuthn / passkey as a second factor.

---

### Phase 8 — Dashboards, Reporting & Ops ✅ DONE

**Goal:** fast, trustworthy operational visibility; NFR-01 (2 s dashboard @ 10k tenants).

**Delivered (2026-09-10, verified over HTTP):**
- **8.1** `sa_platform_metrics` + `php artisan metrics:snapshot` (scheduled 01:00, idempotent per
  day): total/active/suspended/trial tenant counts, **MRR / ARR** (Σ active-subscription
  `SubscriptionPlan::monthlyPrice`), new-tenants-this-month, churned-this-month, enquiry **funnel**
  (`inquiries` by status), **new-tenants-by-month** (last 12), and **avg enquiry → live days**
  (`sa_provisioning_runs` ⋈ `inquiries`). The dashboard reads the latest row (computes one on the
  fly if none) — no live aggregation.
- **8.2** Dashboard charts via Chart.js (CDN): new-tenants-per-month bar + horizontal enquiry-funnel
  bar, data injected as JSON from the metric row.
- **8.3** `sa_tenant_health` (per-tenant, refreshed by the same command): users total/active,
  logins-30d, last-activity, seat limit + utilisation bar, **`is_stale`** (no login in 14 days).
  New **Tenant Health** page (sortable) + an "At-risk" panel on the dashboard.
- **8.4** Notification fan-out — `new_enquiry`, `payment_confirmed`, `tenant_provisioned`,
  `provisioning_failed` (added here), `trial_expiring`, `tenant_suspended`, `tenant_deletion_requested`,
  `security_alert` all broadcast via `NotificationService`.
- **8.5** `GET /health` (public) — DB + cache probe, `{status,db,cache}` 200/503. `audit:verify`
  scheduled daily 04:00 (fails loudly on tampering). `metrics:digest` emails the weekly summary
  (`MetricsDigestMail`) to every active super admin, scheduled Monday 07:30.
  Metrics/Grafana endpoint + CSV-everywhere are add-ons.

#### Modules
- **8.1 Pre-computed metrics** — a nightly job writes `sa_platform_metrics` (date, MRR, ARR,
  tenant counts by status, funnel counts, new-tenants-this-month, churn). The dashboard reads the
  latest row (or a 5-min cache) instead of aggregating live.
- **8.2 Charts** — new tenants per month (12 mo), enquiry funnel (new→…→provisioned/lost), simple
  cohort retention.
- **8.3 Tenant health snapshot** — nightly per-tenant: logins last 30 d, active users, last
  activity timestamp, seat utilisation, `stale` flag → `sa_tenant_health`.
- **8.4 Notification fan-out** — real `super_admin_notifications` on: enquiry received, payment
  confirmed, provisioning failed, trial expiring, login from new IP, tenant suspended.
- **8.5 Ops** — `/health` (DB + cache connectivity), failed-job alerting (log channel → Slack/email
  hook), "avg enquiry → provisioned" SLA metric on the dashboard.

#### DB tables touched
New panel-only: `sa_platform_metrics`, `sa_tenant_health`. Reads across `tenants`, `users`,
`tenant_subscriptions`, `inquiries`, `audit_logs`.

#### Acceptance criteria
- Dashboard renders from the pre-computed row in < 500 ms with 10k synthetic tenants.
- A provisioning failure raises a notification within a minute.
- `/health` returns 200 with `{db: ok, cache: ok}`.

#### Add-on points
- CSV/Excel export on every list.
- Weekly email digest to all super admins.
- Metrics endpoint for Grafana/Prometheus.

---

### Phase 9 — API + SPA ✅ DONE (API delivered; full SPA deferred)

**Goal:** expose the panel's operations over a JSON API so external automation and a future SPA can
drive them, without touching the Blade panel which stays the always-available ops fallback.

**Delivered:**

- **`App\Services\Jwt`** — hand-rolled HS256 encode/decode. `issue(array $claims, int $ttl): string`,
  `verify(?string): ?array` (checks HMAC + `exp`). Secret = `SUPER_ADMIN_JWT_SECRET` (80 hex chars in
  `.env`), asserted distinct from `APP_KEY` and the HRM's JWT secret (SRS §7.3 / Phase 7.3 debt paid
  here). `iss = hrm-superadmin`.
- **Middleware**
  - `ForceJsonResponse` (prepended to the `api` group) — forces `Accept: application/json` so
    validation/abort responses are JSON, never HTML redirects.
  - `ApiJwtAuth` (alias `api.jwt`) — verifies the access token, loads the `SuperAdmin`, re-checks
    `is_active` + IP allowlist, then `setUserResolver()` **and** `Auth::setUser()` so services that
    call `Auth::id()` (e.g. `ProvisioningService`, `AuditLogger`) work under the API guard.
  - `ApiSuperAdminRole` (alias `api.sa_role:<role>`) — per-route role gate; `superadmin` always
    passes; returns `{error:{code:'insufficient_role'}}` 403.
- **Controllers** (`app/Http/Controllers/Api/`) — thin transport over the existing services:
  `ApiController` (base `ok()` / `fail()` envelope), `AuthController` (login with password + TOTP +
  RateLimiter 5/900s, refresh, logout, me), `DashboardController`, `EnquiryController` (index/store/
  show/updateStatus state-machine/payment), `TenantController` (index/show/store=provision/suspend/
  activate/changePlan/updateLimits/features/updateFeatures/impersonate), `PlanController`
  (index/store/update/destroy), `RoleController` (index/store/updatePermissions), `AuditLogController`
  (filtered index).
- **`routes/api.php`** — 29 routes under `/api/v1/super-admin/`. Public: `auth/login`, `auth/refresh`,
  `health`. Behind `api.jwt`: everything else, with `api.sa_role:superadmin` on tenant/plan/role
  writes, `api.sa_role:support` on impersonate, `api.sa_role:billing` on enquiry payment.
- **`resources/views/api-console.blade.php`** ("SPA-lite") — a single-page fetch client (token in
  `sessionStorage`, quick links for dashboard/tenants/plans/enquiries/audit/roles), linked from the
  sidebar as **API Console**. No build step. The full React/Vue SPA at `admin.<domain>` is **deferred**
  — the doc frames it as optional and this page covers the demonstrable-client need.

**Response envelope:** success `{ "data": … }`; error `{ "error": { "code", "message", … } }`.
**Tokens:** access `{sub, role, typ:'access'}` TTL 3600s; refresh `{sub, typ:'refresh'}` TTL 43200s.

**Verified over HTTP (all green):**

| Check | Result |
|---|---|
| `POST auth/login` (email + password + `code`) → access + refresh JWT | ✅ |
| garbage / expired / refresh-typ token on a protected route → 401 | ✅ |
| `auth/refresh` → fresh pair, `expires_in: 3600` | ✅ |
| all 7 GET endpoints (dashboard, tenants, plans, enquiries, audit-logs, roles, me) → 200 | ✅ |
| `POST tenants` (provision), `suspend`, `activate`, `change-plan`, `PUT features`, `impersonate` (returns `consume_url`) → 200/201 | ✅ |
| `POST plans` → 201 · `POST roles` → 201 · `PUT roles/{id}/permissions` → 200 | ✅ |
| `PUT roles/{id}/permissions` with a display-name module (`"Attendance"`) → 422 | ✅ |
| `PUT roles/{id}/permissions` on a system role → 403 `locked` | ✅ |
| role gate: `support`-role calls `POST /tenants` → 403 `insufficient_role` | ✅ |
| `SUPER_ADMIN_JWT_SECRET` len 80, distinct from `APP_KEY` | ✅ |

**Bugs found & fixed during Phase 9:**

- Provision via API failed `tenant_subscriptions.created_by cannot be null` — `Auth::id()` was null
  because `ApiJwtAuth` only set the user *resolver*. Fix: also call `Auth::setUser($admin)`.
- `POST plans` / `POST roles` returned HTML redirects instead of JSON 422 — no `Accept` header. Fix:
  `ForceJsonResponse` middleware prepended to the `api` group.
- `PlanController::store` — `$data['slug']` undefined-key 500 when the nullable field is absent. Fix:
  `($data['slug'] ?? null) ?: Str::slug($data['name'])`.
- `RoleController::updatePermissions` — `in:` rule built from `config('rbac.modules')` (an
  *associative* array in the panel) so it validated against module **display names**, rejecting every
  real key with 422. Fix: `implode(',', array_keys(config('rbac.modules')))`.

**Deferred / follow-ups:** full React/Vue SPA; token denylist on logout (currently stateless);
OpenAPI spec; per-endpoint rate limits beyond login.

---

## 5. Tenant Settings Inventory (reference)

Every per-tenant configurable in the HRM today, and who owns it.
**P** = Panel direct · **I** = via Impersonation · **PD** = set as a Provisioning Default · **H** = HRM only.

| Group | Setting | Backing table(s) | Owner |
|---|---|---|:--:|
| **Identity** | company / display name, subdomain, custom domain, logo | `tenants`, `companies` | P (provision) · I |
| | email, phone, address, city/state/country, pincode | `tenants`, `companies` | I · PD |
| | GST number, PAN number | `companies` / `tenants` | I · PD |
| | timezone, date format, time format, currency, currency symbol, week start | `tenants` | I · PD |
| **Commercial** | subscription plan, plan id | `tenants`, `tenant_subscriptions` | **P** |
| | feature toggles (19 keys) | `tenant_feature_overrides`, `tenant_subscriptions.features_snapshot` | **P** |
| | max employees / employee-limit override | `tenants.max_employees`, `tenant_subscriptions.max_employees_override` | **P** |
| | trial end date | `tenants.trial_ends_at`, `tenant_subscriptions.trial_ends_at` | **P** |
| | status (active/suspended/deleted) | `tenants.status`, `deletion_requested_at/by` | **P** |
| **Attendance** | present/half-day ratios, fallback hours, full-day min, OT after / multiplier, grace, rounding, late-halfday, monthly late allowance, min rest, max daily, sandwich leave | `attendance_policies` | I · PD (seed) |
| | custom-shifts toggle, default company shift | `tenants.custom_shifts_enabled`, `tenants.default_shift_id`, `shift_settings` | I · PD |
| | shift definitions | `shifts` | I · PD (one seed) |
| | weekoffs / default weekoff mask | `user_weekoffs`, `tenants.default_weekoff_days` | I · PD |
| **Leave** | leave types + credit rules | `leave_types` | I · PD (seed set) |
| | opening balances, leave credits | `leave_balances` | I · PD (opt) |
| | notice period, monthly leave count | `tenants.notice_period`, `tenants.leaves` | I · PD |
| **Overtime** | rate multiplier, max/day, max/month, require approval, auto-approve limit | `overtime_settings` | I · PD (seed) |
| **Org structure** | departments | `departments` | I |
| | designations | `designations` | I |
| | branches (feature-gated by `branches`) | `branches` | I |
| **Holidays** | holiday calendar | `holidays` | I · PD (opt seed) |
| **Add-ons** | field-tracking enabled / seats / ping seconds / retention days | `tenants.field_tracking_*` | **P** (it's a paid add-on) · I (tune) |
| | biometric terminals + enrollments + roster | `biometric_devices`, `biometric_enrollments` | I |
| **Integrations** | approval workflows + steps | `approval_workflows`, `approval_workflow_steps` | I |
| | API clients (keys) | `api_clients` | I |
| | webhooks | `webhooks` | I |
| **RBAC** | roles + permission matrix | `roles`, `role_permissions` | **P** (Phase 5) · I |
| | tenant users, their roles, active/inactive | `users` | view in P; deactivate in P (add-on); manage in I |

---

## 6. Data-Model Touchpoints by phase

| Table | P2 | P3 | P4 | P5 | P6 | P7 | P8 |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| `inquiries` | W | | | | | | R |
| `payment_logs` | W | W | | | | enc | R |
| `tenants` | W | W | | | | | R |
| `companies` | W | anon | | | | | |
| `tenant_subscriptions` | W | W | R/W | | | | R |
| `tenant_feature_overrides` | W | | W | | | | |
| `tenant_default_config` | W | | | | | | |
| `shifts` | W | | | | | | |
| `users` | W | anon/deact | | +`role_id` | R | | R |
| `leave_types` / `leave_balances` | W | | | | | | |
| `holidays` | W (opt) | | | | | | |
| `roles` / `role_permissions` | W (seed) | | | W | | | |
| `feature_registry` | | | W | | | | |
| `impersonation_sessions` | | | | | W | | |
| `audit_logs` | W | W | W | W | W | HMAC + archive | R |
| `super_admin_notifications` | W | W | | | | W | W |
| **new** `sa_provisioning_runs` | W | | | | | | |
| **new** `sa_tenant_notes` | | W | | | W | | |
| **new** `sa_feature_override_templates` | | | W | | | | |
| **new** `sa_platform_metrics` / `sa_tenant_health` | | | | | | | W |

`W` = writes, `R` = reads, `anon` = anonymises, `enc` = encrypts, `deact` = deactivates.
**Rule:** the panel never migrates HRM-owned tables; only `sa_*` tables and the one HRM-side
`users.role_id` migration (Phase 5.4, run from the HRM repo).

---

## 7. Risks, Dependencies & Sequencing

| Risk / dependency | Mitigation |
|---|---|
| Feature overrides do nothing visible until **Phase 4.4** wires `FeatureService` into the HRM. | Ship 4.4 right after Phases 2–3; keep it small (option (a) copy). |
| **Suspension** doesn't block tenant logins until the HRM `AuthController` checks `tenant.status`. | One-line guard in `hrm (3)` — make it a prerequisite of Phase 3.3. |
| **RBAC migration (5.4)** touches every `role:` middleware in the HRM. | Dual-write `role` + `role_id`; migrate `can:` usages screen-by-screen; feature-flag the resolver. |
| **Impersonation (6.2)** needs cross-app trust. | Single-use signed token + shared secret; short TTL; audit both sides. |
| Provisioning partial failure leaves orphan rows. | Idempotent steps + `provisioning_status`; a "clean up failed provision" action. |
| Shared DB — a panel migration could corrupt HRM schema. | CI check: `database/migrations/` in `hrm-superadmin` must contain only `sa_*` creates. |
| Two `FeatureService` copies drift (option a). | A test in both repos asserting `config/features.php` keys match `feature_registry`. |

---

## 8. Recommended delivery order & sizing

| Order | Phase | Size | Why here | Key dependency |
|--:|---|:--:|---|---|
| 1 | **P2** Enquiry → Provisioning | **XL** | Can't onboard tenants without it; everything else assumes tenants exist. | HRM `TenantMiddleware` (exists) |
| 2 | **P3** Lifecycle | **L** | Plan changes, trials, suspension, deletion are daily ops. | HRM suspended-login guard |
| 3 | **P4.4** Wire features into HRM | **S–M** | Small change, unlocks the value of Phase 1 + all overrides. | HRM repo access |
| 4 | **P6** Impersonation | **L** | The sanctioned path to "manage all tenant settings"; unblocks support. | HRM session handshake |
| 5 | **P5** RBAC | **XL** | High value but riskiest; do after impersonation so support isn't blocked on it. | HRM `users.role_id` migration |
| 6 | **P4.1–4.3** Registry / templates / versioning | **M** | Governance polish once features are live. | P4.4 |
| 7 | **P7** Security hardening | **M–L** | Before real scale / real payments; TOTP first. | — |
| 8 | **P8** Dashboards / ops | **M** | Nice once volume grows; not blocking. | — |
| 9 | **P9** API + SPA | **XL** | Optional; only if a richer UX is needed. | P2–P8 stable |

---

## Appendix A — Enquiry status state machine (SRS §14.2)

| From | Allowed → | Who | Side effect |
|---|---|---|---|
| `new` | `contacted`, `lost` | any | notify assigned admin |
| `contacted` | `negotiating`, `payment_sent`, `lost` | any | log contact timestamp |
| `negotiating` | `payment_sent`, `lost` | any | — |
| `payment_sent` | `paid`, `negotiating` | any | payment link logged |
| `paid` | `provisioned` | any | fires `TenantProvisioningRequested` |
| `provisioned` | — (terminal) | system | tenant active, welcome email |
| `lost` | `new` (re-open) | superadmin | audit entry |

## Appendix B — Default system-role permission matrix (SRS §9.2)

Modules: attendance, leave, payroll, tasks, projects, recruitment, onboarding, offboarding,
expenses, loans, meetings, announcements, reports, settings.
Actions: view, create, edit, delete, approve, export.

| Module | admin | hr | manager | employee | finance | recruiter |
|---|---|---|---|---|---|---|
| Attendance | all | all | view/approve | own | — | — |
| Leave | all | all | view/approve | apply | — | — |
| Payroll | all | view | — | own slip | all | — |
| Tasks | all | view | all | own | — | — |
| Recruitment | all | all | view | — | — | all |
| Loans | all | view | — | apply | all | — |
| Settings | all | — | — | — | — | — |
| Reports | all | hr reports | team reports | — | finance | — |

`admin`, `hr`, `manager`, `employee` are `is_system = 1` (locked). `finance`, `recruiter` seeded as
editable examples.

## Appendix C — Feature-key registry (19 keys)

| Key | Free default | Module tables (adoption signal) |
|---|:--:|---|
| `attendance` | ✅ | attendances, attendance_logs, attendance_summaries, attendance_tracks, attendance_regularizations |
| `leave_management` | ✅ | leaves, leave_types, leave_balances, leave_transactions |
| `payroll` | ❌ | monthly_payrolls, payroll_masters, user_payrolls, payroll_components |
| `task_management` | ✅ | tasks, task_assigns, task_updates, task_approvals |
| `project_management` | ❌ | projects, project_assigns |
| `recruitment` | ❌ | candidates, job_openings, job_applications, interviews, job_offers |
| `onboarding` | ❌ | onboarding_assignments, onboarding_tasks, onboarding_task_items |
| `offboarding` | ❌ | offboarding_requests, exit_interviews |
| `expense_management` | ❌ | expenses, expense_types, expense_payments, expense_budgets |
| `expense_payroll_link` | ✅ | expense_payroll_links + expenses.payout_channel — approved reimbursements can be sent to payroll and paid with the salary instead of a voucher. Added 2026-09-29; **default OFF**; effective only with `payroll` + `expense_management` + the dynamic payroll engine (`tenants.payroll_dynamic_ui_enabled`). |
| `expense_bulk_payment` | ✅ | expense_payment_batches (vouchers), expense_voucher_sequences — pay many approved advances/reimbursements in one voucher (bank CSV, PDF, void) + bulk approve/reject. Added 2026-09-27; default ON so every company with expenses gets it, switchable per company/plan. |
| `loan_management` | ❌ | loans, loan_repayments, loan_categories |
| `meetings` | ✅ | meetings, meeting_participants |
| `announcements` | ✅ | announcements |
| `daily_reports` | ✅ | daily_reports |
| `kpi_performance` | ❌ | employee_kpi_scores, manager_performance_reviews |
| `geo_tracking` | ❌ | attendance_tracks (lat/long), field-tracking add-on |
| `overtime` | ✅ | overtime_requests, overtime_settings |
| `wfh_travel` | ✅ | requests, request_types, request_attachments |
| `branches` | ❌ | branches |
| `candidate_portal` | ❌ | candidates (public career page) |

Source of truth: `hrm-superadmin/config/features.php` → `FeatureRegistrySeeder` → `feature_registry`.

## Appendix D — Glossary

| Term | Meaning |
|---|---|
| Tenant | A company subscribing to the HRM (`tenants` row). |
| Tenant admin | `users.role = 'admin'` for that tenant — the customer's own administrator. |
| Super admin | Platform staff (`super_admins`). Roles: `superadmin` / `support` / `billing`. |
| Feature override | `tenant_feature_overrides` row — wins over the plan's `features_snapshot`. |
| Provisioning | Turning a paid enquiry into a working tenant (Phase 2). |
| Impersonation | A super admin acting as a tenant user for support (Phase 6), time-limited + audited. |
| Snapshot semantics | A tenant keeps the plan features it had at subscription time; later plan edits don't change it. |
