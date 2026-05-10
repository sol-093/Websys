# Architecture Baseline

Last reviewed: May 10, 2026

## Current Runtime Shape
- `index.php` remains the single web entry point and loads `includes/bootstrap.php`.
- Composer PSR-4 autoloading is active for the `Involve\` namespace under `src/`.
- Legacy procedural files under `includes/` remain supported while repositories, services, API helpers, cache, and permission classes move into `src/`.
- Server-rendered `?page=` and `?action=` flows remain the primary compatibility contract.
- The current branch is `cleanup/code-structure`; the last pushed reference is `49c6815 Add unit tests for repositories and transaction helper`.

## Modernization Already Active
- Granular permissions are defined in `config/permissions.php` and accessed with `can()` / `requirePermission()`.
- JSON API bootstrap lives under `/api` and returns JSON errors for API auth/permission failures.
- File caching lives under `storage/cache` through `Involve\Support\Cache\FileCache`.
- Database bootstrap creates compatibility columns/tables and guarded performance indexes.
- PHPUnit and GitHub Actions CI are configured; SQLite route tests are skipped locally if `pdo_sqlite` is unavailable.
- Repository-backed data access is active for dashboard aggregation/list panels, organization directory/admin/owner workflows, transactions/change requests, announcements, audit logs, notifications, budgets, expense requests, and auth lookups.
- Lazy-loading JSON endpoints exist for dashboard activity/reports/summary, notifications/audit feeds, admin audit/transactions/organizations, owner transactions/members/join requests, announcements, and organizations.

## Current Repository Classes
- `AuthRepository`: user lookup support for auth helpers.
- `OrganizationRepository`: organization visibility, membership, join request, owner/admin list, and mutation helpers.
- `TransactionRepository`: transaction reports, owner/admin transaction lists, and change request workflows.
- `DashboardRepository`: dashboard aggregate and list data.
- `BudgetRepository`: budget line and budget workspace data access.
- `ExpenseRequestRepository`: expense request lookup and mutation support.
- `AnnouncementRepository`: announcement create/list/pin/delete flows.
- `AuditRepository`: admin audit list and personal audit timeline.
- `NotificationRepository`: request/security workflow update aggregation.

## Documentation Reference Point
- The pushed branch currently points at `49c6815`, which already contains the first repository/test foundation, CI, settings, deployment checklist, and PHPMailer cleanup.
- Local uncommitted work after that commit expands repository coverage across API endpoints and notification/dashboard/organization workflows.
- Generated runtime/cache files under `storage/cache/` are ignored; only `storage/cache/.gitkeep` should remain tracked.

## Implementation Guardrails
- Do not cache session, CSRF, password, token, or current-user data.
- Keep page files rendering-focused and move new SQL-heavy work into repositories/services.
- Keep API list responses in `{ ok, items, pagination, filters }` shape.
- Use Composer-managed dependencies only; PHPMailer is loaded from `vendor/`.
- Keep documentation dates and repository/API lists in sync whenever a modernization slice graduates from experimental local changes to committed source.
