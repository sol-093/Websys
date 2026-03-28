# Student Organization Management and Budget Transparency System

A centralized web platform for student organization operations, announcements, and finance transparency.

Version: 1.0.0  
Last Updated: March 28, 2026

Recent Updates:
- Dashboard redesign with KPI cards, trend charts, and modal drill-downs
- Organization visibility categories (collegewide, institutewide, program-based)
- Owner assignment workflow (pending, accepted, declined)
- Join request approval flow for organization owners
- Transaction update/delete now uses admin approval requests
- Announcement pin/unpin support for admin
- Audit logging for critical actions
- Google OAuth login option
- Email verification flow (verify/resend) for newly registered users
- Password recovery flow (forgot/reset) with token expiry and email notifications
- Profile management page with modal change-password flow (non-admin accounts only)
- Owner transaction history filter by type (all/income/expense) and date sort
- Dark theme polish: removed per-card dark gradient artifacts and increased background gradient visibility
- Dark theme follow-up: restored frosted glass blur on dark cards while retaining cleaner panel surfaces
- Registration update: replaced the registration dropdown from class/program selection to institute selection

---

Table of Contents

1. Overview
2. Quick Start
3. Installation
4. Configuration
5. Default Credentials
6. Project Structure
7. Post-Reorg Map
8. Features
9. User Roles
10. Technology Stack
11. Database Setup
12. Security Features
13. Routes and Actions
14. File Management
15. Development Guide
16. Troubleshooting
17. Documentation
18. Support

---

Overview

This system helps admins, student owners, and students manage organization records and keep finances visible.

Key Objectives

- Centralized management of student organizations
- Transparent tracking of income and expenses
- Approval-based workflow for sensitive finance changes
- Role-based access to admin and owner tools
- Simple deployment on XAMPP with minimal setup

---

Quick Start

Prerequisites

- PHP 8.2 or higher
- MySQL/MariaDB 10.4+ (when using MySQL mode)
- Apache web server (XAMPP/LAMP/WAMP) or PHP built-in server
- Modern web browser

Minimum Requirements

- PHP: 8.2+
- MySQL: 10.4+ (optional if using SQLite mode)
- Apache: 2.4+
- Memory: 128MB PHP memory limit
- Disk Space: 100MB minimum

---

Installation

Step 1: Place Project in Web Root

```bash
cd C:\xampp\htdocs
```

Copy project folder as `websys`:

```bash
C:\xampp\htdocs\websys
```

Step 2: Start Services

1. Open XAMPP Control Panel
2. Start Apache
3. Start MySQL (only required for MySQL driver)

Step 3: Configure (Optional)

Edit `src/core/config.php` if you need custom DB credentials or app URL.

Step 4: Access the App

- Main URL: `http://localhost/websys/index.php`

On first run, the app initializes required tables automatically.

---

Configuration

Database Configuration

All settings are in `src/core/config.php`:

```php
'db' => [
    'driver' => 'mysql', // mysql or sqlite
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'websys_db',
    'username' => 'root',
    'password' => '',
    'sqlite_path' => __DIR__ . '/../storage/database.sqlite',
],
```

Application Settings

```php
'app_name' => 'Student Organization Management',
'upload_dir' => __DIR__ . '/../public/uploads',
'base_url' => '',
```

If `base_url` is blank, the app auto-detects URL from the request.

Google OAuth Configuration

In `src/core/config.php`:

```php
'google_oauth' => [
    'client_id' => '',
    'client_secret' => '',
],
```

Redirect URI examples:

- `http://localhost/websys/index.php?page=google_callback`
- `http://localhost:8000/index.php?page=google_callback`

PHP Configuration (Recommended)

```ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
memory_limit = 128M
```

---

Default Credentials

The system auto-creates one admin account if no admin exists.

Administrator

- Email: `admin@campus.local`
- Password: `admin123`
- Role: Admin
- Access: Full admin controls in this system

Important: Change default credentials after first login.

---

Project Structure

```text
websys/
|-- index.php                    # Main router, views, and form action handling
|-- README.md                    # Project documentation
|-- schema.sql                   # MySQL schema reference
|-- public/
|   `-- uploads/                 # Uploaded receipt files
|-- scripts/
|   |-- seed/
|   |   |-- seed_dummy_data.php  # Seed users, orgs, announcements, and requests
|   |   `-- seed_dummy_reports.php # Seed transaction history
|   `-- tests/
|       `-- test_organization_helpers.php # Helper regression test
|-- src/
|   |-- core/                    # Runtime foundation (config/db/auth/helpers/layout)
|   |-- lib/                     # Shared reusable helpers
|   |-- actions/                 # POST/action handlers
|   |-- pages/                   # Page render handlers
|   `-- services/                # Data assembly/services
|-- static/
|   |-- README.md                # Static demo documentation
|   |-- demo/
|   |   |-- system-static-demo.css
|   |   |-- system-static-demo.html
|   |   `-- system-static-demo.js
|   `-- js/
|       `-- dashboard-page.js
|-- docs/
|   |-- architecture/
|   |   `-- PROJECT_DOCUMENTATION.md
|   `-- reference/
|       `-- FUNCTION_ANALYSIS.md
`-- storage/                     # SQLite database storage (if sqlite driver is used)
```

