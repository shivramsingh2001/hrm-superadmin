# HRM Super Admin Panel — Milestone 1

Platform-staff console for the HRM SaaS. **Separate Laravel 12 app, shared `hrm_22_04` database.**
Built to the SRS at `D:\HRMNEW\DOCS\SRS_SuperAdminPanel_revised (1).docx`.

## What Milestone 1 covers

- **Auth** — `super_admins` table, session login, 5-attempts→15-min lockout, `sa_role:` gate
  (superadmin / support / billing). TOTP + IP allow-listing are stubbed for a later milestone.
- **Dashboard** — tenant counts, open enquiries, estimated MRR (5-min cache), recent tenants,
  trials expiring in 30 days.
- **Tenants** — list/search; detail tabs: Overview, Features, Users (read-only), Billing, Roles,
  Audit. Per-tenant feature overrides (write/clear), suspend/reactivate (superadmin only).
- **Subscription plans** — full CRUD (superadmin only); deactivate blocked while tenants are on a
  plan; feature set edited as a checkbox grid.
- **Feature registry** — 19-key registry from `config/features.php`, seeded into `feature_registry`,
  re-seed button.
- **Feature resolution** — `App\Services\FeatureService`: override → active subscription snapshot →
  config default → false; deprecated keys follow `replaced_by`. Cached 5 min, busted on write.
  *Not yet wired into the HRM app* (Phase 2).
- **Audit log** — every mutation writes an immutable `audit_logs` row via `App\Services\AuditLogger`;
  searchable viewer + CSV export. HMAC signing / partitioning are a later milestone.

## Setup

```
cd D:\HRMNEW\hrm-superadmin
composer install
# .env already points DB_* at hrm_22_04 (root / no password) and uses file session+cache, sync queue.
php artisan key:generate            # only if APP_KEY is blank
php artisan db:seed --class=FeatureRegistrySeeder
php artisan superadmin:set-password rajesh@shurt.io <password>
php artisan serve --host=127.0.0.1 --port=8001
```

Open http://127.0.0.1:8001/login.

Seeded super-admin accounts (passwords were unknown hashes — set one with the command above):
`rajesh@shurt.io` (superadmin), `priya@shurt.io` (support), `amit@shurt.io` (billing).

## Hard rules

- **This app never migrates the shared DB.** `database/migrations/` is intentionally empty. All
  tables already exist in `hrm_22_04`. If a future milestone truly needs its own table, prefix it
  `sa_` and document it here.
- No tenant scoping — every query here is cross-tenant by design. Models are plain, mapped to
  existing tables with explicit `$table`.

## Phase 2 ✅ done (2026-09-10)

Public `/contact` form → enquiry queue + state machine + notes + payment recording →
`ProvisioningService` (atomic + idempotent: creates the tenant, company, subscription + feature
snapshot, feature overrides, default config, shift, admin user with a one-time password, seeded
leave types + system roles, welcome email; links the enquiry as `provisioned`). Panel-only
`sa_provisioning_runs` table (tracked in a separate `sa_migrations` table so it never touches the
HRM's migration history). Run `php artisan migrate` once to create it.

## Phase 3 ✅ done (2026-09-10)

Tenant lifecycle: `TenantLifecycleService` + a **Lifecycle** tab on the tenant page — change plan
(new subscription row, old one expired, cache busted), employee-limit override, trial extend /
convert, renewal (payment + extend `end_date`), reason-coded suspend/reactivate, deletion request
→ 30-day grace → cancel. Scheduled: `tenants:trial-expiry`, `tenants:auto-suspend`,
`tenants:execute-deletions` (all `--dry-run` capable; needs the platform `schedule:run` cron).
**HRM change:** `AuthController::login` rejects login when `tenant.status` is not `active`/`trial`.

## Phase 4 ✅ done (2026-09-10)

Feature & plan governance: registry deprecation (`replaced_by` chains), **bulk feature-override
templates** (`sa_feature_override_templates` — apply a map to many tenants at once), **plan
versioning** (`sa_plan_versions` — snapshot per edit, feature diff, "affects new subs only", revert),
and **`FeatureService` wired into the HRM app** — copied service + `config/features.php`, a
`feature:` route middleware + `@feature` Blade directive, and a cross-app cache-bust endpoint
(`POST /internal/superadmin/feature-cache/bust`, shared secret `SUPERADMIN_INTERNAL_TOKEN` in both
`.env`s). Run `php artisan migrate` for the two new `sa_*` tables.

## Phase 5 ✅ done (2026-09-10)

Tenant RBAC: the **Roles** tab is now a full module × action permission matrix — create custom
roles, edit the matrix (system roles locked), clone a role to another tenant, delete. HRM side
(foundation, not yet enforced): `users.role_id` column + `rbac:sync-roles` backfill command +
`RbacService::can()` resolver + `@permission` Blade directive + a `permission:` middleware alias +
a dual-write observer. `role:` middleware is unchanged — the swap to `permission:` is incremental.

## Phase 6 ✅ done (2026-09-10)

Impersonation & support. From a tenant's **Support** tab: add support notes, pick a user, and
**Impersonate** — the panel mints a 60-minute `impersonation_sessions` token and hands off to the
HRM's `/impersonate/consume`, which logs in as that user with a persistent banner. "End session" (or
a panel Force-end, or the 60-min hard cap enforced by `EnforceImpersonationExpiry` middleware)
returns to the panel. Everything is audited as `super_admin_impersonating`.
New env: `SUPERADMIN_PANEL_URL` (HRM), `HRM_WEB_URL` / `IMPERSONATION_TTL_MINUTES` (panel). Run
`php artisan migrate` for `sa_tenant_notes`.

