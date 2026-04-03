Student Organization Management and Budget Transparency System - Function Analysis
Last Updated: April 3, 2026

Recent Functional Updates

- April 3, 2026: `static/js/dashboard-page.js`
  - Added immediate chart recolor behavior on theme toggle.
  - Added body class observer to re-apply chart presentation state without refresh.
- April 3, 2026: `static/js/owner-org-switcher.js` (new shared component)
  - Added multi-instance custom dropdown logic with click-open interaction.
  - Added isolated hidden-input synchronization for independent filters/selectors.
- April 3, 2026: `src/pages/owner_pages.php`
  - Updated custom dropdown integration for organization/transaction controls.
  - Fixed transaction filter submission wiring and improved transaction row readability.

---

This document lists the key reusable functions in the `src/` layer and explains what they do and where they are used.

## Core Architecture Notes

- The application uses a single entry point: `index.php`.
- Reusable helpers are centralized in `src/`.
- `src/layout.php` provides shared shell rendering and UI behavior for all routes.
- Uploaded receipts are stored in `public/uploads/` and linked directly via saved file paths.

---

## `src/core/config.php`

This file returns the runtime configuration array.

### Keys

- **`db`**: database config (`driver`, `host`, `port`, `database`, `username`, `password`, `sqlite_path`).
- **`app_name`**: app label shown in page title and shell.
- **`upload_dir`**: target path for receipt uploads.
- **`google_oauth`**: Google OAuth credentials (`client_id`, `client_secret`).
- **`base_url`**: optional explicit base URL override.

---

## `src/core/db.php`

### Connection and Initialization

- **`db()`**: returns a singleton PDO instance; supports MySQL and SQLite based on config.
- **`initializeDatabaseSqlite(PDO $pdo)`**: creates SQLite schema and ensures defaults.
- **`initializeDatabaseMySql(PDO $pdo)`**: creates MySQL schema and ensures defaults.

### Schema Compatibility Helpers

- **`ensureAcademicAndVisibilityColumns(PDO $pdo)`**: ensures `users.institute/program` and organization visibility columns exist.
- **`ensureAnnouncementPinColumns(PDO $pdo)`**: ensures announcement pinning columns (`is_pinned`, `pinned_at`) exist.
- **`ensureAuthEnhancementColumns(PDO $pdo)`**: ensures auth/security columns (verification, reset token, account status, login metadata) exist.
- **`ensureAuthEnhancementTables(PDO $pdo)`**: ensures supporting auth tables (`user_sessions`, `login_history`, `password_history`, `security_notifications`) exist.
- **`tableColumnExists(PDO $pdo, string $table, string $column)`**: checks for column existence in MySQL or SQLite.
- **`ensureDefaultAdmin(PDO $pdo)`**: auto-creates default admin account when none exists.

Used by: the full application at startup (`index.php` includes `src/db.php`).

---

## `src/core/auth.php`

### Authentication and Authorization

- **`currentUser()`**: returns current session user (`id`, `name`, `email`, `role`, `institute`, `program`, `email_verified`, `account_status`, `created_at`) or `null`.
  - Includes role integrity check: owner users without owned orgs are downgraded to student.
- **`requireLogin()`**: redirects to login page when user is not authenticated.
- **`requireRole(array $roles)`**: enforces role whitelist, otherwise redirects with error flash.

Used by: admin and owner workflows throughout `index.php`.

---

## `src/actions/auth_flows.php`

### Authentication Flow Handlers

