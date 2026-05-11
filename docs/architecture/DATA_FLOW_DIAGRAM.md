# INVOLVE Data Flow Diagram

Last reviewed: May 11, 2026

## Purpose
This document describes the data flow of the INVOLVE Student Organization Management and Budget Transparency System from context level through selected Level 3 flows. It is intended for system defense, maintenance, and future architecture updates.

## DFD Scope
- Existing web routes under `index.php`, `?page=...`, and `?action=...`.
- Existing JSON endpoints under `/api`.
- Session-based authentication, CSRF, uploads, notifications, audit logs, and exports.
- Current PHP/PDO database stores and upload folders.

## Mermaid Source Files
The diagrams below are embedded in this Markdown file and also available as standalone Mermaid files for editor preview extensions:
- [Level 0 Context](mermaid/dfd-level-0-context.mmd)
- [Level 1 Major Processes](mermaid/dfd-level-1-major-processes.mmd)
- [Level 2 Finance Approval](mermaid/dfd-level-2-finance-approval.mmd)
- [Level 3 Clean System Overview](mermaid/dfd-level-3-clean-system.mmd)
- [Level 3 Void Transaction](mermaid/dfd-level-3-void-transaction.mmd)

## External Entities
- **Student:** browses organizations, joins organizations, reviews updates, and manages profile data.
- **Organization Owner:** manages assigned organization profile, announcements, members, budgets, expense requests, and transactions.
- **System Admin:** manages organizations, owner assignments, approvals, budgets, audit logs, and reports.
- **Email/SMTP Provider:** sends verification, reset, and notification emails.
- **Google OAuth Provider:** provides optional Google sign-in identity data.
- **Browser/File System:** provides uploaded files and receives generated PDF/CSV exports.

## Data Stores
- **D1 Users:** account, role, profile, verification, and onboarding data.
- **D2 Sessions and Tokens:** session records, reset tokens, remember/login metadata.
- **D3 Organizations:** organization profile, ownership, visibility, and branding data.
- **D4 Memberships and Requests:** organization members and join requests.
- **D5 Owner Assignments:** owner assignment workflow records.
- **D6 Announcements:** organization announcements, pins, labels, and expiry.
- **D7 Budgets and Lines:** budgets, line items, allocations, spent and pending amounts.
- **D8 Expense Requests:** budget-backed expense requests, receipts, admin decisions, linked transactions.
- **D9 Financial Transactions:** income/expense records, receipts, void status, void reason, linked expense request.
- **D10 Transaction Change Requests:** owner update/delete requests and admin decisions.
- **D11 Audit Logs:** captured critical actions and entity references.
- **D12 Security Notifications:** request/security updates shown to users.
- **D13 Upload Storage:** user photos, organization logos, receipts, bundled media.
- **D14 Runtime Cache:** cached safe aggregate/list data.

## Level 0: Context Diagram
```mermaid
flowchart LR
    Student[Student]
    Owner[Organization Owner]
    Admin[System Admin]
    OAuth[Google OAuth Provider]
    Mail[Email/SMTP Provider]
    Files[Browser / File System]

    System((INVOLVE System))

    Student -->|registration, login, joins, profile updates, views| System
    Owner -->|organization updates, announcements, budgets, transactions, requests| System
    Admin -->|organization admin, approvals, audit review, exports| System
    OAuth -->|identity callback| System
    System -->|OAuth request| OAuth
    System -->|verification/reset emails| Mail
    Files -->|uploads| System
    System -->|PDF/CSV downloads, rendered pages, JSON| Files
```

