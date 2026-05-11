# Project Architecture Documentation

## Summary
- **Version:** 1.3.0-dev architecture baseline
- **Updated:** May 10, 2026
- **Runtime:** PHP 8.2+, PDO, MySQL/SQLite, Tailwind CSS, Vanilla JS
- **Entry point:** `index.php`

## Version History
- **1.3.0-dev**: incremental architecture modernization with PSR-4 repositories, repository-backed API/list endpoints, expanded PHPUnit/PHPStan coverage, current modernization status documentation, and deployment/cache guidance
- **1.2.1**: INVOLVE brand asset integration, About page brand refresh, responsive navbar logo updates, and PDF export template background support
- **1.2.0**: email verification/reset delivery, reset-token hardening, INVOLVE navbar/About branding polish
- **1.1.x**: current layered architecture baseline with single-entry routing
- **1.1.1**: presentation/layout refinements and responsive shell updates
- **1.1.0**: onboarding, global search, security hardening, and doc refresh baseline
- **1.0.x**: earlier structure stabilized around auth, organizations, dashboard, and transactions

## Purpose
This document describes the system architecture, layer boundaries, data model responsibilities, and operational expectations for maintainers.

## Audience
- Developers implementing new features
- Maintainers reviewing system consistency
- Reviewers validating architecture and local hosting shape

## Core Content

### Architecture Model
- Single-entry application: `index.php` loads bootstrap and route dispatchers.
- Feature-oriented organization under `includes/`:
  - `bootstrap.php` runtime startup and dependency loading
  - `routes/` page/action dispatch
  - `features/` feature-specific pages, actions, workflows, and data
  - `shared/` shared UI helpers
  - `core/` runtime concerns (auth, config, DB bootstrap, layout, helpers)
  - `lib/` reusable domain/helper logic
- Composer-autoloaded architecture under `src/`:
  - `Auth/` granular permission and permission matrix classes
  - `Repositories/` SQL/data access classes for migrated feature areas
  - `Services/` workflow/business logic classes used during incremental migration
  - `Support/` JSON response, request, cache, profiling, upload, and utility support
- JSON endpoints under `api/` use the same session/CSRF model as the server-rendered app and return structured JSON instead of redirects or flash messages.

### Project Structure
```text
websys/
|-- index.php                  # Single entry point for route/action dispatch
|-- README.md                  # Main repository documentation hub
|-- assets/                    # CSS, JS, and font assets
|-- database/                  # Schema/reference database files
|   `-- schema.sql
|-- docs/                      # Architecture and reference documentation
|-- config/                    # App settings and permissions
|-- includes/                  # Application PHP code
|   |-- core/                  # Auth, config, DB, helpers, layout
|   |-- features/              # Feature pages, actions, workflows, data
|   |-- lib/                   # Reusable domain helpers
|   |-- routes/                # Page and action dispatch
|   `-- shared/                # Shared UI helpers
|-- src/                       # Composer-autoloaded Involve classes
|-- api/                       # JSON endpoints and API bootstrap/helpers
|-- storage/                   # Runtime cache and test storage
|-- scripts/                   # Maintenance, seed, and test scripts
|-- uploads/                   # Writable upload storage and bundled media
|   |-- assets/                 # Bundled logos, page images, and PDF template
|   |-- organizations/          # Organization profile/logo uploads
|   |-- receipts/               # Transaction receipt uploads
|   `-- users/                  # User profile picture uploads
`-- vendor/                    # Composer dependencies
```

Source folders under `includes/` now include `bootstrap.php`, `routes/`, `features/`, and `shared/` in addition to the existing `core/` and `lib/` runtime layers.

### Security and Runtime Principles
- Session-based authentication and role enforcement
- Granular permission checks through `can()` and `requirePermission()`
- CSRF token verification for mutating requests
- PDO prepared statements for database access
- Escaped output via helper rendering functions
- Audit logging for critical actions
- PHPMailer-backed email delivery for verification and password reset flows
- One-use forgot-password reset tokens with expiry cleanup and reset cooldown tracking

### Data and Schema Notes
- Schema bootstrap and compatibility checks live in `includes/core/db.php`.
- Schema reference file: `database/schema.sql`.
- App supports MySQL and SQLite through PDO.
- Migrated SQL-heavy paths should use repository classes under `src/Repositories/`.
- Repository coverage currently includes auth, organizations, transactions, dashboard data, budgets, expense requests, announcements, audit logs, and notification workflow updates.
- Uploaded receipts/media are stored in `uploads/`, with bundled media in `uploads/assets/`, user profile pictures in `uploads/users/`, organization profile images in `uploads/organizations/`, and receipts in `uploads/receipts/`.
- User profile pictures and organization logos are decoded and re-saved through GD before storage so uploaded image metadata and malformed image payloads are not preserved. Receipt PDFs/images keep the receipt validation path and are not re-encoded.
- Compatibility migrations include `password_reset_at` for forgot-password reset cooldown enforcement.

### Route and Feature Domains
- Public/auth routes: login, registration, account recovery, informational pages
- Student domains: dashboard, organizations, announcements
- Owner domains: organization management, announcements, transactions, join responses
- Admin domains: organization administration, ownership assignment, approval workflows, audit logs
- Shared transparency domains: notification center for request/security updates and searchable audit review for admins
- Shared layout domain: global navigation, light/dark theme switching, INVOLVE brand assets, search, onboarding, and modal shell behavior
- API list domains: dashboard activity/reports/summary, notification updates/audit, admin audit/transactions/organizations, owner transactions/members/join requests, announcements, and organizations.

### Operational Scripts
- Seed scripts:
  - `scripts/seed/seed_dummy_data.php`
  - `scripts/seed/seed_dummy_reports.php`
- Maintenance script:
  - `scripts/maintenance/cleanup_expired_reset_tokens.php`
- Regression script:
  - `scripts/tests/test_organization_helpers.php`
- Composer quality gates:
  - `composer doctor`
  - `composer lint`
  - `composer validate --strict`
  - `composer test`
  - `composer analyse`
  - `composer audit`

### Deployment Notes
- Local run command:

```bash
php -S localhost:8000
```

- Local URL:

```text
http://localhost:8000/index.php
```

### Git Reference Notes
- Current working branch: `cleanup/code-structure`.
- Last pushed reference observed during this documentation pass: `49c6815 Add unit tests for repositories and transaction helper`.
- Local changes after that commit should be separated from generated files under `storage/cache/` when preparing the next commit.

## Related Docs
- [Repository Overview](../../README.md)
- [Data Flow Diagram](DATA_FLOW_DIAGRAM.md)
- [Function Analysis](../reference/FUNCTION_ANALYSIS.md)
- [Change Summary](../reference/CHANGELOG_2026-04-06.md)
- [Source Layer Guide](../../includes/README.md)
- [Current Modernization Status](../reference/CURRENT_MODERNIZATION_STATUS.md)

## Maintenance
- Keep this document focused on architecture decisions and boundaries.
- Record behavior-level updates in changelog/reference docs instead of expanding architectural narrative noise.
- Update section links when files/routes are moved.