## Phase 7 ✅ done (2026-09-10)

Security hardening: **mandatory TOTP** (pure-PHP RFC 6238, `/2fa/setup` on first login, secret
encrypted at rest — `superadmin:reset-totp {email}` for recovery), **per-account + global IP
allowlist** and a **12 h hard session cap** (`SuperAdminGuard` middleware), **tamper-evident audit
log** (HMAC into `sa_audit_signatures`, `php artisan audit:verify`), brute-force / new-IP **security
alert emails**, and **encrypted `payment_logs.reference_number`** (`payment:encrypt-refs` backfill).
Env: `AUDIT_LOG_HMAC_KEY` (shared with HRM), `SUPER_ADMIN_IP_WHITELIST`,
`SUPER_ADMIN_SESSION_HARD_MINUTES`. Run `php artisan migrate` for `sa_audit_signatures` + the
`totp_secret` column widening.

## Phase 8 ✅ done (2026-09-10)

Dashboards & ops: nightly `metrics:snapshot` → `sa_platform_metrics` (MRR/ARR, funnel,
new-tenants-by-month, avg enquiry→live) + `sa_tenant_health` (logins 30d, seat utilisation, stale
flag). Reworked dashboard reads the snapshot + Chart.js charts; new **Tenant Health** page; public
`GET /health` probe; `metrics:digest` weekly email; `audit:verify` scheduled daily. Run
`php artisan migrate` for the two `sa_*` metrics tables, then `php artisan metrics:snapshot`.

## Phase 9 ✅ done (2026-09-10) — API + SPA-lite

JSON API at `/api/v1/super-admin/*` (29 routes) over the existing services. `App\Services\Jwt`
(hand-rolled HS256, `SUPER_ADMIN_JWT_SECRET` — 80 hex, verified distinct from `APP_KEY` / the HRM's
secret). Middleware: `ForceJsonResponse` (JSON errors), `api.jwt` (`ApiJwtAuth` — token verify +
`is_active` + IP allow-list, sets `Auth::user()`), `api.sa_role:<role>` per-route gate. Controllers
under `app/Http/Controllers/Api/` cover auth (password + TOTP `code`, refresh, logout, me),
dashboard, enquiries, tenants (incl. provision / suspend / activate / change-plan / features /
impersonate), plans, roles + permission matrix, audit logs. Envelope: `{data}` / `{error:{code,
message}}`. Access TTL 3600 s, refresh 43200 s. **API Console** page (`/api-console`, sidebar link)
is a no-build fetch client; the full React/Vue SPA at `admin.<domain>` is deferred.
Set `SUPER_ADMIN_JWT_SECRET` in `.env` (see `config/platform.php` `jwt` block); no migration.

## Not yet (future milestone)

Full React/Vue SPA; 5-step provisioning wizard + async job; token denylist on logout; OpenAPI spec;
`role:` → `permission:` middleware swap in the HRM (Phase 5.4 is additive-only foundation);
per-endpoint API rate limits beyond login.

The full phase-by-phase roadmap is in
[`docs/SUPERADMIN_EXECUTION_PLAN.md`](docs/SUPERADMIN_EXECUTION_PLAN.md).
