# Function Analysis Reference

## Summary
- **Scope:** Key reusable functions and handler responsibilities across the codebase
- **Updated:** May 1, 2026
- **Focus:** Practical navigation of where logic lives

## Purpose
This document maps core functions by file/layer so contributors can quickly locate and extend behavior without breaking architecture boundaries.

## Audience
- Developers adding features or fixing bugs
- Reviewers tracing behavior ownership
- Maintainers onboarding to the repository

## Core Content

### Core Runtime (`src/core/`)
- `config.php`: returns app/database/integration configuration array
- `db.php`: creates PDO connection, initializes schema, applies compatibility migrations
- `auth.php`: current-user resolution and role/auth guards
- `helpers.php`: escaping, redirects, flash, CSRF, validation, utility helpers, audit helpers
- `layout.php`: shared header/footer shell and global client-side behavior injection
- `mailer.php`: PHPMailer setup and message-send helper layer for account email flows

### Domain Helpers (`src/lib/`)
- `organization.php`: ownership lookup, visibility rules, join eligibility, category helpers
- `notifications.php`: login update aggregation and popup marker generation
- `pagination.php`: pagination data shaping and UI rendering helpers
- `email.php`: verification/reset email composition, outbound mail, and SMTP readiness checks
- `integrations.php`: base URL resolution, OAuth checks, JSON fetch utility
- `maintenance.php`: lifecycle cleanup helpers
- `uploads.php`: upload-related validation/storage helper logic

### Action Handlers (`src/actions/`)
- `auth_flows.php`: register/login/verify/resend/forgot/reset/change-password/profile update handlers, including one-use reset token validation and reset cooldown tracking
- `content_actions.php`: announcement and transaction mutation handlers
- `workflows.php`: admin/owner approval and organization workflow handlers

### Render Handlers (`src/pages/`)
- `public_pages.php`: public/auth pages and logout routing output
- `community_pages.php`: student/community-facing page renderers
- `owner_pages.php`: owner-facing page renderers
- `admin_pages.php`: admin-facing page renderers
- `dashboard_page.php` + `dashboard_page_markup.php`: dashboard composition and markup sections

### Services (`src/services/`)
- `dashboard_data.php`: dashboard KPI/trend/feed aggregation logic

### Client Scripts (`static/js/`)
- `dashboard-page.js`: dashboard charts, empty states, modal behavior, client-side table filters
- `owner-org-switcher.js`: shared custom dropdown component behavior
- `image-cropper.js`: reusable cropper workflow for profile/org image flows
- `register-form.js`: registration page client UX enhancements

### Shared UI Notes
- Navbar logo paths, sizing, light/dark asset switching, and hover animation live in `src/core/layout.php`.
- Current navbar assets are served from `public/uploads/logodark.png` for light mode and `public/uploads/logolight.png` for dark mode.

## Related Docs
- [Repository Overview](../../README.md)
- [Project Architecture](../architecture/PROJECT_DOCUMENTATION.md)
- [Change Summary](CHANGELOG_2026-04-06.md)
- [Source Layer Guide](../../src/README.md)

## Maintenance
- Keep this as a map of responsibilities, not a full code dump.
- Add new major handlers/helpers to the appropriate section when introducing them.
- Prefer referencing symbols and files that are stable and discoverable.
