# Change Summary (April 6, 2026)

Baseline: `8a74fda` (April 4, 2026: Polish organization modals in light mode)
Scope: all current workspace updates compared to baseline

## Addendum (April 26, 2026 follow-up)

### L) About page and public routing expansion
- Added new `about` route and renderer with mission/vision/core values and team member cards.
- Added contextual About CTA from home hero while keeping admin redirect protections in place.

Files:
- index.php
- src/pages/public_pages.php

### M) Profile and organization media pipeline completion
- Added profile and organization media columns (`path`, `crop_x`, `crop_y`, `zoom`) across schema bootstrap and compatibility migrators.
- Added reusable media rendering helpers for placeholders + cropped media output (`renderProfilePlaceholder()`, `renderProfileMedia()`).
- Rolled media rendering into admin tables, owner flows, dashboard summaries, and profile/org list cards.

Files:
- schema.sql
- src/core/db.php
- src/core/helpers.php
- src/core/auth.php
- src/pages/admin_pages.php
- src/pages/owner_pages.php
- src/pages/dashboard_page_markup.php
- src/pages/community_pages.php
- src/services/dashboard_data.php

### N) Cropper modal implementation and stabilization
- Added reusable cropper module (`static/js/image-cropper.js`) with drag reposition, zoom control, and canvas export.
- Fixed crop export to align with the visible guide frame instead of full stage bounds.
- Added profile-specific improvements: larger preview modal sync and auto-submit save behavior after crop.
- Added cache-busted script include in layout to prevent stale client-side cropper bundles.

Files:
- static/js/image-cropper.js
- src/core/layout.php
- src/pages/community_pages.php

### O) Organization members modal enlargement
- Increased admin/user Organization Members modal width and list height for better scanning of long member lists.

Files:
- src/pages/admin_pages.php

## Addendum (post-summary updates)

### A) Navigation active-state hotfix
- Fixed overlapping nav highlight on Organizations view.
- Dashboard active state no longer includes the organizations page key.

Files:
- src/core/layout.php

### B) Runtime onboarding resilience hotfix
- Added shared onboarding completion helper in layout script.
- Added fallback that skips unavailable step targets and completes tour when no valid steps remain.
- Prevents disappearing/repeating onboarding loop when navigating between dashboard and organizations targets.

Files:
- src/core/layout.php

### C) Static onboarding reliability hotfix
- Preserved onboarding progress by resuming from saved session step index.
- Prevented unintended restart while an onboarding layer is already active.
- Persisted step index when skipping missing targets.
- Reworked tooltip/focus placement to run after scroll completion with viewport-safe clamping.

Files:
- static/demo/system-static-demo.js

### D) Static presentation documentation
- Added presentation-oriented idea/pitch document focused on current static frontend UI details.
- Includes theme origin (glass transparency), color direction, typography notes, and teacher-facing pitch script.

Files:
- static/FRONTEND_IDEAS.md

### E) Footer modernization and compaction update
- Reworked footer into a cleaner responsive accordion/grid shell while preserving role-aware content.
- Added and then iteratively refined compact spacing, alignment, and typography for mobile and desktop.
- Removed footer branding strip for a shorter footprint and hid mobile accordion chevrons.
- Simplified collapsed state by removing preview text; section details now appear only when expanded.
- Added social links with inline SVG icons (no CDN dependency) and aligned bottom utility row.

Files:
- src/core/layout.php

### F) Navigation and page-level responsive polish
- Tightened desktop utility control spacing in navbar (search, theme toggle, logout) without changing control size.
- Improved table responsiveness by wrapping multiple admin/owner/student tables in table-scroll containers.
- Standardized modal shells with close-target attributes and viewport-safe panel scrolling.
- Improved search/text input intent hints for relevant modal search fields.
- Increased pagination control hit targets for better touch accessibility.

Files:
- src/core/layout.php
- src/lib/pagination.php
- src/pages/admin_pages.php
- src/pages/dashboard_page_markup.php
- src/pages/owner_pages.php

### G) Pagination sizing refinement (April 7)
- Reduced Prev/Next pagination button footprint to better match number buttons while keeping them slightly larger for clarity.
- Preserved existing pagination behavior and disabled-state handling.

Files:
- src/lib/pagination.php

### H) Announcement label and duration controls (April 7)
- Added optional announcement label and configurable visibility duration (7, 14, 30, 60, 90 days) when owners post announcements.
- Added announcement expiry support (`duration_days`, `expires_at`) with runtime schema compatibility updates for both SQLite and MySQL.
- Updated announcement listings and dashboard feeds to show only active announcements and display label/expiry metadata.
- Updated cleanup routine to purge announcements based on expiry timestamp.
- Added a dedicated owner modal for viewing/managing all organization announcements, while keeping the Post Announcement panel focused on a compact preview list.

### I) Owner assignment eligibility guard (April 7)
- Enforced admin owner assignment validation so assignees must match organization visibility requirements for institutewide/program-based organizations.
- Collegewide organizations remain assignable to any eligible student/owner account.
- Applied validation to both direct assign-owner actions and organization update flows that change the owner.

Files:
- src/actions/workflows.php
- src/core/db.php
- src/actions/content_actions.php
- src/pages/owner_pages.php
- src/pages/community_pages.php
- src/services/dashboard_data.php
- src/pages/dashboard_page_markup.php
- src/lib/maintenance.php
- scripts/seed/seed_dummy_data.php
- schema.sql

