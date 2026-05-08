# INVOLVE Next Execution Checklist

## Purpose
This is the working execution checklist for migrating INVOLVE from the current custom PHP-rendered frontend to a **Laravel + Inertia.js + React** stack.

Use this beside:
- [INVOLVE_NEXT_MIGRATION_PLAN.md](/c:/xampp/htdocs/websys/docs/reference/INVOLVE_NEXT_MIGRATION_PLAN.md)

This checklist is intentionally practical:
- what to do
- what to produce
- how to know the phase is complete

---

## Phase 0: Freeze and Inventory

### Goal
Capture the current system clearly enough that we can migrate without losing behavior.

### Tasks
- [ ] confirm the final current route list
- [ ] confirm authenticated role access per page
- [ ] list all current POST actions and owning feature areas
- [ ] identify pages with the heaviest business logic coupling
- [ ] identify shared UI patterns to replicate first:
  - [ ] navbar
  - [ ] dropdowns
  - [ ] modals
  - [ ] pagination
  - [ ] search panels
  - [ ] cards
- [ ] identify data-heavy workflows that must be preserved exactly:
  - [ ] auth
  - [ ] organizations join flow
  - [ ] owner finance
  - [ ] admin transaction requests
  - [ ] audit logs

### Source files to inspect
- `includes/routes/pages.php`
- `includes/routes/actions.php`
- `includes/features/auth/`
- `includes/features/dashboard/`
- `includes/features/organizations/`
- `includes/features/admin/`
- `includes/features/transactions/`

### Deliverables
- [ ] route inventory confirmed
- [ ] role access map confirmed
- [ ] workflow dependency notes confirmed

### Exit Criteria
- we can explain what each major page and action does without guessing

---

## Phase 1: Laravel Foundation

### Goal
Stand up the new Laravel + Inertia + React app shell beside the current system.

### Tasks
- [ ] decide project placement:
  - [ ] sibling app
  - [ ] subdirectory app
  - [ ] separate migration branch structure
- [ ] scaffold Laravel application
- [ ] install Inertia.js server adapter
- [ ] install React frontend adapter
- [ ] install Tailwind
- [ ] configure Vite
- [ ] verify Laravel app boots locally
- [ ] configure app name and base layout
- [ ] configure environment to connect to the current MySQL database

### Suggested output structure
```text
involve-next/
  app/
  bootstrap/
  config/
  database/
  public/
  resources/js/
  routes/
```

### Deliverables
- [ ] Laravel app boots
- [ ] Inertia renders a React page
- [ ] database connection works

### Exit Criteria
- a working React page is rendered through Laravel + Inertia against the current database config

---

## Phase 2: Shared App Shell

### Goal
Recreate the system-wide shell before migrating bigger workflows.

### Tasks
- [ ] create React app layout
- [ ] create auth layout
- [ ] recreate navbar
- [ ] recreate account dropdown
- [ ] recreate theme toggle behavior
- [ ] establish shared page container sizing
- [ ] create base button variants
- [ ] create base modal system
- [ ] create base dropdown system
- [ ] create shared empty-state component
- [ ] create shared search panel component

### Suggested frontend targets
```text
resources/js/
  Layouts/AppLayout.jsx
  Layouts/AuthLayout.jsx
  Components/AppNavbar.jsx
  Components/ThemeToggle.jsx
  Components/Modal.jsx
  Components/Dropdown.jsx
  Components/Button.jsx
  Components/EmptyState.jsx
  Components/SearchPanel.jsx
```

### Deliverables
- [ ] app shell exists
- [ ] auth shell exists
- [ ] shared components render consistently

### Exit Criteria
- multiple pages can share one stable layout and component system

---

## Phase 3: Auth Migration

### Goal
Move auth first so the new app has a real entry flow.

### Tasks
- [ ] migrate login page
- [ ] migrate register page
- [ ] migrate forgot password page
- [ ] migrate reset password page
- [ ] migrate verify email page
- [ ] migrate logout flow
- [ ] preserve validation rules
- [ ] preserve email verification requirements
- [ ] preserve theme parity

### Backend work
- [ ] create auth controllers
- [ ] create request validation classes
- [ ] create auth middleware mapping
- [ ] map existing user fields cleanly to Eloquent

### Deliverables
- [ ] auth works end-to-end in Laravel/Inertia

### Exit Criteria
- users can register, verify, log in, reset password, and log out in the new stack

---

## Phase 4: Dashboard Migration

### Goal
Move the dashboard as the first major authenticated app page.

### Tasks
- [ ] recreate KPI cards
- [ ] recreate finance status panel
- [ ] recreate recent announcements/activity block
- [ ] recreate organizations preview
- [ ] recreate recent reports table
- [ ] recreate financial summary section
- [ ] migrate chart rendering
- [ ] recreate dashboard modals
- [ ] keep dashboard data aggregation in Laravel service/controller layer

### Backend targets
- [ ] `DashboardController`
- [ ] `DashboardDataService`

### Frontend targets
- [ ] `Pages/Dashboard/Index.jsx`
- [ ] dashboard chart component
- [ ] dashboard metric card component

### Deliverables
- [ ] dashboard page parity achieved

### Exit Criteria
- dashboard is usable without relying on the old PHP-rendered dashboard

---

## Phase 5: Organization Directory and Profile

### Goal
Move the main user-facing organization and profile pages.

