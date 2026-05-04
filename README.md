# Student Organization Management and Budget Transparency System

## Summary
- **Version:** 1.2.1
- **Updated:** May 1, 2026
- **Stack:** PHP 8.2+, PDO, MySQL/SQLite, Tailwind CSS, Vanilla JS
- **Entry point:** `index.php`

## Version History
- **1.2.1** (May 1, 2026): NEXUS wordmark font integration, About page wordmark refresh, responsive navbar wordmark updates, and PDF export template background support
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

4. Run the repository regression script:

```bash
php scripts/tests/test_organization_helpers.php
```

### Configuration
- Main runtime config: `src/core/config.php`
- Database bootstrap and compatibility: `src/core/db.php`
- Upload destination: `public/uploads/`
- SMTP/OAuth values are read from environment variables and config.
- Navbar logo image paths are set inline in `src/core/layout.php` and currently map light mode to `public/uploads/logodark.png` and dark mode to `public/uploads/logolight.png`.
- NEXUS wordmark font assets live in `static/fonts/`; active wordmark styles are defined in `src/core/layout.php`.
- Transaction PDF exports use `public/uploads/pdftemplate.png` as the page background template.

### Architecture Snapshot
- `src/core/`: runtime bootstrap, shared helpers, auth/session guards, layout shell
- `src/lib/`: reusable domain and utility helpers
- `src/actions/`: POST mutation handlers
- `src/pages/`: route/page renderers
- `src/services/`: data aggregation and service orchestration

### Project Structure
```text
websys/
├── index.php                 # Single entry point for routing and action dispatch
├── README.md                 # Main repository overview and quick-start guide
├── schema.sql                # Baseline schema reference
├── docs/
│   ├── architecture/         # Architecture and system design docs
│   └── reference/            # Function analysis and changelog docs
├── public/
│   ├── uploads/              # Writable upload storage for receipts and media
│   └── vendor/               # Vendor assets when present in local builds
├── scripts/
│   ├── maintenance/          # Cleanup and maintenance scripts
│   ├── seed/                 # Demo/seeding scripts
│   └── tests/                # Regression/utility scripts
├── src/
│   ├── actions/              # POST/action handlers
│   ├── core/                 # Bootstrap, auth, DB, helpers, layout
│   ├── lib/                  # Reusable domain helpers
│   ├── pages/                # Route/page renderers
│   └── services/             # Aggregated data and service logic
└── static/
    ├── demo/                 # Frontend-only static demo assets
    ├── fonts/                # Wordmark font assets
    └── js/                   # Shared client-side scripts
```

### Key Project Files
- `index.php`: routes pages and dispatches POST actions
- `src/core/layout.php`: shared shell, styles, and global behavior
- `src/core/db.php`: database bootstrap and compatibility initialization
- `src/core/mailer.php`: PHPMailer transport configuration and send helpers
- `src/actions/content_actions.php`: content mutations and transaction PDF export generation
- `src/pages/dashboard_page.php`: dashboard page controller
- `src/pages/dashboard_page_markup.php`: dashboard markup partials
- `src/services/dashboard_data.php`: dashboard data aggregation
- `static/js/dashboard-page.js`: dashboard client-side charts, filters, and modal behavior

## Related Docs
- [Project Architecture](docs/architecture/PROJECT_DOCUMENTATION.md)
- [Function Analysis](docs/reference/FUNCTION_ANALYSIS.md)
- [Changelog (Baseline Comparison)](docs/reference/CHANGELOG_2026-04-06.md)
- [Source Folder Guide](src/README.md)
- [Static Demo Guide](static/README.md)
- [Frontend Pitch Notes](static/FRONTEND_IDEAS.md)

## Maintenance
- Keep behavior changes and documentation updates in the same change set.
- Prefer extending the layered structure instead of adding new top-level patterns.
- Use prepared statements, CSRF checks, and role guards for all mutating/authenticated flows.
