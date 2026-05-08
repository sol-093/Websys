# INVOLVE Next Migration Plan

## Update Name
**INVOLVE Next**

## Goal
Move the current INVOLVE system from a custom PHP-rendered frontend to a **Laravel + Inertia.js + React** hybrid stack while preserving:

- current MySQL data
- PHP business rules
- role-based workflows
- auditability and financial transparency features

This is a **migration plan**, not a big-bang rewrite.

---

## Why This Stack

### Frontend
- **React**
- better component reuse
- cleaner modal/dropdown/table/chart handling
- easier long-term UI consistency

### Backend
- **Laravel**
- stronger routing, validation, auth, policies, queues, notifications, and migrations
- natural PHP upgrade path from the current architecture

### Bridge
- **Inertia.js**
- keeps server-side routing
- avoids building a full API first
- lets Laravel controllers render React pages directly

### Database
- keep **MySQL**
- migrate schema into Laravel migrations gradually

---

## Migration Strategy

Use a **strangler migration**:

1. build the new Laravel + Inertia app beside the current system
2. migrate one workflow area at a time
3. keep the current app as the live reference during the transition
4. cut over only when major workflows are stable

This is the safest path for the current project.

---

## Current System Inventory

### Current Page Routes
From `includes/routes/pages.php`

#### Public pages
- `home`
- `about`
- `login`
- `register`
- `verify_email`
- `forgot_password`
- `reset_password`
- `logout`

#### Authenticated pages
- `profile`
- `admin_orgs`
- `admin_students`
- `admin_requests`
- `admin_audit`
- `notifications`
- `announcements`
- `organizations`
- `my_org`
- `my_org_manage`
- `my_org_members`
- `my_org_finance`
- default authenticated fallback: `dashboard`

### Current Action Routes
From `includes/routes/actions.php`

#### GET actions
- `search`
- `export_transactions`
- `google_login`
- `google_callback`

#### Public POST actions
- `register`
- `login`
- `resend_verification`
- `forgot_password`
- `reset_password`

#### Authenticated POST actions
- `change_password`
- `update_profile`
- `create_org`
- `update_org_admin`
- `delete_org`
- `assign_owner`
- `process_tx_change_request`
- `respond_owner_assignment`
- `join_org`
- `respond_join_request`
- `remove_org_member`
- `update_my_org`
- `add_announcement`
- `delete_announcement`
- `pin_announcement_admin`
- `unpin_announcement_admin`
- `add_transaction`
- `update_transaction`
- `delete_transaction`
- `complete_onboarding`
- `restart_onboarding`

### Current Feature Areas

#### Auth
- `includes/features/auth/`

#### Dashboard
- `includes/features/dashboard/`

#### Organizations and profile
- `includes/features/organizations/`

#### Admin
- `includes/features/admin/`

#### Transactions
- `includes/features/transactions/`

#### Shared helpers and libraries
- `includes/lib/`
- `includes/core/`

---

## Target Laravel + Inertia Structure

### Backend
```text
app/
  Actions/
  Http/Controllers/
  Http/Requests/
  Models/
  Policies/
  Services/
  Support/
```

### Frontend
```text
resources/js/
  Components/
  Layouts/
  Pages/
    Auth/
    Dashboard/
    Organizations/
    Owner/
    Admin/
  Hooks/
  Lib/
```

### Suggested page groups
- `Pages/Auth`
- `Pages/Dashboard`
- `Pages/Organizations`
- `Pages/Owner`
- `Pages/Admin`

---

## Mapping Current Features to the New Stack

### Auth
Current:
- `includes/features/auth/*`

Target:
- Laravel auth controllers / requests / policies
- React pages under `Pages/Auth`

### Dashboard
Current:
- `includes/features/dashboard/*`

Target:
- `DashboardController`
- `Pages/Dashboard/Index.jsx`

### Organizations and Profile
Current:
- `includes/features/organizations/*`

Target:
- `OrganizationController`
- `ProfileController`
- React pages under `Pages/Organizations`

### Owner Workspace
Current:
- `my_org_manage`
- `my_org_members`
- `my_org_finance`

Target:
- `Owner/OrganizationManagement`
- `Owner/MembershipManagement`
- `Owner/FinancialManagement`

### Admin Workspace
Current:
- `admin_orgs`
- `admin_students`
- `admin_requests`
- `admin_audit`

Target:
- `Admin/Organizations`
- `Admin/Students`
- `Admin/TransactionRequests`
- `Admin/AuditLogs`

---