---

Post-Reorg Map

This repository now follows a layered organization to keep routing, actions, rendering, and shared logic separated.

- `src/core/`: bootstrap/runtime foundation (`config.php`, `db.php`, `auth.php`, `helpers.php`, `layout.php`)
- `src/lib/`: reusable helper modules used by multiple pages/actions
- `src/actions/`: state-changing handlers (form posts, approvals, workflow transitions)
- `src/pages/`: page-level render handlers and page markup composition
- `src/services/`: data assembly logic used by complex pages (for example dashboard aggregation)
- `scripts/seed/`: seeders for dummy records and sample reports
- `scripts/tests/`: lightweight regression scripts for core helper behaviors
- `static/demo/`: standalone static prototype assets
- `static/js/`: production JS assets used by runtime pages
- `docs/architecture/`: architecture-level system documentation
- `docs/reference/`: function-level reference and behavior analysis

---

Features

Public and Shared Features

Authentication
- Email/password registration and login
- Google OAuth login (optional)
- Logout with session cleanup
- Email verification requirement for unverified accounts
- Forgot/reset password flow with secure reset tokens
- Profile update and password change (owner/student accounts)

Dashboard
- KPI cards (income, expense, balance)
- Monthly trend chart using Chart.js
- Recent announcements and transactions
- Financial summary per organization
- Modal views for organizations, announcements, and chart snapshot

Announcements
- Organization announcements feed
- 30-day visibility window
- Admin pin/unpin for important announcement priority

Organizations
- Browse organizations with visibility labels
- Join request submission and status tracking
- Institute/program eligibility checks for restricted organizations

Administrative Features

Organizations Management (`?page=admin_orgs`)
- Create, update, and delete organizations
- Configure visibility category and target institute/program
- Assign student as owner (pending acceptance flow)

Student Directory (`?page=admin_students`)
- View all student/owner accounts
- Filter by name or email

Transaction Request Review (`?page=admin_requests`)
- Review owner requests to update/delete transactions
- Approve or reject with optional admin note

Audit Logs (`?page=admin_audit`)
- Browse action logs by date range
- Track actor, action, entity, and details

Owner Features (`?page=my_org`)

- Edit owned organization details
- Post and delete announcements
- Add income/expense with optional receipt file
- Submit update/delete transaction requests for admin approval
- Review status of submitted transaction requests
- Approve/decline pending membership requests

---

User Roles

Admin

Permissions:
- Manage organizations (create/update/delete)
- Assign owners to organizations
- Approve/reject transaction change requests
- Pin/unpin announcements
- View student directory
- View audit logs
- View any organization overview

Owner

Permissions:
- Manage own organization profile
- Post/delete own organization announcements
- Add financial transactions
- Request transaction updates/deletes (admin approval required)
- Approve/decline join requests for owned organizations

Student

Permissions:
- Register and login
- View dashboard data and announcements
- Request to join eligible organizations
- Accept/decline owner assignments from admin

---

Technology Stack

Backend
- PHP 8.2+ (single-entry procedural style with helper modules)
- PDO for database access
- MySQL or SQLite support

Frontend
- HTML5
- Tailwind CSS via CDN
- Vanilla JavaScript
- Chart.js for data visualization

Architecture
- Single entry point: `index.php`
- Module helpers in `src/`
- Session-based authentication and flash messaging

---

Database Setup

Supported Database Modes

- MySQL (default)
- SQLite (file-based)

Main Tables

- `users`
- `organizations`
- `organization_members`
- `announcements`
- `financial_transactions`
- `owner_assignments`
- `organization_join_requests`
- `transaction_change_requests`
- `audit_logs`

Initialization Behavior

- `src/core/db.php` creates missing database/tables automatically
- Default admin account is auto-generated when no admin exists
- Missing columns for new features are added safely during boot

Optional Manual SQL

- Reference schema: `schema.sql`

---

Security Features

Authentication and Sessions

- Password hashing with `password_hash()`
- Password verification with `password_verify()`
- Session ID regeneration after login
- Role guards via `requireRole()`
- Account status checks on login (`active`, `suspended`, `banned`)

CSRF Protection

- CSRF token generation and verification on all form posts

Rate Limiting

- Login attempt throttling
- Registration attempt throttling
- Resend verification throttling
- Forgot-password request throttling

Validation and Upload Security

- Password complexity checks
- File extension + MIME validation for receipts
- Receipt size limit: 5MB
- Allowed receipt types: JPG, JPEG, PNG, PDF

Auditability

- Important user and admin actions logged to `audit_logs`
- Password changes and resets logged with notification support

---

Routes and Actions

Page Routes (`GET ?page=`)

