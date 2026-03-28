Student Organization Management and Budget Transparency System
Comprehensive Project Documentation

Version: 1.0.0  
Last Updated: March 8, 2026  
Platform: PHP 8.2+ / MySQL or SQLite / Tailwind CSS

---

Table of Contents

1. Project Overview
2. System Architecture
3. Directory Structure
4. Database Schema
5. Features and Functionality
6. User Roles and Permissions
7. Routes and Actions
8. Security Features
9. File Management
10. Configuration
11. Deployment Guide
12. Development Guide
13. Troubleshooting

---

Project Overview

This project is a role-based web application for managing student organizations and publishing transparent financial records. It allows administrators to manage organizations and approvals, owners to operate organization activities, and students to discover and join organizations while monitoring published budget activity.

Key Objectives

- Centralize student organization records and operations
- Provide transparent financial reporting (income and expenses)
- Enforce approval workflows for sensitive changes
- Keep role permissions clear and auditable
- Maintain lightweight deployment using XAMPP-compatible PHP + PDO

Target Users

- Students
- Organization owners
- System administrators

---

System Architecture

Technology Stack

Backend:
- PHP 8.2+
- PDO (prepared statements)
- MySQL or SQLite

Frontend:
- HTML5
- Tailwind CSS (CDN)
- Vanilla JavaScript
- Chart.js (dashboard visualization)

Server Requirements:
- PHP 8.2+
- Apache 2.4+ (recommended for XAMPP)
- MySQL/MariaDB 10.4+ (only if using MySQL driver)

Architecture Pattern

Single-entry routing pattern:
- `index.php` handles route rendering and POST actions
- `src/` contains reusable helpers and route/data modules (`db.php`, `auth.php`, `helpers.php`, `layout.php`, and extracted page/data handlers)

Session-based authentication:
- Login state and role checks are session-driven
- CSRF validation on POST requests
- Role guards for admin/owner/student feature boundaries

---

Directory Structure

```text
websys/
|-- index.php                    # Main route + action controller and view rendering
|-- README.md                    # Primary setup and usage documentation
|-- schema.sql                   # Baseline MySQL schema reference
|-- public/
|   `-- uploads/                 # Uploaded receipt files
|-- scripts/
|   |-- seed/
|   |   |-- seed_dummy_data.php  # Seed users/orgs/announcements and requests
|   |   `-- seed_dummy_reports.php # Seed financial transactions
|   `-- tests/
|       `-- test_organization_helpers.php # Helper regression script
|-- src/
|   |-- core/
|   |   |-- auth.php             # Current user lookup and role guards
|   |   |-- config.php           # Database, app, uploads, OAuth config
|   |   |-- db.php               # PDO connection + auto-initialize schema/columns
|   |   |-- helpers.php          # CSRF, uploads, flash, audit, icon registry, and utility helpers
|   |   `-- layout.php           # Shared shell, UI chrome, and dashboard styling
|   |-- lib/
|   |   |-- integrations.php     # Base URL, OAuth checks, and external JSON fetch helper
|   |   |-- maintenance.php      # Data lifecycle cleanup helpers
|   |   |-- notifications.php    # Login update collection and popup marker helpers
|   |   |-- organization.php     # Organization ownership and visibility helpers
|   |   `-- pagination.php       # Shared pagination data + rendering helpers
|   |-- actions/
|   |   |-- auth_flows.php       # Google and email auth flow handlers
|   |   |-- content_actions.php  # Owner/admin content and finance action handlers
|   |   `-- workflows.php        # Admin/owner workflow handlers for approval flows
|   |-- pages/
|   |   |-- admin_pages.php      # Admin page route handlers (admin_orgs, students, requests, audit)
|   |   |-- community_pages.php  # Announcements and organizations pages
|   |   |-- owner_pages.php      # Owner page renderer for my_org route
|   |   |-- public_pages.php     # Home/login/register/logout page handlers
|   |   |-- dashboard_page.php   # Dashboard page renderer (server-side)
|   |   `-- dashboard_page_markup.php # Dashboard HTML partial (cards/tables/modals)
|   `-- services/
|       `-- dashboard_data.php   # Dashboard data aggregation for KPIs, trends, and panels
|-- static/
|   |-- README.md                # Static demo file guide
|   |-- demo/
|   |   |-- system-static-demo.css
|   |   |-- system-static-demo.html
|   |   `-- system-static-demo.js
|   `-- js/
|       `-- dashboard-page.js    # External dashboard client logic (charts + modals)
|-- storage/                     # SQLite DB location when sqlite driver is used
`-- docs/
  |-- architecture/
  |   `-- PROJECT_DOCUMENTATION.md # This file
  `-- reference/
      `-- FUNCTION_ANALYSIS.md     # Reusable functions and helper reference
