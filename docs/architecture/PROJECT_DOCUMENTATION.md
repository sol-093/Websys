# Project Architecture Documentation

## Summary
- **Version:** 1.2.x architecture baseline
- **Updated:** May 1, 2026
- **Runtime:** PHP 8.2+, PDO, MySQL/SQLite, Tailwind CSS, Vanilla JS
- **Entry point:** `index.php`

## Version History
- **1.2.0**: email verification/reset delivery, reset-token hardening, navbar brand/logo polish
- **1.1.x**: current layered architecture baseline with single-entry routing
- **1.1.1**: presentation/layout refinements and responsive shell updates
- **1.1.0**: onboarding, global search, security hardening, and doc refresh baseline
- **1.0.x**: earlier structure stabilized around auth, organizations, dashboard, and transactions

## Purpose
This document describes the system architecture, layer boundaries, data model responsibilities, and operational expectations for maintainers.

## Audience
- Developers implementing new features
- Maintainers reviewing system consistency
- Reviewers validating architecture and deployment shape

## Core Content

### Architecture Model
- Single-entry application: `index.php` performs route dispatch and POST action dispatch.
- Layered organization under `src/`:
  - `core/` runtime concerns (auth, config, DB bootstrap, layout, helpers)
  - `lib/` reusable domain/helper logic
  - `actions/` state mutation handlers
  - `pages/` rendering handlers
  - `services/` data aggregation and orchestration

### Project Structure
```text
websys/
├── index.php                    # Single entry point for route/action dispatch
├── schema.sql                   # Baseline schema reference
├── README.md                    # Main repository documentation hub
├── docs/
│   ├── architecture/
│   │   └── PROJECT_DOCUMENTATION.md # Architecture reference
│   └── reference/
│       ├── FUNCTION_ANALYSIS.md      # Function and handler map
│       └── CHANGELOG_2026-04-06.md   # Baseline change summary
├── public/
│   ├── uploads/                 # Writable upload storage for receipts/media
│   └── vendor/                  # Optional local vendor assets
├── scripts/
│   ├── maintenance/             # Periodic cleanup scripts
│   ├── seed/                    # Demo data scripts
│   └── tests/                   # Regression utilities
├── src/
│   ├── core/                    # Bootstrap, auth, DB, helpers, layout
│   ├── lib/                     # Reusable domain helpers
│   ├── actions/                 # POST/action handlers
│   ├── pages/                   # Route/page renderers
│   └── services/                # Data aggregation and service logic
└── static/
  ├── demo/                    # Static demo HTML/CSS/JS
  └── js/                      # Shared client-side behavior
```

### Security and Runtime Principles
- Session-based authentication and role enforcement
- CSRF token verification for mutating requests
- PDO prepared statements for database access
- Escaped output via helper rendering functions
- Audit logging for critical actions
- PHPMailer-backed email delivery for verification and password reset flows
- One-use forgot-password reset tokens with expiry cleanup and reset cooldown tracking

### Data and Schema Notes
- Schema bootstrap and compatibility checks live in `src/core/db.php`.
- Schema reference file: `schema.sql`.
- App supports MySQL and SQLite through PDO.
- Uploaded receipts/media are stored in `public/uploads/`.
- Compatibility migrations include `password_reset_at` for forgot-password reset cooldown enforcement.

### Route and Feature Domains
- Public/auth routes: login, registration, account recovery, informational pages
- Student domains: dashboard, organizations, announcements
- Owner domains: organization management, announcements, transactions, join responses
- Admin domains: organization administration, ownership assignment, approval workflows, audit logs
- Shared layout domain: global navigation, light/dark theme switching, inline navbar logo assets, search, onboarding, and modal shell behavior

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
- [Source Layer Guide](../../src/README.md)
- [Static Demo Guide](../../static/README.md)

## Maintenance
- Keep this document focused on architecture decisions and boundaries.
- Record behavior-level updates in changelog/reference docs instead of expanding architectural narrative noise.
- Update section links when files/routes are moved.