- `home`
- `login`
- `register`
- `verify_email`
- `forgot_password`
- `reset_password`
- `dashboard` (default after login)
- `admin_orgs`
- `admin_students`
- `admin_requests`
- `admin_audit`
- `announcements`
- `organizations`
- `my_org`
- `profile` (not available to admins)
- `google_login`
- `google_callback`
- `logout`

Form Actions (`POST action=`)

Authentication
- `register`
- `login`
- `resend_verification`
- `forgot_password`
- `reset_password`
- `change_password`
- `update_profile`

Admin
- `create_org`
- `update_org_admin`
- `delete_org`
- `assign_owner`
- `process_tx_change_request`
- `pin_announcement_admin`
- `unpin_announcement_admin`

Student/Owner
- `respond_owner_assignment`
- `join_org`

Owner
- `respond_join_request`
- `update_my_org`
- `add_announcement`
- `delete_announcement`
- `add_transaction`
- `update_transaction` (creates approval request)
- `delete_transaction` (creates approval request)

---

File Management

Upload Directory

- `public/uploads/` - receipt files for financial transactions

Receipt Rules

- Allowed extensions: `.jpg`, `.jpeg`, `.png`, `.pdf`
- MIME type is validated server-side
- Max file size: 5MB
- Stored path format: `public/uploads/receipt_[random].ext`

---

Development Guide

Useful Scripts

Seed core dummy data:

```bash
php scripts/seed/seed_dummy_data.php
```

Seed dummy financial reports:

```bash
php scripts/seed/seed_dummy_reports.php
```

Run with PHP built-in server:

```bash
php -S localhost:8000
```

Then open:

- `http://localhost:8000/index.php`

Core Development Files

- `index.php` - routing, rendering, and action handling
- `src/core/db.php` - DB bootstrap and migrations-like column checks
- `src/core/helpers.php` - security helpers, flash messages, uploads, audit logging
- `src/core/layout.php` - shared shell, styling, navigation, and role-aware footer behavior

---

Troubleshooting

Database Connection Errors

Symptoms: App fails during load or cannot query tables.

Solutions:
- Verify DB credentials in `src/core/config.php`
- Ensure MySQL service is running (if using MySQL driver)
- Confirm `db.driver` matches your environment (`mysql` or `sqlite`)
- Check that `storage/` is writable for SQLite mode

Login Problems

Symptoms: Invalid credentials or repeated lockouts.

Solutions:
- Confirm admin default account: `admin@campus.local` / `admin123`
- Wait for rate-limit window to reset after repeated failures
- Verify session/cookie settings in PHP environment

Google Login Not Working

Symptoms: OAuth errors or callback failure.

Solutions:
- Set `google_oauth.client_id` and `google_oauth.client_secret`
- Ensure callback URL matches Google Console exactly
- Confirm `base_url` or host path is correct

File Upload Errors

Symptoms: Receipt upload rejected or not saved.

Solutions:
- Confirm file is JPG/JPEG/PNG/PDF
- Keep file size under 5MB
- Ensure `public/uploads/` is writable

---

Documentation

Primary Documentation

- `README.md` - complete setup and feature reference
- `docs/architecture/PROJECT_DOCUMENTATION.md` - comprehensive system documentation
- `docs/reference/FUNCTION_ANALYSIS.md` - reusable function and helper reference
- `schema.sql` - SQL schema reference
- `static/README.md` - static frontend demo guide

Static Demo Files

- `static/demo/system-static-demo.html`
- `static/demo/system-static-demo.css`
- `static/demo/system-static-demo.js`

---

Support

If you encounter issues:

1. Check `src/core/config.php` values first
2. Verify DB service and credentials
3. Review PHP/Apache error logs
4. Re-run seed scripts if you need demo data

Backup Recommendations

- Database backup before major updates
- Backup `public/uploads/` when receipt history is important

---

License

Internal academic use.

---

Version History

Version 1.0.0 (March 7, 2026)
- Consolidated dashboard and role-based workflows
- Added request-driven finance edits/deletes
- Added owner assignment and join request flows
- Added audit logs and Google OAuth support

Version 1.0.1 (March 28, 2026)
- Updated the shared admin-facing icon (`uiIcon('audit')`) to use the new admin logo SVG across all pages where it appears

Version 1.0.2 (March 28, 2026)
- Added a shared multi-column footer in `renderFooter()` with platform, role-tool, governance, and support sections
- Footer links now adapt by authentication state and role (admin, owner, student, guest)

Version 1.0.3 (March 28, 2026)
- Fixed footer Data Privacy Notice link to open the registration privacy consent flow directly
- Added query-parameter support on the register page so `privacy=1` auto-opens the privacy modal

Version 1.0.4 (March 28, 2026)
- Updated shared layout shell so short pages keep the footer at the viewport bottom (including login page)

Version 1.0.5 (March 28, 2026)
- Simplified footer bottom bar by removing icon shortcuts
- Reduced footer vertical spacing for a more compact layout