```

---

Database Schema

Primary Tables

`users`
- Purpose: user accounts and role assignment
- Important fields:
  - `id`, `name`, `email`, `password_hash`
  - `role` (`admin`, `student`, `owner`)
  - `institute`, `program`
  - `email_verified`, `email_verified_at`
  - `activation_token`, `activation_expires`
  - `reset_token`, `reset_expires`
  - `account_status`, `password_changed_at`
  - `last_login_at`, `last_login_ip`
  - `created_at`

`user_sessions`
- Purpose: track active/remembered user sessions

`login_history`
- Purpose: record login successes/failures and context

`password_history`
- Purpose: track historical password hashes for audit/security controls

`security_notifications`
- Purpose: track generated account security notifications

`organizations`
- Purpose: organization profiles and ownership
- Important fields:
  - `id`, `name`, `description`
  - `org_category` (`collegewide`, `institutewide`, `program_based`)
  - `target_institute`, `target_program`
  - `owner_id`, `created_at`

`organization_members`
- Purpose: approved student membership records
- Important fields:
  - `organization_id`, `user_id`, `joined_at`
  - unique organization-user membership pair

`announcements`
- Purpose: organization announcements shown in dashboard and announcements page
- Important fields:
  - `organization_id`, `title`, `content`, `created_at`
  - `is_pinned`, `pinned_at`

`financial_transactions`
- Purpose: income and expense records per organization
- Important fields:
  - `organization_id`, `type` (`income`, `expense`)
  - `amount`, `description`, `transaction_date`
  - `receipt_path`, `created_at`

`owner_assignments`
- Purpose: admin-sent owner assignment requests
- Important fields:
  - `organization_id`, `student_id`, `status` (`pending`, `accepted`, `declined`)
  - `created_at`, `updated_at`

`organization_join_requests`
- Purpose: student join request workflow
- Important fields:
  - `organization_id`, `user_id`, `status` (`pending`, `approved`, `declined`)
  - `created_at`, `updated_at`

`transaction_change_requests`
- Purpose: owner-submitted request to update/delete transactions requiring admin review
- Important fields:
  - `transaction_id`, `organization_id`, `requested_by`
  - `action_type` (`update`, `delete`)
  - `proposed_*` fields for update proposal
  - `status` (`pending`, `approved`, `rejected`)
  - `admin_note`, `created_at`, `updated_at`

`audit_logs`
- Purpose: track critical actions for traceability
- Important fields:
  - `user_id`, `action`, `entity_type`, `entity_id`
  - `details`, `ip_address`, `user_agent`, `created_at`

Initialization Behavior

- `src/db.php` auto-creates schema on boot.
- Missing columns are added through compatibility checks.
- A default admin account is created if no admin exists.

---

Features and Functionality

Shared and Public Features

Authentication and Access
- Registration for students
- Email/password login
- Optional Google OAuth login
- Logout and session reset
- Email verification before first login
- Password recovery (forgot/reset)
- Profile management and change-password (non-admin users)

Dashboard
- KPI overview cards (income, expense, net)
- Trend chart visualization
- Recent transactions and announcements
- Organization and summary modals

Announcements
- Recent announcements view (30-day window)
- Important announcement pinning by admin
- Organization context for each post

Organizations
- Organization listing and visibility labels
- Category-aware visibility filtering
- Join request workflow

Admin Features

Organization Management (`?page=admin_orgs`)
- Create/update/delete organization records
- Configure category and targeting metadata
- Assign owner through pending acceptance flow

Student Directory (`?page=admin_students`)
- Search/filter students by name or email
- View role and registration date

Transaction Request Processing (`?page=admin_requests`)
- Review pending owner requests
- Approve/reject update or delete requests
- Add admin notes

Audit Trail (`?page=admin_audit`)
- View action logs with optional day-range filtering

Owner Features (`?page=my_org`)
- Update organization profile
- Create/delete announcements
- Add transaction entries with optional receipt upload
- Filter transaction history by `type` and sort by `transaction_date`
- Request update/delete for existing transactions
- Review request statuses
- Process pending membership requests

Student Features

Membership and Participation
- Browse organizations
- Submit join requests
- Track request state (pending/approved/declined)
- Accept/decline owner assignment offers

---

User Roles and Permissions

Admin
- Full administrative access in this project
- Can manage organizations, owners, approval queues, and audit logs
- Can pin/unpin announcements
- Profile settings route is intentionally unavailable to admin accounts

Owner
- Can manage assigned organization content
- Can add transactions directly
- Cannot directly edit/delete old transactions without admin approval
- Can approve or decline join requests for their organization

Student
- Can register and login
- Can request membership in eligible organizations
- Can view dashboard and organization/announcement data
- Can accept or decline owner assignments sent by admin

---

Routes and Actions

Page Routes (GET `?page=`)

- `home`
- `login`
- `register`
- `verify_email`
- `forgot_password`
- `reset_password`
- `dashboard`
- `admin_orgs`
- `admin_students`
- `admin_requests`
- `admin_audit`
- `announcements`
- `organizations`
- `my_org`
- `profile` (owner/student only)
- `google_login`
- `google_callback`
- `logout`

Form Actions (POST `action=`)

Authentication:
- `register`
- `login`
- `resend_verification`
- `forgot_password`
- `reset_password`
- `change_password`
- `update_profile`

Admin:
- `create_org`
- `update_org_admin`
- `delete_org`
- `assign_owner`
- `process_tx_change_request`
- `pin_announcement_admin`
- `unpin_announcement_admin`

Owner and Student:
- `respond_owner_assignment`
- `join_org`

Owner only:
- `respond_join_request`
- `update_my_org`
- `add_announcement`
- `delete_announcement`
- `add_transaction`
- `update_transaction` (creates request)
- `delete_transaction` (creates request)

---

Security Features

Authentication
- Password hashing (`password_hash`)
- Password verification (`password_verify`)
- Session regeneration on successful login

CSRF Protection
- Token generation and validation for POST operations
- Invalid token attempts are rejected with error feedback

Authorization
- Role restrictions enforced through `requireRole()` checks
- Unauthorized route access redirected safely

Input and Upload Validation
- Required field checks on critical forms
- Password strength checks on registration
- Receipt upload validates extension, MIME, and size

Rate Limiting
- Login and registration throttling by session key and client context

Auditing
- Significant actions are written to `audit_logs` with metadata

---

File Management

Upload Directory
- `public/uploads/` for receipt files

Upload Rules
- Allowed: `jpg`, `jpeg`, `png`, `pdf`
- Max size: 5MB
- Server-side MIME verification required

File Naming
- Uploaded receipts are stored as generated random filenames prefixed with `receipt_`

---

Configuration

Primary Config File
- `src/config.php`

Database Settings
```php
'db' => [
    'driver' => 'mysql',
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

Google OAuth Settings
```php
'google_oauth' => [
    'client_id' => '',
    'client_secret' => '',
],
```

---

Deployment Guide

Recommended (XAMPP)

1. Place project in `C:\xampp\htdocs\websys`
2. Start Apache and MySQL
3. Open: `http://localhost/websys/index.php`

Alternative (PHP Built-in Server)

```bash
php -S localhost:8000
```

Then open:
- `http://localhost:8000/index.php`

Production Checklist

- Change default admin credentials immediately
- Configure secure database credentials
- Enable HTTPS and secure cookie settings in production
- Ensure upload directory permissions are correct
- Schedule DB and uploads backup

---

Development Guide

Core Files
- `index.php`: route and action processing
- `src/core/db.php`: initialization and schema compatibility checks
- `src/core/helpers.php`: CSRF, flash, uploads, audit log helper set
- `src/core/auth.php`: user/session role helper functions
- `src/core/layout.php`: global shell and dashboard visual behavior

Seed and Demo Data

```bash
php scripts/seed/seed_dummy_data.php
php scripts/seed/seed_dummy_reports.php
php scripts/tests/test_organization_helpers.php
```

Testing Suggestions

- Test role-restricted pages per role
- Test join request cycle (submit, approve/decline)
- Test transaction request cycle (request, approve/reject)
- Test upload validation for each allowed and disallowed file type

Documentation References

- `README.md` - setup and quick usage guide
- `docs/architecture/PROJECT_DOCUMENTATION.md` - full architecture and feature documentation
- `docs/reference/FUNCTION_ANALYSIS.md` - function-level reference for `src/` and key `index.php` helpers
- `static/README.md` - static frontend demo notes

---

Troubleshooting

Database Problems

Symptoms:
- App fails at startup
- Missing table/column errors

Checks:
- Verify `src/config.php` DB values
- Ensure MySQL service is running (MySQL mode)
- Confirm write permissions for `storage/` (SQLite mode)

Authentication Issues

Symptoms:
- Invalid login despite known credentials
- Frequent lockout behavior

Checks:
- Validate user email and password
- Confirm default admin account exists
- Wait for temporary rate-limit windows to expire

Google OAuth Issues

Symptoms:
- Callback errors
- Missing token or profile failure

Checks:
- Set client ID/secret in `src/config.php`
- Ensure redirect URI matches exactly in Google console
- Verify base URL/host consistency

Upload Issues

Symptoms:
- Receipt rejected or not saved

Checks:
- Verify file type and size
- Confirm `public/uploads/` write permissions
- Check PHP upload limits in `php.ini`

---

Document Version: 1.0.0  
Last Updated: March 8, 2026