## Level 1: Major System Processes
```mermaid
flowchart TB
    Student[Student]
    Owner[Organization Owner]
    Admin[System Admin]
    OAuth[Google OAuth]
    Mail[SMTP Provider]
    Files[Browser / File System]

    P1((1.0 Authentication and Account))
    P2((2.0 Organization Management))
    P3((3.0 Membership and Ownership))
    P4((4.0 Announcements))
    P5((5.0 Budget and Expense Requests))
    P6((6.0 Financial Transactions))
    P7((7.0 Admin Review and Audit))
    P8((8.0 Notifications and Activity Feeds))
    P9((9.0 Reports, Exports, and API Lists))
    P10((10.0 Upload Handling))

    D1[(D1 Users)]
    D2[(D2 Sessions and Tokens)]
    D3[(D3 Organizations)]
    D4[(D4 Memberships and Requests)]
    D5[(D5 Owner Assignments)]
    D6[(D6 Announcements)]
    D7[(D7 Budgets and Lines)]
    D8[(D8 Expense Requests)]
    D9[(D9 Financial Transactions)]
    D10[(D10 Transaction Change Requests)]
    D11[(D11 Audit Logs)]
    D12[(D12 Security Notifications)]
    D13[(D13 Upload Storage)]
    D14[(D14 Runtime Cache)]

    Student --> P1
    Owner --> P1
    Admin --> P1
    OAuth --> P1
    P1 --> D1
    P1 --> D2
    P1 --> Mail

    Student --> P2
    Owner --> P2
    Admin --> P2
    P2 --> D3
    P2 --> D11

    Student --> P3
    Owner --> P3
    Admin --> P3
    P3 --> D4
    P3 --> D5
    P3 --> D11
    P3 --> D12

    Owner --> P4
    Admin --> P4
    P4 --> D6
    P4 --> D11
    P4 --> D14

    Owner --> P5
    Admin --> P5
    P5 --> D7
    P5 --> D8
    P5 --> D9
    P5 --> D11
    P5 --> D12

    Owner --> P6
    Admin --> P6
    P6 --> D9
    P6 --> D10
    P6 --> D11
    P6 --> D12

    Admin --> P7
    P7 --> D8
    P7 --> D10
    P7 --> D11

    Student --> P8
    Owner --> P8
    Admin --> P8
    P8 --> D6
    P8 --> D10
    P8 --> D11
    P8 --> D12

    Student --> P9
    Owner --> P9
    Admin --> P9
    P9 --> D3
    P9 --> D6
    P9 --> D7
    P9 --> D8
    P9 --> D9
    P9 --> D11
    P9 --> D14
    P9 --> Files

    Student --> P10
    Owner --> P10
    Admin --> P10
    P10 --> D13
    P10 --> D11
```

## Level 2: Core Workflows

### 2.1 Authentication and Account
```mermaid
flowchart TB
    User[Student / Owner / Admin]
    OAuth[Google OAuth]
    Mail[SMTP Provider]

    P11((1.1 Register Account))
    P12((1.2 Verify Email))
    P13((1.3 Login / Google Login))
    P14((1.4 Reset Password))
    P15((1.5 Update Profile))
    P16((1.6 Complete Onboarding))

    D1[(D1 Users)]
    D2[(D2 Sessions and Tokens)]
    D11[(D11 Audit Logs)]
    D12[(D12 Security Notifications)]
    D13[(D13 Upload Storage)]

    User -->|registration form| P11
    P11 --> D1
    P11 --> Mail
    P11 --> D11

    User -->|verification token| P12
    P12 --> D1
    P12 --> D11

    User -->|credentials| P13
    OAuth -->|identity callback| P13
    P13 --> D1
    P13 --> D2
    P13 --> D12

    User -->|reset request/new password| P14
    P14 --> D1
    P14 --> D2
    P14 --> Mail
    P14 --> D11

    User -->|profile form/photo| P15
    P15 --> D1
    P15 --> D13
    P15 --> D11

    User -->|tour complete| P16
    P16 --> D1
```