- **`handleGoogleLoginPage(array $config)`**: initiates Google OAuth flow and redirects to provider URL.
- **`handleGoogleCallbackPage(PDO $db, array $config)`**: validates OAuth callback, upserts user, creates session, and redirects.
- **`handleRegisterAction(PDO $db)`**: handles registration validation, creates user, and sends activation email.
- **`handleLoginAction(PDO $db)`**: handles credentials, status checks, verification checks, throttling, and session login.
- **`handleVerifyEmailAction(PDO $db)`**: verifies activation token and marks email as verified.
- **`handleResendVerificationAction(PDO $db)`**: resends activation link with rate limiting.
- **`handleForgotPasswordAction(PDO $db)`**: generates reset token and sends reset email.
- **`handleResetPasswordAction(PDO $db)`**: validates reset token, updates password hash, clears token, logs reset.
- **`handleChangePasswordAction(PDO $db, array $user)`**: validates current password and updates password hash for logged-in user.
- **`handleUpdateProfileAction(PDO $db, array $user)`**: updates name/email; email changes trigger re-verification workflow.

Used by: route/action dispatch in `index.php` to keep main controller concise.

---

## `src/core/helpers.php`

### Rendering and Utility

- **`e(?string $value)`**: HTML-escapes output (`htmlspecialchars`).
- **`uiIcon(...)`**: returns SVG icon markup by icon key. The `audit` key is used for admin-facing labels and now maps to the updated admin logo SVG.

### Navigation and Flash Messaging

- **`redirect(string $url)`**: sends HTTP Location header and exits.
- **`setFlash(string $type, string $message)`**: sets session flash message.
- **`getFlash()`**: fetches and clears one flash message.

### Request and CSRF

- **`isPost()`**: checks if request method is POST.
- **`csrfToken()`**: generates and returns per-session CSRF token.
- **`verifyCsrfToken(?string $token)`**: validates provided token against session token.

### Rate Limiting

- **`rateLimitIsBlocked(string $key, int $maxAttempts, int $windowSeconds)`**: checks if key exceeds allowed attempts in window.
- **`rateLimitIncrement(string $key, int $windowSeconds)`**: increments attempt count.
- **`rateLimitClear(string $key)`**: clears rate-limit bucket key.

### Validation and File Upload

- **`validateAndStoreReceiptUpload(array $file, string $uploadDir)`**:
  - validates upload error code
  - validates size (max 5MB)
  - validates extension and MIME
  - saves with randomized filename
  - returns `['path' => ?string, 'error' => ?string]`
- **`validatePasswordStrength(string $password)`**: checks minimum complexity (length, upper/lower, number, special char).

### Audit

- **`auditLog(?int $userId, string $action, string $entityType = 'system', ?int $entityId = null, ?string $details = null)`**:
  - writes entry to `audit_logs`
  - captures IP and user-agent
  - wrapped safely to avoid breaking normal flow on logging errors

Used by: almost every action flow in `index.php` (auth, admin actions, requests, pin/unpin, etc.).

---

## `src/core/layout.php`

### Shared Shell Rendering

- **`renderHeader(string $title = 'Dashboard')`**:
  - emits HTML document start, global styles, navigation, flash output, and route-aware shell
  - reads current user and pending update popup payload from session
- **`renderFooter()`**:
  - closes common layout wrappers and outputs shared scripts and role-aware footer markup
  - works with shared shell flex layout so footer stays at bottom on short pages
  - uses compact spacing and text-only bottom bar for reduced footer height

Used by: all route views rendered by `index.php`.

---

## `src/lib/integrations.php`

### Integration and External Request Helpers

- **`appBaseUrl(array $config)`**: resolves runtime base URL (or configured override).
- **`googleOauthEnabled(array $config)`**: checks if OAuth credentials are present.
- **`fetchJson(string $url, ?array $postFields = null)`**: helper for GET/POST JSON calls (used in OAuth token/profile fetch).

Used by: Google OAuth login and callback flow in `index.php`.

---

## `src/lib/maintenance.php`

### Data Lifecycle Helper

- **`purgeExpiredAnnouncements(PDO $db, int $days = 30)`**: removes announcements older than retention period.

Used by: dashboard boot flow in `index.php`.

---

## `src/lib/pagination.php`

### Pagination Utilities

- **`paginateArray(array $items, string $queryKey, int $perPage = 10)`**:
  - generic in-memory paginator
  - reads page index from query string key