## Migration Phases

## Phase 0: Freeze and Document
Before building the new stack, freeze behavior and inventory the current app.

### Tasks
- document route inventory
- document role rules
- identify critical workflows
- identify business logic that must not change during migration

### Deliverables
- this migration blueprint
- role/route/workflow map

---

## Phase 1: Laravel + Inertia Foundation
Create the new base app without replacing the current app yet.

### Tasks
- scaffold Laravel app
- install Inertia.js + React
- install Tailwind
- connect to current MySQL
- set up shared app layout
- set up theme support
- configure auth/session/csrf baseline

### Deliverables
- working Laravel shell
- working Inertia React page
- database connection to existing schema

---

## Phase 2: Database and Domain Migration
Move the schema into Laravel migrations and models.

### Tasks
- create migrations for current tables
- create Eloquent models and relationships
- add policies and role helpers
- move shared helper logic into service classes where appropriate

### Core tables to migrate first
- `users`
- `organizations`
- `organization_members`
- `announcements`
- `financial_transactions`
- workflow/audit/session tables

### Deliverables
- Laravel migrations
- Eloquent models
- clear domain relationships

---

## Phase 3: Auth and Shared Layout
Migrate the smallest but most shared surfaces first.

### Migrate
- login
- register
- forgot/reset password
- verify email
- navbar
- theme toggle
- profile dropdown

### Deliverables
- full React auth shell
- shared layout and utility components

---

## Phase 4: Dashboard
Migrate the dashboard into React while keeping Laravel controllers as the data source.

### Migrate
- KPI cards
- activity sections
- organizations preview
- announcements preview
- chart panels
- dashboard modals

### Deliverables
- `DashboardController`
- `Pages/Dashboard/Index.jsx`

---

## Phase 5: Organizations and Profile
Migrate the user-facing organization and profile workflows.

### Migrate
- all organizations
- join/request flow
- profile page
- profile media actions
- organization visibility behavior

### Deliverables
- organization directory in React
- profile page in React

---

## Phase 6: Owner Workspace
Migrate the owner workspace as separate React pages.

### Migrate
- organization management
- membership management
- financial management
- announcements workspace

### Deliverables
- owner pages with clean component reuse

---

## Phase 7: Admin Workspace
Migrate admin workflows into React/Inertia.

### Migrate
- manage organizations
- student records
- owner transaction requests
- audit logs
- organization overview
- search/member modals

### Deliverables
- admin pages in React
- shared table/filter/modal patterns

---

## Phase 8: Reliability and UX Hardening
Stabilize the new stack before cutover.

### Standardize
- modals
- dropdowns
- confirmation dialogs
- pagination
- search empty states
- accessibility and focus behavior
- responsive table/card patterns

### Deliverables
- production-ready UX layer

---

## Phase 9: Legacy Cutover
Switch the system to the new app when major workflows are stable.

### Tasks
- route cutover
- remove dead renderer paths
- archive or retire `includes/features/*` page rendering
- keep rollback plan until stable

### Deliverables
- Laravel + Inertia becomes the main app

---

## Recommended First Migration Order

1. Foundation
2. Auth + shared layout
3. Dashboard
4. Organizations + profile
5. Owner workspace
6. Admin workspace
7. Reliability pass
8. Legacy cutover

---

## Immediate Start Tasks

These are the first concrete things to do next:

### Task 1: Create Laravel app shell
- create new Laravel app in a sibling directory or migration branch
- install Inertia React starter

### Task 2: Connect to current database
- configure `.env`
- confirm Laravel can read current MySQL data

### Task 3: Recreate core models
- `User`
- `Organization`
- `OrganizationMember`
- `Announcement`
- `FinancialTransaction`

### Task 4: Build shared app shell
- main layout
- auth layout
- navbar
- theme toggle

### Task 5: Migrate auth first
- login
- register
- forgot/reset
- verify email

---

## Notes for This Repo

### Keep current app running
Do not stop current PHP development abruptly.

### Avoid big-bang rewrite
No full rewrite in one shot.

### Reuse business rules first
Move behavior carefully; don’t rebuild logic from memory.

### BudgetFlow should wait
`INVOLVE BudgetFlow` is a strong next major feature, but it should be built **after** the new stack foundation is stable if we choose the migration path seriously.

---

## Recommended Next Artifact
After this document, the next best file to create is:

`docs/reference/INVOLVE_NEXT_EXECUTION_CHECKLIST.md`

That file should break the migration into weekly or checkpoint-based execution tasks.