### 2.2 Organization, Membership, and Ownership
```mermaid
flowchart TB
    Student[Student]
    Owner[Organization Owner]
    Admin[System Admin]

    P21((2.1 Create / Update Organization))
    P22((2.2 Browse Organization Directory))
    P23((2.3 Submit Join Request))
    P24((2.4 Approve / Decline Join Request))
    P25((2.5 Assign Owner))
    P26((2.6 Accept / Reject Owner Assignment))

    D1[(D1 Users)]
    D3[(D3 Organizations)]
    D4[(D4 Memberships and Requests)]
    D5[(D5 Owner Assignments)]
    D11[(D11 Audit Logs)]
    D12[(D12 Security Notifications)]
    D13[(D13 Upload Storage)]
    D14[(D14 Runtime Cache)]

    Admin --> P21
    Owner --> P21
    P21 --> D3
    P21 --> D13
    P21 --> D11
    P21 --> D14

    Student --> P22
    Owner --> P22
    Admin --> P22
    P22 --> D3
    P22 --> D4
    P22 --> D14

    Student --> P23
    P23 --> D4
    P23 --> D12

    Owner --> P24
    P24 --> D4
    P24 --> D11
    P24 --> D12

    Admin --> P25
    P25 --> D1
    P25 --> D3
    P25 --> D5
    P25 --> D11
    P25 --> D12

    Student --> P26
    P26 --> D1
    P26 --> D3
    P26 --> D5
    P26 --> D11
    P26 --> D12
```

### 2.3 Finance, Budgeting, and Admin Approval
```mermaid
flowchart TB
    Owner[Organization Owner]
    Admin[System Admin]
    Files[Browser / File System]

    P31((3.1 Create / Activate Budget))
    P32((3.2 Add Budget Lines))
    P33((3.3 Submit Expense Request))
    P34((3.4 Review Expense Request))
    P35((3.5 Create Linked Expense Transaction))
    P36((3.6 Record Manual Transaction))
    P37((3.7 Request Transaction Update / Delete))
    P38((3.8 Approve / Reject Transaction Change))
    P39((3.9 Void Transaction))

    D7[(D7 Budgets and Lines)]
    D8[(D8 Expense Requests)]
    D9[(D9 Financial Transactions)]
    D10[(D10 Transaction Change Requests)]
    D11[(D11 Audit Logs)]
    D12[(D12 Security Notifications)]
    D13[(D13 Upload Storage)]
    D14[(D14 Runtime Cache)]

    Owner --> P31
    P31 --> D7
    P31 --> D11
    P31 --> D14

    Owner --> P32
    P32 --> D7
    P32 --> D11
    P32 --> D14

    Owner -->|amount, line, receipt| P33
    Files -->|receipt upload| P33
    P33 --> D8
    P33 --> D13
    P33 --> D11
    P33 --> D12

    Admin -->|approve/reject| P34
    P34 --> D8
    P34 --> D11
    P34 --> D12

    P34 -->|approved request| P35
    P35 --> D7
    P35 --> D9
    P35 --> D8
    P35 --> D14

    Owner -->|manual income/expense + receipt| P36
    Files -->|receipt upload| P36
    P36 --> D9
    P36 --> D13
    P36 --> D11
    P36 --> D14

    Owner -->|update/delete request| P37
    P37 --> D10
    P37 --> D11
    P37 --> D12

    Admin -->|approval decision| P38
    P38 --> D10
    P38 --> D11
    P38 --> D12

    P38 -->|approved delete| P39
    P39 --> D9
    P39 --> D14
```

### 2.4 Announcements, Notifications, Audit, and Reports
```mermaid
flowchart TB
    Student[Student]
    Owner[Organization Owner]
    Admin[System Admin]
    Files[Browser / File System]

    P41((4.1 Create / Delete / Pin Announcement))
    P42((4.2 Build Activity Feed))
    P43((4.3 Queue Security / Workflow Notification))
    P44((4.4 Review Audit Logs))
    P45((4.5 Generate PDF / CSV Export))
    P46((4.6 Serve API List Endpoint))

    D3[(D3 Organizations)]
    D6[(D6 Announcements)]
    D7[(D7 Budgets and Lines)]
    D8[(D8 Expense Requests)]
    D9[(D9 Financial Transactions)]
    D10[(D10 Transaction Change Requests)]
    D11[(D11 Audit Logs)]
    D12[(D12 Security Notifications)]
    D14[(D14 Runtime Cache)]

    Owner --> P41
    Admin --> P41
    P41 --> D6
    P41 --> D11
    P41 --> D14

    Student --> P42
    Owner --> P42
    Admin --> P42
    P42 --> D6
    P42 --> D9
    P42 --> D11
    P42 --> D12

    P43 --> D12
    P43 --> D11

    Admin --> P44
    P44 --> D11

    Owner --> P45
    Admin --> P45
    P45 --> D7
    P45 --> D8
    P45 --> D9
    P45 --> Files

    Student --> P46
    Owner --> P46
    Admin --> P46
    P46 --> D3
    P46 --> D6
    P46 --> D8
    P46 --> D9
    P46 --> D10
    P46 --> D11
    P46 --> D14
```

