# Architecture Baseline

Last reviewed: May 9, 2026

## Current Runtime Shape
- `index.php` remains the single web entry point and loads `includes/bootstrap.php`.
- Composer PSR-4 autoloading is active for the `Involve\` namespace under `src/`.
- Legacy procedural files under `includes/` remain supported while repositories, services, API helpers, cache, and permission classes move into `src/`.
- Server-rendered `?page=` and `?action=` flows remain the primary compatibility contract.

## Modernization Already Active
- Granular permissions are defined in `config/permissions.php` and accessed with `can()` / `requirePermission()`.
- JSON API bootstrap lives under `/api` and returns JSON errors for API auth/permission failures.
- File caching lives under `storage/cache` through `Involve\Support\Cache\FileCache`.
- Database bootstrap creates compatibility columns/tables and guarded performance indexes.
- PHPUnit and GitHub Actions CI are configured; SQLite route tests are skipped locally if `pdo_sqlite` is unavailable.

## Implementation Guardrails
- Do not cache session, CSRF, password, token, or current-user data.
- Keep page files rendering-focused and move new SQL-heavy work into repositories/services.
- Keep API list responses in `{ ok, items, pagination, filters }` shape.
- Use Composer-managed dependencies only; PHPMailer is loaded from `vendor/`.