### Tasks
- [ ] migrate organizations directory
- [ ] migrate join request flow
- [ ] migrate organization visibility labels
- [ ] migrate profile page
- [ ] migrate profile image actions
- [ ] preserve owner/student visibility rules

### Backend targets
- [ ] `OrganizationController`
- [ ] `ProfileController`

### Frontend targets
- [ ] `Pages/Organizations/Index.jsx`
- [ ] `Pages/Profile/Show.jsx`

### Deliverables
- [ ] org directory and profile work in the new stack

### Exit Criteria
- users can browse orgs, request joining, and manage profile in React/Inertia

---

## Phase 6: Owner Workspace Migration

### Goal
Move owner workflows into dedicated React pages.

### Tasks
- [ ] migrate organization management page
- [ ] migrate membership management page
- [ ] migrate finance management page
- [ ] migrate communications/announcements page behavior
- [ ] migrate transaction request flow
- [ ] preserve owner role restrictions

### Frontend targets
- [ ] `Pages/Owner/OrganizationManagement.jsx`
- [ ] `Pages/Owner/MembershipManagement.jsx`
- [ ] `Pages/Owner/FinancialManagement.jsx`

### Deliverables
- [ ] owner workspace usable in the new stack

### Exit Criteria
- owners can manage org settings, members, announcements, and finance without the legacy pages

---

## Phase 7: Admin Workspace Migration

### Goal
Move admin workflows into the new stack.

### Tasks
- [ ] migrate manage organizations
- [ ] migrate student records
- [ ] migrate owner transaction requests
- [ ] migrate audit logs
- [ ] migrate organization overview
- [ ] migrate search/member modals

### Backend targets
- [ ] `Admin/OrganizationsController`
- [ ] `Admin/StudentsController`
- [ ] `Admin/TransactionRequestsController`
- [ ] `Admin/AuditLogsController`

### Frontend targets
- [ ] `Pages/Admin/Organizations/Index.jsx`
- [ ] `Pages/Admin/Students/Index.jsx`
- [ ] `Pages/Admin/TransactionRequests/Index.jsx`
- [ ] `Pages/Admin/AuditLogs/Index.jsx`

### Deliverables
- [ ] admin workspace parity achieved

### Exit Criteria
- admins can complete their core workflows in the new stack

---

## Phase 8: Shared UX Hardening

### Goal
Stabilize the new stack before full cutover.

### Tasks
- [ ] verify modal focus behavior
- [ ] verify dropdown positioning behavior
- [ ] verify pagination anchor behavior
- [ ] verify mobile/desktop table-card transitions
- [ ] verify theme consistency
- [ ] verify form feedback states
- [ ] verify confirmation dialogs
- [ ] verify empty states
- [ ] verify search filters and resets
- [ ] verify accessibility basics

### Deliverables
- [ ] stable shared interaction layer

### Exit Criteria
- common UI patterns behave consistently across migrated pages

---

## Phase 9: Cutover Preparation

### Goal
Prepare to switch the primary app to the new stack safely.

### Tasks
- [ ] confirm migrated feature coverage
- [ ] identify remaining legacy-only pages
- [ ] create rollback plan
- [ ] map old routes to new routes
- [ ] plan session/auth continuity
- [ ] prepare launch checklist

### Deliverables
- [ ] cutover checklist
- [ ] rollback checklist

### Exit Criteria
- we know exactly what remains before switching traffic to the new app

---

## Phase 10: Legacy Retirement

### Goal
Retire the old renderer when safe.

### Tasks
- [ ] remove legacy route reliance
- [ ] archive or remove old page-rendering code
- [ ] preserve historical docs
- [ ] update maintainer docs

### Deliverables
- [ ] new stack becomes the source of truth

### Exit Criteria
- the old PHP-rendered frontend is no longer needed for normal app operation

---

## Immediate Next Actions

If we are starting right now, do these first:

1. [ ] choose where the Laravel app will live
2. [ ] scaffold Laravel + Inertia + React
3. [ ] connect to current MySQL
4. [ ] create shared app shell
5. [ ] migrate auth first

### Recommended Placement Decision
Use a **sibling app**, not a subdirectory inside the current `websys` app.

#### Recommended local path
```text
C:\xampp\htdocs\involve-next
```

#### Why this is the best option
- keeps the current app untouched while migration is in progress
- avoids mixing Laravel public/build tooling with the current custom PHP app
- avoids `.htaccess`, `public/`, and asset pipeline conflicts
- makes rollback easy because the current app remains independently runnable
- gives Laravel its normal expected directory structure

#### Do not prefer for now
- putting Laravel directly inside `websys/`
- replacing the current root before feature parity exists
- merging Vite, Composer, and routing concerns too early

#### Practical local workflow
- current app stays at:
  - `C:\xampp\htdocs\websys`
- new app lives at:
  - `C:\xampp\htdocs\involve-next`
- both can point to the same MySQL database during migration
- compare old and new flows side by side during development

---

## Decision Notes

### Keep
- MySQL
- PHP backend knowledge
- role-based permissions
- auditability patterns

### Avoid for now
- big-bang rewrite
- API-first rewrite before page migration
- adding major new features during foundation migration

### Special note
`INVOLVE BudgetFlow` should remain a later feature track unless the new Laravel foundation is already stable enough to support it cleanly.
