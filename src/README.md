# Source Layer Guide

## Summary
- **Scope:** `src/` runtime application code
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
- Runtime startup is coordinated by `src/bootstrap.php`.
- Route rendering and action dispatch are coordinated by `src/routes/pages.php` and `src/routes/actions.php`.
- Shared shell rendering is handled through `src/core/layout.php`.
- Email verification and forgot-password delivery use `src/core/mailer.php` and `src/lib/email.php`.
- Navbar logo source paths and footer branding are maintained in `src/core/layout.php`.
- Global layout styles and behavior are extracted to `static/css/app.css`, `static/js/theme-init.js`, and `static/js/app.js`.

### Placement Rules
- Put reusable runtime concerns in `src/core/`.
- Put domain helpers that can be reused by multiple flows in `src/lib/`.
- Put feature-specific state-changing handlers, page renderers, and data shaping in the matching `src/features/<feature>/` directory.
- Put route dispatch only in `src/routes/`.
- Put shared presentation helpers in `src/shared/`.

## Related Docs
- [Repository Overview](../README.md)
- [Project Architecture](../docs/architecture/PROJECT_DOCUMENTATION.md)
- [Function Analysis](../docs/reference/FUNCTION_ANALYSIS.md)

## Maintenance
- Keep edits small and local to the correct layer.
- Avoid introducing new top-level architectural patterns unless required.
- Keep action handlers CSRF-protected and route guards role-aware.
- Keep account recovery changes aligned with token expiry, one-use reset behavior, and reset cooldown tracking.
