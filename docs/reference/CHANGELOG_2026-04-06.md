# Change Summary (Baseline: April 6, 2026)

## Summary
- **Baseline reference date:** April 6, 2026
- **Document updated:** May 5, 2026
- **Scope:** High-level grouped change summary after the April 6 baseline

## Version History
- **1.2.0** (May 1, 2026): PHPMailer-backed verification/reset flows, stricter one-use reset tokens, reset cooldown tracking, navbar branding/logo and hover motion
- **1.1.3** (April 27, 2026): documentation cleanup, consistent layout, upload control visual alignment
- **1.1.1** (April 6, 2026): footer and navigation presentation refinements, responsive usability updates
- **1.1.0** (April 6, 2026): onboarding tour, search palette, auth hardening, and documentation refresh
- **1.0.3** (April 4, 2026): organization modal polish and light-mode contrast improvements
- **1.0.2** (April 3, 2026): membership automation, profile integrity, dashboard/dropdown refinements
- **1.0.1** (March 28, 2026): registration and consent UX, dark theme, and footer/layout polish

## Purpose
This document tracks major change categories for quick historical context without replacing commit history.

## Audience
- Developers reviewing recent system evolution
- Maintainers preparing release notes
- Reviewers validating feature/security progression

## Core Content

### Security and Auth
- Hardened request processing and CSRF handling across POST workflows.
- Added/strengthened account flows: verification, reset, login updates, onboarding persistence.
- Added SMTP guardrails for password recovery and maintenance cleanup script for expired reset tokens.
- Activated PHPMailer-backed verification and reset delivery using configured SMTP credentials.
- Password reset links are now one-use, expired tokens are cleared when encountered, and successful forgot-password resets are tracked with `password_reset_at`.
- Forgot-password reset requests are blocked for seven days after a successful emailed reset, while normal profile password changes remain separate.

### Dashboard and UX
- Introduced onboarding tour improvements and reliability fixes.
- Added global command palette interactions.
- Improved chart readability/theme synchronization and empty-state behavior.
- Expanded responsive polish for tables, modals, footer, pagination, and navigation states.
- Added inline INVOLVE navbar logo support with separate light/dark assets and subtle hover animation for nav links and brand/logo.

### Organization and Workflow Logic
- Added/expanded owner assignment and join workflow guardrails.
- Improved visibility-category logic and eligibility checks.
- Refined owner/admin transaction request and report/export flows.

### Media and Profile Pipeline
- Completed profile/organization media field support and rendering adoption.
- Added reusable client cropper module and stabilized crop/preview/save workflow.
- Applied media display consistency across dashboard/admin/owner/community surfaces.

### Data and Maintenance
- Expanded seed data realism for demos and testing.
- Added maintenance helpers/scripts for lifecycle cleanup operations.

### Documentation and Project Hygiene
- Refreshed documentation set and standardized layout.
- Added/updated reference docs and static presentation notes.
- Updated documentation to reflect the May 1, 2026 auth/email and INVOLVE branding changes.

### Previous Release Notes
- Earlier versions focused on the baseline system foundation: organization management, announcements, transactions, dashboard views, and account workflows.
- Subsequent releases added onboarding, security strengthening, responsive UI work, and media/profile workflow improvements.

## Related Docs
- [Repository Overview](../../README.md)
- [Project Architecture](../architecture/PROJECT_DOCUMENTATION.md)
- [Function Analysis](FUNCTION_ANALYSIS.md)
- [Static Demo Guide](../../static/README.md)

## Maintenance
- Keep this summary concise and category-based.
- Use git history for line-level or commit-level detail.
- Add dated sections only for significant behavior or architecture shifts.