## Level 3: Critical Detailed Flows

### 3.1 Expense Request Approval to Linked Transaction
```mermaid
flowchart TB
    Owner[Organization Owner]
    Admin[System Admin]
    File[Receipt File]

    P311((3.1.1 Validate Budget Line and Remaining Amount))
    P312((3.1.2 Validate and Store Receipt))
    P313((3.1.3 Create Expense Request))
    P314((3.1.4 Notify Admin / Record Audit))
    P315((3.1.5 Lock Request for Decision))
    P316((3.1.6 Approve or Reject Request))
    P317((3.1.7 Create Expense Transaction))
    P318((3.1.8 Increment Line Spent Amount))
    P319((3.1.9 Notify Owner / Invalidate Cache))

    D7[(Budgets and Lines)]
    D8[(Expense Requests)]
    D9[(Financial Transactions)]
    D11[(Audit Logs)]
    D12[(Security Notifications)]
    D13[(Upload Storage)]
    D14[(Runtime Cache)]

    Owner -->|budget line, amount, description| P311
    P311 --> D7
    File --> P312
    P312 --> D13
    P312 --> P313
    P313 --> D8
    P313 --> P314
    P314 --> D11
    P314 --> D12

    Admin -->|decision and note| P315
    P315 --> D8
    P315 --> P316
    P316 --> D8
    P316 --> D11

    P316 -->|approved| P317
    P317 --> D9
    P317 --> D8
    P317 --> P318
    P318 --> D7
    P316 -->|approved/rejected| P319
    P319 --> D12
    P319 --> D14
```

### 3.2 Transaction Delete Request to Voided Record
```mermaid
flowchart TB
    Owner[Organization Owner]
    Admin[System Admin]

    P321((3.2.1 Select Active Transaction))
    P322((3.2.2 Validate Ownership and Active Status))
    P323((3.2.3 Create Delete Change Request))
    P324((3.2.4 Notify Admin / Record Audit))
    P325((3.2.5 Admin Reviews Request))
    P326((3.2.6 Approve or Reject Request))
    P327((3.2.7 Void Transaction Instead of Deleting))
    P328((3.2.8 Notify Owner / Exclude from Active Totals))

    D9[(Financial Transactions)]
    D10[(Transaction Change Requests)]
    D11[(Audit Logs)]
    D12[(Security Notifications)]
    D14[(Runtime Cache)]

    Owner --> P321
    P321 --> P322
    P322 --> D9
    P322 -->|active transaction| P323
    P323 --> D10
    P323 --> P324
    P324 --> D11
    P324 --> D12

    Admin --> P325
    P325 --> D10
    P325 --> P326
    P326 --> D10
    P326 --> D11
    P326 -->|approved delete| P327
    P327 -->|set is_voided, voided_at, void_reason| D9
    P327 --> P328
    P328 --> D12
    P328 --> D14
```

### 3.3 Upload Validation and Storage
```mermaid
flowchart TB
    User[Student / Owner / Admin]
    File[Uploaded File]

    P331((3.3.1 Receive File))
    P332((3.3.2 Check Upload Error and Size))
    P333((3.3.3 Check Original Extension Allowlist))
    P334((3.3.4 Inspect MIME / Image Dimensions))
    P335((3.3.5 Generate Random Filename))
    P336((3.3.6 Move to Upload Storage))
    P337((3.3.7 Save Relative Path on Related Record))
    P338((3.3.8 Delete Orphan File on DB Failure))

    D1[(Users)]
    D3[(Organizations)]
    D8[(Expense Requests)]
    D9[(Financial Transactions)]
    D13[(Upload Storage)]
    D11[(Audit Logs)]

    User --> P331
    File --> P331
    P331 --> P332
    P332 --> P333
    P333 --> P334
    P334 --> P335
    P335 --> P336
    P336 --> D13
    P336 --> P337
    P337 --> D1
    P337 --> D3
    P337 --> D8
    P337 --> D9
    P337 --> D11
    P337 -->|failure| P338
    P338 --> D13
```

