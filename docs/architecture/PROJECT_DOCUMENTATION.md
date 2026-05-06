# Project Architecture Documentation

## Summary
- **Version:** 1.2.x architecture baseline
- **Updated:** May 5, 2026
- **Runtime:** PHP 8.2+, PDO, MySQL/SQLite, Tailwind CSS, Vanilla JS
- **Entry point:** `index.php`

## Version History
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

### Project Structure
```text
websys/
|-- index.php                  # Single entry point for route/action dispatch
|-- README.md                  # Main repository documentation hub
|-- assets/                    # CSS, JS, and font assets
|-- database/                  # Schema/reference database files
|   `-- schema.sql
|-- docs/                      # Architecture and reference documentation
|-- includes/                  # Application PHP code
|   |-- core/                  # Auth, config, DB, helpers, layout
|   |-- features/              # Feature pages, actions, workflows, data
|   |-- lib/                   # Reusable domain helpers
|   |-- routes/                # Page and action dispatch
|   `-- shared/                # Shared UI helpers
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
- Uploaded receipts/media are stored in `uploads/`, with bundled media in `uploads/assets/`, user profile pictures in `uploads/users/`, organization profile images in `uploads/organizations/`, and receipts in `uploads/receipts/`.
- Compatibility migrations include `password_reset_at` for forgot-password reset cooldown enforcement.

### Route and Feature Domains
- Public/auth routes: login, registration, account recovery, informational pages
- Student domains: dashboard, organizations, announcements
- Owner domains: organization management, announcements, transactions, join responses
- Admin domains: organization administration, ownership assignment, approval workflows, audit logs
- Shared transparency domains: notification center for request/security updates and searchable audit review for admins
- Shared layout domain: global navigation, light/dark theme switching, INVOLVE brand assets, search, onboarding, and modal shell behavior

### Operational Scripts
- Seed scripts:
  - `scripts/seed/seed_dummy_data.php`
  - `scripts/seed/seed_dummy_reports.php`
- Maintenance script:
  - `scripts/maintenance/cleanup_expired_reset_tokens.php`
- Regression script:
  - `scripts/tests/test_organization_helpers.php`

### Deployment Notes
- Local run command:

```bash
php -S localhost:8000
```

- Local URL:

```text
http://localhost:8000/index.php
```

## Related Docs
- [Repository Overview](../../README.md)
- [Function Analysis](../reference/FUNCTION_ANALYSIS.md)
- [Change Summary](../reference/CHANGELOG_2026-04-06.md)
- [Source Layer Guide](../../includes/README.md)

## Maintenance
- Keep this document focused on architecture decisions and boundaries.
- Record behavior-level updates in changelog/reference docs instead of expanding architectural narrative noise.
- Update section links when files/routes are moved.