- **`renderPagination(array $pagination)`**:
  - route-safe pagination UI renderer
  - preserves dashboard scroll behavior for dashboard pagination keys

Used by: list/table sections in `index.php` (admin and owner pages, dashboard widgets).

---

## `src/lib/organization.php`

### Ownership Helpers

- **`getOwnedOrganizations(int $ownerId)`**: list organizations owned by a user.
- **`getOwnedOrganizationById(int $ownerId, int $organizationId)`**: fetch one owned organization.

### Academic and Visibility Metadata

- **`getInstituteOptions()`**: predefined institute list.
- **`getProgramInstituteMap()`**: maps programs to institute values.
- **`getOrgCategoryOptions()`**: organization visibility category labels.
- **`normalizeAcademicValue(?string $value)`**: normalized lowercase comparator for institute/program checks.

### Visibility and Join Eligibility

- **`sortOrganizationsByCategory(array $organizations)`**: deterministic category-first organization sorting.
- **`applyOrganizationVisibilityForUser(array $organizations, array $user)`**:
  - filters organizations by user institute/program
  - leaves non-student roles unfiltered
- **`getOrganizationVisibilityLabel(array $org)`**: human-readable visibility label.
- **`canUserJoinOrganization(array $org, array $user)`**: enforces institute/program join restrictions.
- **`getJoinRestrictionLabel(array $org)`**: UI label for restricted join state.

Used by: organization listing, join flow checks, admin organization forms, and owner workflows in `index.php`.

---

## `src/lib/notifications.php`

### Login Update Notification Helpers

- **`collectUserRequestUpdates(int $userId)`**: aggregates recent join/transaction/assignment request updates.
- **`buildUpdatesMarker(array $updates)`**: creates deterministic marker hash for update payload.
- **`queueLoginUpdatesPopup(int $userId)`**: queues update popup data and sets cookie marker to prevent repeat display.

Used by: post-login and Google OAuth login flow in `index.php`.

---

## `src/actions/workflows.php`

### Admin and Owner Workflow Handlers

- **`handleProcessTxChangeRequestAction(PDO $db, array $user)`**:
  - handles admin approve/reject flow for transaction change requests
  - applies approved DB changes and writes audit logs
- **`handleRespondJoinRequestAction(PDO $db, array $user)`**:
  - handles owner approve/decline flow for pending join requests
  - inserts membership on approval and writes audit logs
- **`handleCreateOrgAction(PDO $db, array $user)`**: creates organizations from admin form inputs.
- **`handleUpdateOrgAdminAction(PDO $db, array $user)`**: updates organization profile, owner, and visibility metadata.
- **`handleDeleteOrgAction(PDO $db, array $user)`**: deletes an organization and related dependent records.
- **`handleAssignOwnerAction(PDO $db, array $user)`**: creates owner assignment requests for students.
- **`handleRespondOwnerAssignmentAction(PDO $db, array $user)`**: accepts/declines owner assignment requests.
- **`handleJoinOrgAction(PDO $db, array $user)`**: submits organization join requests with eligibility checks.

Used by: POST action dispatch in `index.php` for approval workflow actions.

---

## `src/actions/content_actions.php`

### Content and Transaction Action Handlers

- **`handleUpdateMyOrgAction(PDO $db, array $user)`**: owner update flow for organization name/description.
- **`handleAddAnnouncementAction(PDO $db, array $user)`**: owner creates announcements.
- **`handleDeleteAnnouncementAction(PDO $db, array $user)`**: owner deletes own announcements.
- **`handlePinAnnouncementAdminAction(PDO $db, array $user)`**: admin pins announcement globally.
- **`handleUnpinAnnouncementAdminAction(PDO $db, array $user)`**: admin unpins announcement.
- **`handleAddTransactionAction(PDO $db, array $user, array $config)`**: owner creates transactions with optional receipt upload.
- **`handleUpdateTransactionAction(PDO $db, array $user)`**: owner submits update request for transaction.
- **`handleDeleteTransactionAction(PDO $db, array $user)`**: owner submits delete request for transaction.