### 3.4 Login, Session, and Security Notification
```mermaid
flowchart TB
    User[User]
    OAuth[Google OAuth Provider]
    Mail[SMTP Provider]

    P341((3.4.1 Collect Credentials or OAuth Callback))
    P342((3.4.2 Validate User and Password / OAuth Identity))
    P343((3.4.3 Check Verification and Rate Limits))
    P344((3.4.4 Start Session))
    P345((3.4.5 Queue Login Security Update))
    P346((3.4.6 Render Dashboard / API Me))
    P347((3.4.7 Password Reset Token Flow))

    D1[(Users)]
    D2[(Sessions and Tokens)]
    D11[(Audit Logs)]
    D12[(Security Notifications)]

    User --> P341
    OAuth --> P341
    P341 --> P342
    P342 --> D1
    P342 --> P343
    P343 --> D1
    P343 --> D2
    P343 --> P344
    P344 --> D2
    P344 --> P345
    P345 --> D12
    P345 --> D11
    P344 --> P346

    User -->|forgot password| P347
    P347 --> D1
    P347 --> D2
    P347 --> Mail
    P347 --> D11
```

## Data Dictionary
| ID | Store | Main Data | Used By |
| --- | --- | --- | --- |
| D1 | Users | identity, role, email verification, profile, onboarding | auth, permissions, owner assignment, notifications |
| D2 | Sessions and Tokens | active sessions, reset tokens, login metadata | login, logout, reset password |
| D3 | Organizations | name, description, scope, logo, owner | directory, admin orgs, owner workspace |
| D4 | Memberships and Requests | members, join request status | join workflow, owner member review |
| D5 | Owner Assignments | pending/accepted/rejected owner assignments | admin assignment and student response |
| D6 | Announcements | title, content, label, pin, expiry | dashboard activity, organization pages |
| D7 | Budgets and Lines | budget periods, totals, line allocations/spend | owner budget workspace, admin budget overview |
| D8 | Expense Requests | request amount, status, receipt, admin note, linked transaction | BudgetFlow approval |
| D9 | Financial Transactions | income/expense, amount, receipt, void status | reports, dashboards, exports, transaction history |
| D10 | Transaction Change Requests | update/delete proposals, status, admin note | admin approvals, owner request trail |
| D11 | Audit Logs | action, actor, entity, source details | admin audit, transparency trail |
| D12 | Security Notifications | login/security/request updates | notification center, popups |
| D13 | Upload Storage | profile images, org logos, receipts, assets | upload workflows, receipt viewing, branding |
| D14 | Runtime Cache | safe aggregate/list cache entries | dashboard, public counts, API list reads |

## Control and Security Notes
- Mutating web actions use CSRF validation.
- Role and permission checks are enforced through `can()` and `requirePermission()`.
- Uploads validate size, original extension, MIME type, and image dimensions where applicable.
- Profile and organization images are decoded and re-saved before storage; receipt files keep their validated original payload for audit visibility.
- Financial transaction delete approvals void records instead of removing rows.
- Voided transactions remain visible but are excluded from active totals and blocked from further update/delete requests.
- Audit logs and notifications are written around critical workflows.
- Cache stores only safe aggregate/list data, never sessions, CSRF tokens, passwords, reset tokens, or current-user state.

## Current Gaps and Future DFD Updates
- A future public transparency portal should add a dedicated Level 2 reporting/transparency DFD.
- If API token authentication is added, Level 2 authentication should split session auth from token auth.
- If Redis/APCu/WebSockets are introduced, D14 and notification flows should be updated.
- If upload storage moves outside the web root or into object storage, D13 should be revised.
