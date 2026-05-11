# INVOLVE Student Organization Management and Budget Transparency System

## Summary
- **Version:** 1.3.0-dev
- **Updated:** May 10, 2026
- **Stack:** PHP 8.2+, PDO, MySQL/SQLite, Tailwind CSS, Vanilla JS
- **Entry point:** `index.php`
- **Current modernization:** PSR-4 app classes, repository-backed API/list endpoints, granular permissions, JSON API foundation, file cache, PHPUnit, PHPStan, and CI

## Version History
- **1.3.0-dev** (May 10, 2026): architecture modernization in progress with repository-backed API/list endpoints, expanded PHPUnit/PHPStan coverage, documentation baseline refresh, and clearer separation of source changes from generated cache artifacts
- **1.2.1** (May 1, 2026): INVOLVE brand asset integration, About page brand refresh, responsive navbar logo updates, and PDF export template background support
- **1.2.0** (May 1, 2026): PHPMailer-backed verification/reset flows, one-use password reset hardening, reset cooldown tracking, navbar logo and hover polish
- **1.1.3** (April 27, 2026): documentation standardization, UI consistency pass, upload control polish
- **1.1.1** (April 6, 2026): footer refinement, navbar spacing polish, responsive table/modal updates
- **1.1.0** (April 6, 2026): onboarding tour, global search palette, security hardening, documentation refresh
- **1.0.3** (April 4, 2026): organization modal polish and light-mode presentation improvements
- **1.0.2** (April 3, 2026): membership automation, profile integrity updates, dashboard/dropdown refinements
- **1.0.1** (March 28, 2026): registration, consent, dark theme, and footer/layout polish

## Purpose
This repository provides a role-based web application for student organizations, including organization operations, announcements, and finance transparency with approval workflows.

## Audience
- Administrators managing organizations, approvals, and audits
- Organization owners handling announcements and transactions
- Students viewing organizations, announcements, and budget updates
- Developers maintaining and extending the system

## Core Content

### Quick Start
1. Start the app:

```bash
php -S localhost:8000
```

2. Open:

```text
http://localhost:8000/index.php
```

3. (Optional) Seed demo data:

```bash
php scripts/seed/seed_dummy_data.php
php scripts/seed/seed_dummy_reports.php
php scripts/seed/seed_extra_dummy_data.php
php scripts/seed/seed_extra_dummy_reports.php
```

4. Run PHP lint:

```bash
composer lint
```

5. Run the repository regression script:

```bash
composer test
```

6. Run static analysis for PSR-4 classes:

```bash
composer analyse
```

7. Check dependency metadata and security advisories:

```bash
composer validate --strict
composer audit
```

### Configuration
- Main runtime config: `includes/core/config.php`
- Central app settings: `config/settings.php`
- Safe environment template: `.env.example`
- Database bootstrap and compatibility: `includes/core/db.php`
- Upload destination: `uploads/`
- Required PHP extensions: PDO driver for the selected database, `fileinfo`, and `gd` for server-side profile/organization image reprocessing.
- SMTP/OAuth values are read from environment variables and config.
- Keep `APP_DEBUG=false` for normal local demos; only enable `APP_DEBUG=true` when you intentionally need detailed startup/debug output.
- Set mail credentials in `.env` with `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM`, and `SMTP_FROM_NAME` when you want verification or password reset emails to send.
- Navbar logo image paths are configured in `config/settings.php`.
- INVOLVE brand image assets live in `uploads/assets/`; active navbar and About page logo styles are defined in `includes/core/layout.php`.
- Account emails currently use text-based `involve` header branding for broader email-client compatibility.
- Transaction PDF exports use `uploads/assets/pdftemplate.png` as the page background template.
- User profile and organization logo uploads are decoded and re-saved server-side before storage to strip embedded metadata and reject malformed image files.

### Architecture Snapshot
- `index.php`: thin web entry point that loads bootstrap and route dispatchers
- `includes/bootstrap.php`: runtime bootstrap, dependency loading, DB startup, current user/page setup
- `includes/routes/`: GET page dispatch and action/POST dispatch
- `includes/features/`: feature-oriented page, action, workflow, and data files
- `includes/shared/`: shared UI helpers split out of the layout shell
- `includes/core/`: auth/session guards, config, DB bootstrap, layout shell, generic helpers
- `includes/lib/`: reusable domain and utility helpers
- `src/`: PSR-4 classes for repositories, services, auth, cache, API, and support utilities
- `api/`: JSON endpoints that use the current session and CSRF model
- Repository-backed API/list endpoints currently cover dashboard activity/reports/summary, organizations, announcements, admin audit/organizations/transactions, owner members/join requests/transactions, and notification feeds.
- `?page=notifications`: persistent request/security update feed and personal audit timeline for logged-in users
- `?page=admin_audit`: admin-only audit review with search, family filters, and source details