Used by: POST action dispatch in `index.php`.

---

## `src/pages/public_pages.php`

### Public Route Handlers

- **`handleLogoutPage()`**: destroys session and redirects to home.
- **`handleHomePage(PDO $db, ?array $user)`**: renders landing page with organization preview.
- **`handleLoginPage(array $config)`**: renders login page (Google OAuth and resend-verification support).
- **`handleRegisterPage()`**: renders registration page and privacy consent modal; supports `privacy=1` query to open the modal on load.
- **`handleVerifyEmailPage()`**: email verification result screen.
- **`handleForgotPasswordPage()`**: forgot-password request screen.
- **`handleResetPasswordPage()`**: password reset screen.

Used by: GET page route dispatch in `index.php`.

---

## `src/pages/admin_pages.php`

### Admin Page Handlers

- **`handleAdminOrgsPage(PDO $db)`**: admin organization management page (`admin_orgs`) with create/update/delete and owner assignment UI.
- **`handleAdminStudentsPage(PDO $db)`**: searchable student/owner listing for admins.
- **`handleAdminRequestsPage(PDO $db)`**: admin queue for transaction change requests.
- **`handleAdminAuditPage(PDO $db, array $user)`**: audit log page with time window filter.
- **`handleMyOrgAdminPage(PDO $db)`**: admin organization overview inside `my_org` route.

Used by: GET page route dispatch in `index.php`.

---

## `src/pages/community_pages.php`

### Community Page Handlers

- **`handleAnnouncementsPage(PDO $db, $user, string $announcementCutoff)`**: announcement feed with slider and pin controls.
- **`handleOrganizationsPage(PDO $db, array $user)`**: all-organizations listing with join request status/action UI.
- **`handleProfilePage(array $user)`**: profile settings page with account summary, organization info, and change-password modal.

Used by: GET page route dispatch in `index.php`.

---

## `src/pages/owner_pages.php`

### Owner Page Handlers

- **`handleMyOrgOwnerPage(PDO $db, array $user, string $announcementCutoff)`**: owner-facing `my_org` page renderer (pending join requests, org updates, announcements, transactions, request history, plus transaction type/date filters).

Used by: GET page route dispatch in `index.php`.

---

## `src/services/dashboard_data.php`

### Dashboard Data Assembly

- **`buildDashboardViewData(PDO $db, array $user, array $config, string $announcementCutoff, string $recentReportCutoffDate)`**:
  - centralizes dashboard query and KPI/trend aggregation logic
  - returns prepared arrays/metrics for dashboard rendering
  - keeps `index.php` focused on route and view structure

Used by: dashboard route in `index.php`.

---

## `src/pages/dashboard_page.php`

### Dashboard Page Renderer

- **`handleDashboardPage(array $dashboardData, array $user)`**:
  - renders the dashboard page shell from prepared view data
  - emits serialized client chart payload
  - loads external dashboard scripts (`Chart.js` CDN and `static/js/dashboard-page.js`)

Used by: dashboard route dispatch in `index.php`.

---

## `src/pages/dashboard_page_markup.php`

### Dashboard Markup Partial

- Contains dashboard HTML/UI markup (cards, tables, and modals) separated from route logic.
- Keeps `index.php` focused on routing and delegates large view markup to a dedicated partial.

Used by: `handleDashboardPage()` in `src/dashboard_page.php`.

---

## Notes on Reusability

Most reusable helper and route-handler logic has now been extracted from `index.php` into dedicated modules under `src/` (`organization.php`, `pagination.php`, `notifications.php`, `integrations.php`, `maintenance.php`, `auth_flows.php`, `workflows.php`, `content_actions.php`, `public_pages.php`, `admin_pages.php`, `community_pages.php`, `owner_pages.php`, `dashboard_data.php`, `dashboard_page.php`, and `dashboard_page_markup.php`). Keeping future shared logic in module files instead of route files will improve maintainability and testability as the app grows.
