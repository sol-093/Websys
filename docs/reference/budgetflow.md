
## Update Name
**INVOLVE BudgetFlow**

It fits the feature well: budgeting, expense routing, approval flow, and financial traceability, without sounding too heavy.

## Goal
Add a practical budgeting and expense approval workflow that fits the current INVOLVE architecture, without overcomplicating the first release.

## Recommended scope for v1
For the system right now, the best version is:

- organization owner creates a budget
- owner adds budget line items
- owner submits expense requests against a budget line
- admin approves or rejects requests
- approved requests become real expense transactions
- budget usage updates automatically
- audit logs and notifications stay in place

What I am intentionally **not** recommending yet for v1:
- multi-stage configurable approval chains
- attachment bundles beyond the existing receipt pattern
- editing approved budgets without restrictions
- fully custom workflow builders

That keeps it strong and usable without turning it into a giant refactor.

---

# INVOLVE BudgetFlow Implementation Plan

## Phase 0: Foundation Decisions
Lock the operating rules before coding.

### Decisions for v1
- approver is `admin`
- budget owners are `organization owners`
- budgets are tied to a date range
- approved expense requests automatically create a `financial_transactions` expense row
- rejected requests require an admin note
- owners can still manually add transactions for now, but BudgetFlow requests become the preferred tracked path
- budget edits are allowed while budget is `draft`
- once budget is `active`, structural edits should be limited

### Deliverable
A clear ruleset for how the workflow behaves.

---

## Phase 1: Database Foundation
Add the minimum schema needed for budgeting and approvals.

### New tables
- `budgets`
- `budget_line_items`
- `expense_requests`

### Table purposes
`budgets`
- organization-level budget header
- period start/end
- total amount
- status

`budget_line_items`
- per-category allocation
- description
- allocated amount
- tracked spent amount

`expense_requests`
- request submitted by owner
- linked to one budget line
- amount
- description
- receipt path
- approval status
- admin note
- linked transaction id when approved

### Existing table extension
`financial_transactions`
- add `expense_request_id`

### Deliverable
Schema update and migration-safe SQL.

---

## Phase 2: Backend Domain Helpers
Create reusable helpers before UI.

### New helper areas
```text
includes/features/budgeting/
includes/features/expense_approvals/
includes/lib/budgeting.php
includes/lib/expense_requests.php
```

### Core helper responsibilities
- fetch org budgets
- fetch active budget
- fetch budget lines
- calculate remaining allocation
- validate request against allocation
- create expense request
- approve/reject expense request
- link approved request to transaction

### Deliverable
Stable backend logic separated from page rendering.

---

## Phase 3: Owner Budget Workspace
Give owners a place to create and manage budgets.

### New owner capabilities
- create budget for organization
- define budget period
- add line items
- view allocated vs spent
- view remaining amount by line item

### Suggested UI placement
Add a new owner workspace page:

```text
?page=my_org_budget&org_id=...
```

This is better than stuffing it into current finance pages.

### Sections
- budget overview
- line items
- remaining allocation
- budget status

### Deliverable
Budget management page for organization owners.

---

## Phase 4: Expense Request Submission
Allow owners to request expenses through the budget.

### New owner flow
- choose budget line item
- enter amount
- enter description
- attach receipt if available
- submit request

### Validation
- must belong to active org budget
- must not exceed remaining amount unless policy allows over-budget warning
- must pass ownership checks

### Suggested UI placement
Inside:

```text
?page=my_org_finance&org_id=...
```

or a dedicated request section linked from finance.

### Deliverable
Owners can submit budget-linked expense requests.

---

## Phase 5: Admin Approval Workflow
Give admins a clean place to review and act.

### Admin capabilities
- view all pending expense requests
- filter by organization / status / budget period
- open request details
- approve request
- reject request with note

### Approval behavior
On approve:
- update `expense_requests.status`
- create linked `financial_transactions` expense entry
- update related budget line spent amount
- write audit log
- send notification

On reject:
- update status to `rejected`
- save admin note
- write audit log
- send notification

### Suggested UI placement
New admin page:

```text
?page=admin_expense_requests
```

### Deliverable
Admin approval dashboard and detail flow.

---

## Phase 6: Budget Tracking and Visibility
Show the financial story clearly.

### Add visibility to
- owner budget page
- finance page
- admin review pages

### Show
- allocated
- spent
- remaining
- pending requested amount
- approved amount

### Optional v1.1 improvement
Show warning states when a line is nearly exhausted.

### Deliverable
Budget usage visibility across owner/admin workflows.

---

## Phase 7: Notifications and Audit Trail
Keep the process accountable.

### Notifications
- request submitted
- request approved
- request rejected

### Audit events
- budget created
- budget updated
- expense request submitted
- expense request approved
- expense request rejected
- linked transaction created from approval

### Deliverable
Full traceability using the existing system patterns.

---

## Phase 8: Reporting
Make the feature useful operationally.

### Reports
- budget vs actual spending
- expense request history
- per-organization spending by budget line
- approved vs rejected requests

### Export targets
- PDF
- CSV later if needed

### Deliverable
Basic budget reporting and financial oversight.

---

# Suggested URL / Feature Structure

## Owner
```text
?page=my_org_budget&org_id=...
?page=my_org_finance&org_id=...
```

## Admin
```text
?page=admin_expense_requests
?page=admin_budget_overview
```

---

# Suggested Checkpoints

## Checkpoint 1
Schema + backend helpers

## Checkpoint 2
Owner budget creation and viewing

## Checkpoint 3
Owner expense request submission

## Checkpoint 4
Admin approval/rejection workflow

## Checkpoint 5
Automatic transaction linking + budget tracking

## Checkpoint 6
Notifications, audit trail, and reporting

---

# Why this is the best approach for now
This version:
- fits the current architecture
- avoids overengineering
- improves financial control immediately
- keeps owners and admins in clear roles
- builds on existing transactions, uploads, permissions, and audit systems

It also gives us room later for:
- multi-stage approvals
- stricter transaction enforcement
- semester-based budgets
- richer reports
- attachment and supporting document workflows

## Recommended update label
If you want this to appear like a named system upgrade, I’d call it:

**INVOLVE BudgetFlow v1**

If you want, I can next turn this into a **PLEASE IMPLEMENT THIS PLAN** format with exact checkpoints and test plan, ready for implementation.