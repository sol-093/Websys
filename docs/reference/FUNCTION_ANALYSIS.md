# Function Analysis Reference

## Summary
- **Scope:** Key reusable functions and handler responsibilities across the codebase
- **Updated:** May 5, 2026
- **Focus:** Practical navigation of where logic lives

## Purpose
This document maps core functions by file/layer so contributors can quickly locate and extend behavior without breaking architecture boundaries.

## Audience
- Developers adding features or fixing bugs
- Reviewers tracing behavior ownership
- Maintainers onboarding to the repository

## Core Content

### Core Runtime (`includes/core/`)
- `config.php`: returns app/database/integration configuration array
- `db.php`: creates PDO connection, initializes schema, applies compatibility migrations
- `auth.php`: current-user resolution and role/auth guards
- `helpers.php`: escaping, redirects, flash, CSRF, validation, utility helpers, audit helpers
- `layout.php`: shared header/footer shell and global client-side behavior injection
- `mailer.php`: PHPMailer setup and message-send helper layer for account email flows

### Domain Helpers (`includes/lib/`)
- `organization.php`: ownership lookup, visibility rules, join eligibility, category helpers
- `notifications.php`: login update aggregation and popup marker generation
- `pagination.php`: pagination data shaping and UI rendering helpers
- `email.php`: verification/reset email composition, outbound mail, and SMTP readiness checks
- `integrations.php`: base URL resolution, OAuth checks, JSON fetch utility
- `maintenance.php`: lifecycle cleanup helpers
- `uploads.php`: upload-related validation/storage helper logic

### Routes (`includes/routes/`)
- `actions.php`: global action dispatch, OAuth callbacks, and POST action routing
- `pages.php`: public/auth page dispatch, authenticated page dispatch, and dashboard fallback

### Feature Code (`includes/features/`)
- `auth/actions.php` and `auth/pages.php`: login, registration, verification, account recovery, profile update, public/auth pages, About, and logout output
- `admin/pages.php`: admin-facing page renderers
- `organizations/pages.php`, `organizations/owner_pages.php`, and `organizations/workflows.php`: organization browsing, owner pages, membership, ownership, and admin/owner workflow handlers
- `transactions/actions.php`: announcement and transaction mutation handlers, including transaction PDF export generation
- `dashboard/page.php`, `dashboard/markup.php`, and `dashboard/data.php`: dashboard composition, markup sections, and KPI/trend/feed aggregation

### Shared UI (`includes/shared/`)
- `ui.php`: breadcrumbs, empty states, and dashboard skeleton rendering helpers

### Client Scripts (`assets/js/`)
- `app.js`: extracted global layout behavior, toasts, CSRF form injection, theme/mobile nav, global search, currency inputs, and onboarding
- `theme-init.js`: early dark-mode body class initialization
- `dashboard-page.js`: dashboard charts, empty states, modal behavior, client-side table filters
- `owner-org-switcher.js`: shared custom dropdown component behavior
- `image-cropper.js`: reusable cropper workflow for profile/org image flows
- `register-form.js`: registration page client UX enhancements

### Shared UI Notes
- Navbar logo paths, sizing, light/dark asset switching, footer branding, and shared shell markup live in `includes/core/layout.php`.
- Global runtime styles live in `assets/css/app.css`.
- Current navbar assets are served from `uploads/assets/involvelogo dark.png` for light mode and `uploads/assets/involvelogo light.png` for dark mode.

## Related Docs
- [Repository Overview](../../README.md)
- [Project Architecture](../architecture/PROJECT_DOCUMENTATION.md)
- [Change Summary](CHANGELOG_2026-04-06.md)
- [Source Layer Guide](../../includes/README.md)

## Maintenance
- Keep this as a map of responsibilities, not a full code dump.
- Add new major handlers/helpers to the appropriate section when introducing them.
- Prefer referencing symbols and files that are stable and discoverable.