### Project Structure
```text
websys/
|-- index.php                  # Single entry point for routing and action dispatch
|-- README.md                  # Main repository overview and quick-start guide
|-- assets/                    # CSS, JS, and font assets
|-- database/                  # Schema/reference database files
|   `-- schema.sql
|-- docs/                      # Architecture and reference documentation
|-- config/                    # Settings and permission matrices
|-- includes/                  # Application PHP code
|   |-- core/                  # Auth, config, DB, helpers, layout
|   |-- features/              # Feature pages, actions, workflows, data
|   |-- lib/                   # Reusable domain helpers
|   |-- routes/                # Page and action dispatch
|   `-- shared/                # Shared UI helpers
|-- src/                       # Composer-autoloaded Involve classes
|-- api/                       # JSON endpoints
|-- storage/                   # Runtime cache and test storage, not deployment source
|-- scripts/                   # Maintenance, seed, and test scripts
|-- uploads/                   # Writable upload storage and bundled media
|   |-- assets/                 # Bundled logos, page images, and PDF template
|   |-- organizations/          # Organization profile/logo uploads
|   |-- receipts/               # Transaction receipt uploads
|   `-- users/                  # User profile picture uploads
`-- vendor/                    # Composer dependencies
```

Source folders under `includes/` now include `bootstrap.php`, `routes/`, `features/`, and `shared/` in addition to the existing `core/` and `lib/` runtime layers.

### Key Project Files
- `index.php`: loads bootstrap, action dispatch, and page dispatch
- `includes/bootstrap.php`: starts the app and requires feature/runtime modules
- `includes/routes/actions.php`: global action and POST dispatch
- `includes/routes/pages.php`: page dispatch
- `includes/core/layout.php`: shared shell and layout markup
- `includes/shared/ui.php`: shared breadcrumbs, empty states, and skeleton UI helpers
- `includes/core/db.php`: database bootstrap and compatibility initialization
- `includes/lib/email.php`: Composer PHPMailer-backed email templates and send helpers
- `src/Repositories/*Repository.php`: SQL/data access layer for migrated dashboard, organization, transaction, budget, announcement, audit, and notification flows
- `api/list_helpers.php`: shared API pagination/filter response helpers
- `config/settings.php`: central branding, upload, pagination, PDF, feature, and security settings
- `includes/features/transactions/actions.php`: content mutations and transaction PDF export generation
- `includes/features/dashboard/page.php`: dashboard page controller
- `includes/features/dashboard/markup.php`: dashboard markup partials
- `includes/features/dashboard/data.php`: dashboard data aggregation
- `assets/css/app.css`: extracted global runtime styles
- `assets/js/app.js`: extracted global runtime behavior
- `assets/js/dashboard-page.js`: dashboard client-side charts, filters, and modal behavior

## Related Docs
- [Project Architecture](docs/architecture/PROJECT_DOCUMENTATION.md)
- [Function Analysis](docs/reference/FUNCTION_ANALYSIS.md)
- [Changelog (Baseline Comparison)](docs/reference/CHANGELOG_2026-04-06.md)
- [Source Folder Guide](includes/README.md)
- [Architecture Baseline](docs/architecture/ARCHITECTURE_BASELINE.md)
- [Current Modernization Status](docs/reference/CURRENT_MODERNIZATION_STATUS.md)
- [Production Deployment Checklist](docs/reference/PRODUCTION_DEPLOYMENT_CHECKLIST.md)

## Maintenance
- Keep behavior changes and documentation updates in the same change set.
- Prefer extending the layered structure instead of adding new top-level patterns.
- Use prepared statements, CSRF checks, and role guards for all mutating/authenticated flows.
- Keep generated cache files under `storage/cache/` out of review/commit sets; only `storage/cache/.gitkeep` should be tracked.
- Use `git archive` or the deployment checklist for production packages so local `.env`, IDE files, test scripts, and seed scripts are excluded.