### J) Organization switcher icon update (April 7)
- Replaced chevron icon with building/organization SVG icon in organization switcher buttons.
- Updated all organization-related icons (`'orgs'` and `'my-org'` in uiIcon) to use the new building design.
- Added new `'admin'` icon using briefcase SVG for all admin-related UI elements.
- Updated public homepage to use the new `'admin'` icon instead of `'audit'` in the "For Admin" section.
- Icons provide clearer visual indication of organization selection and admin context across the UI.

Files:
- src/pages/admin_pages.php
- src/pages/owner_pages.php
- src/pages/public_pages.php
- src/core/helpers.php

### K) Auth recovery implementation start (April 7)
- Fixed reset-password page DB wiring by updating reset page handler signature to accept PDO directly instead of relying on global DB state.
- Updated route dispatch to pass DB into reset-password page handler.
- Aligned reset-password validation with registration policy by enforcing `validatePasswordStrength()` during reset.
- Added SMTP guardrail for forgot-password flow: requests now fail fast with explicit user/admin feedback when SMTP is not fully configured.
- Added maintenance cleanup script to clear expired reset tokens (`scripts/maintenance/cleanup_expired_reset_tokens.php`).

Files:
- index.php
- src/pages/public_pages.php
- src/actions/auth_flows.php
- src/lib/email.php
- scripts/maintenance/cleanup_expired_reset_tokens.php
- README.md

## 1) Security and request handling
- Added centralized security headers and CSP setup during bootstrap.
- Introduced shared CSRF helper APIs and middleware flow for POST actions.
- Added reusable CSRF field helper and migrated many forms to use it.
- Added secure file upload helper with MIME allowlist and size guardrails.

Files:
- index.php
- src/core/helpers.php
- src/pages/admin_pages.php
- src/pages/community_pages.php
- src/pages/dashboard_page_markup.php
- src/pages/owner_pages.php
- src/pages/public_pages.php
- src/lib/uploads.php

## 2) Authentication and account flows
- Added first-login student onboarding state (`onboarding_done`) end-to-end.
- Added complete onboarding endpoint and replay onboarding endpoint.
- Hardened token workflows by storing hashed activation/reset tokens.
- Updated verify/reset handlers to compare hashed tokens safely.

Files:
- schema.sql
- src/core/db.php
- src/core/auth.php
- src/actions/auth_flows.php
- index.php

## 3) Navigation and global search
- Added global command palette search (`Ctrl+K`/`Cmd+K`) for users, organizations, announcements.
- Added organizations nav shortcut in desktop/mobile menus.
- Improved mobile nav open/close behavior and responsive nav state.

Files:
- index.php
- src/core/layout.php

## 4) Onboarding tour UX
- Added conditional onboarding tooltip overlay for eligible student sessions.
- Added guided multi-step targets across dashboard and organizations pages.
- Added completion persistence and local/session storage coordination.
- Added footer action to replay onboarding tour for student users.

Files:
- src/core/layout.php
- src/pages/dashboard_page_markup.php
- src/pages/community_pages.php
- src/actions/auth_flows.php
- index.php

## 5) Dashboard data and resilience improvements
- Added month-over-month KPI delta calculations in dashboard data service.
- Added progress-bar animation hooks and chart fallback messaging.
- Added empty-state and no-data handling for chart rendering.
- Added client-side transaction history filtering enhancements.

Files:
- src/services/dashboard_data.php
- src/pages/dashboard_page_markup.php
- static/js/dashboard-page.js

## 6) Reporting and transactions
- Added CSV export action for transaction reports.
- Added owner/admin export links in transaction history UIs.
- Added print-friendly report styling updates.

Files:
- src/actions/content_actions.php
- index.php
- src/pages/owner_pages.php
- src/pages/admin_pages.php
- src/core/layout.php

## 7) Email and configuration updates
- Added SMTP configuration fields to app config.
- Updated email sending to support PHPMailer when SMTP is configured.
- Added fallback to PHP mail() when SMTP host is not configured.
- Added Composer manifest for PHPMailer dependency.

Files:
- src/core/config.php
- src/lib/email.php
- composer.json

## 8) UI and component consistency updates
- Updated icon set and helper alias for shared icon rendering.
- Added loading/toast/password-toggle/currency-input shared UX scripts.
- Refined dark-mode card, modal, and footer visual consistency.
- Updated static demo assets to mirror current runtime UX direction.

Files:
- src/core/helpers.php
- src/core/layout.php
- src/pages/public_pages.php
- static/README.md
- static/demo/system-static-demo.css
- static/demo/system-static-demo.html
- static/demo/system-static-demo.js

## 9) Documentation and instruction files
- Updated project README recent updates list.
- Added repository-specific Copilot instruction file.
- Added this dated compare summary for full baseline-to-current traceability.

Files:
- README.md
- .github/copilot-instructions.md
- docs/reference/CHANGELOG_2026-04-06.md

## 10) Net diff size
- 22 tracked files changed in working tree diff output (`git diff --stat HEAD`).
- Approximate delta: 3,264 insertions and 250 deletions.
- Plus newly added files not in that stat before staging:
  - .github/copilot-instructions.md
  - composer.json
  - src/lib/uploads.php
  - static/js/register-form.js
  - docs/reference/CHANGELOG_2026-04-06.md
