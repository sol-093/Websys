# Source Layer Guide

## Summary
- **Scope:** `includes/` runtime application code
- **Updated:** May 5, 2026
- **Pattern:** Single-entry PHP architecture with feature-oriented source folders

## Purpose
This document explains how source code is organized and where new functionality should be added.

## Audience
- Developers implementing new routes/features
- Maintainers reviewing architecture boundaries

## Core Content

### Layer Responsibilities
- `bootstrap.php`: runtime startup, dependency loading, DB startup, current user/page setup
- `routes/`: GET page dispatch and action/POST dispatch
- `features/`: feature-oriented page renderers, action handlers, workflows, and data aggregation
- `shared/`: shared UI helpers and cross-feature presentation helpers
- `core/`: auth/session, DB bootstrap, shared layout, mail transport, generic helpers
- `lib/`: reusable domain, email, notification, upload, maintenance, and utility helpers

### Entry and Dispatch
- `index.php` is the single entry point.
- Runtime startup is coordinated by `includes/bootstrap.php`.
- Route rendering and action dispatch are coordinated by `includes/routes/pages.php` and `includes/routes/actions.php`.
- Shared shell rendering is handled through `includes/core/layout.php`.
- Email verification and forgot-password delivery use Composer PHPMailer through `includes/lib/email.php`.
- Navbar logo source paths and footer branding are maintained in `includes/core/layout.php`.
- Global layout styles and behavior are extracted to `assets/css/app.css`, `assets/js/theme-init.js`, and `assets/js/app.js`.

### Placement Rules
- Put reusable runtime concerns in `includes/core/`.
- Put domain helpers that can be reused by multiple flows in `includes/lib/`.
- Put feature-specific state-changing handlers, page renderers, and data shaping in the matching `includes/features/<feature>/` directory.
- Put route dispatch only in `includes/routes/`.
- Put shared presentation helpers in `includes/shared/`.

## Related Docs
- [Repository Overview](../README.md)
- [Project Architecture](../docs/architecture/PROJECT_DOCUMENTATION.md)
- [Function Analysis](../docs/reference/FUNCTION_ANALYSIS.md)

## Maintenance
- Keep edits small and local to the correct layer.
- Avoid introducing new top-level architectural patterns unless required.
- Keep action handlers CSRF-protected and route guards role-aware.
- Keep account recovery changes aligned with token expiry, one-use reset behavior, and reset cooldown tracking.
