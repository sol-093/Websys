# INVOLVE Next Auth Bridge Plan

## Purpose
Adapt the fresh Laravel + Inertia auth foundation so it works with the **existing `users` table** from the current INVOLVE system.

This is the first real backend bridge between:
- current app data
- new Laravel auth

Without this step, the new app can boot, but it cannot safely replace the current login/register/verification behavior.

---

## Why This Phase Matters

Laravel Breeze assumes a standard Laravel auth schema:
- `password`
- `remember_token`
- standard Laravel password reset flow
- standard email verification contracts

The current INVOLVE schema is different.

### Current `users` table fields
From `database/schema.sql`:

- `id`
- `name`
- `email`
- `password_hash`
- `role`
- `onboarding_done`
- `institute`
- `program`
- `year_level`
- `section`
- `profile_picture_path`
- `profile_picture_crop_x`
- `profile_picture_crop_y`
- `profile_picture_zoom`
- `email_verified`
- `email_verified_at`
- `activation_token`
- `activation_expires`
- `reset_token`
- `reset_expires`
- `account_status`
- `last_login_at`
- `last_login_ip`
- `password_changed_at`
- `password_reset_at`
- `created_at`

### Immediate compatibility issues
- Laravel expects `password`, current app uses `password_hash`
- Laravel may expect `remember_token`, current schema does not include it
- current app uses custom activation tokens for verification
- current app uses custom reset tokens instead of Laravel’s default password reset table flow
- current app includes role and account-status restrictions that must be preserved

---

## Goal
Make Laravel auth work **against the existing user table** without breaking:
- current passwords
- current email verification behavior
- current account status rules
- current role model

---

## Phase Scope

This phase covers:
- login compatibility
- register compatibility
- verification compatibility
- password reset compatibility
- user model mapping
- account status checks

This phase does **not** yet cover:
- profile migration UI
- Google OAuth migration
- owner/admin page migration

---

## Target Outcomes

After this phase:
- Laravel can authenticate existing users
- Laravel can register new users into the same schema
- email verification still behaves correctly
- password reset still works with the existing token approach or a consciously migrated replacement
- user roles and account statuses are enforced

---

## Work Checklist

## 1. User Model Mapping

### Current problem
Fresh Laravel `User` model is still close to default behavior.

### Tasks
- [ ] update `App\\Models\\User`
- [ ] map fillable fields to current schema
- [ ] expose `password_hash` appropriately
- [ ] decide whether to:
  - [ ] keep `password_hash` as-is and customize auth provider behavior
  - [ ] or add a compatibility accessor/mutator path
- [ ] add casts for:
  - [ ] `email_verified_at`
  - [ ] `activation_expires`
  - [ ] `reset_expires`
  - [ ] `last_login_at`
  - [ ] `password_changed_at`
  - [ ] `password_reset_at`
- [ ] add helpers for:
  - [ ] role checks
  - [ ] status checks
  - [ ] verification checks

### Deliverable
- Laravel `User` model represents the real schema

---

## 2. Login Flow Compatibility

### Current app behavior to preserve
From `includes/features/auth/actions.php`:
- authenticate against `password_hash`
- block invalid credentials
- block suspended/banned users
- require verification before access
- update login-related session state and audit behavior

### Tasks
- [ ] replace default Breeze login assumptions
- [ ] authenticate using `password_hash`
- [ ] preserve email verification gate
- [ ] preserve `account_status` gate
- [ ] define post-login redirect behavior
- [ ] decide how much audit logging to move now vs later

### Deliverable
- existing users can log in through Laravel auth

---

## 3. Register Flow Compatibility

### Current app behavior to preserve
- writes to current `users` schema
- stores `password_hash`
- stores program/institute/year/section data
- stores verification token and expiry
- enforces privacy consent and validation rules

### Tasks
- [ ] inspect Breeze register controller/request
- [ ] expand register form to include required INVOLVE fields
- [ ] store `password_hash` instead of default `password`
- [ ] store role as `student`
- [ ] store institute/program/year/section
- [ ] set verification state correctly
- [ ] generate activation token and expiry
- [ ] preserve validation rules from current app

### Deliverable
- new users can register into the existing schema correctly

---

## 4. Email Verification Compatibility

### Current problem
Laravel’s built-in email verification flow is hash-based and route-signed; current app uses:
- `activation_token`
- `activation_expires`
- `email_verified`
- `email_verified_at`

### Decision
For the migration bridge, the best short-term choice is:
- keep the **current verification model first**
- do not switch to Laravel-native verification yet

### Tasks
- [ ] create a Laravel verification controller/service that reads current token fields
- [ ] support verification links using existing token semantics
- [ ] update verified state in current schema
- [ ] handle expiry and invalid token responses
- [ ] support resend verification

### Deliverable
- current verification logic works in the new stack

---

## 5. Password Reset Compatibility

### Current problem
Laravel Breeze expects the standard password reset broker/table flow.
Current app uses:
- `reset_token`
- `reset_expires`
- `password_reset_at`

### Decision
For the migration bridge, the safest move is:
- keep the **current reset-token approach first**

### Tasks
- [ ] replace default Breeze reset handling with current token logic
- [ ] generate reset token into `users`
- [ ] store reset expiry in `reset_expires`
- [ ] verify token against current schema
- [ ] update password via `password_hash`
- [ ] update `password_reset_at`
- [ ] clear reset token after success

### Deliverable
- password reset works without forcing a schema redesign yet

---

## 6. Account Status and Role Enforcement

### Current app rules to preserve
- admin accounts have different behavior from student/owner accounts
- suspended/banned users should not authenticate normally
- owner/admin/student role checks drive navigation and access

### Tasks
- [ ] add role helpers or enums
- [ ] add middleware/policy strategy
- [ ] add account-status guard behavior
- [ ] preserve admin restrictions where needed

### Deliverable
- Laravel auth respects current role and status rules

---

## 7. Route and Page Alignment

### Tasks
- [ ] map current auth route names to new Laravel routes where sensible
- [ ] keep user-facing route naming clear
- [ ] decide redirect destinations after login, register, verify, reset

### Suggested Laravel auth targets
- `/login`
- `/register`
- `/forgot-password`
- `/reset-password/{token}`
- `/verify-email`

### Deliverable
- auth routes are predictable and migration-safe

---

## 8. Testing Checklist

### Manual verification
- [ ] existing verified user can log in
- [ ] unverified user is blocked from normal access
- [ ] suspended user is blocked
- [ ] new user can register
- [ ] verification email/token flow works
- [ ] resend verification works
- [ ] forgot password creates token
- [ ] reset password updates the existing schema correctly

### Technical verification
- [ ] Laravel can read existing users table correctly
- [ ] no duplicate password column assumptions remain
- [ ] no Laravel default password broker flow is accidentally left active if we are not using it

---

## Immediate Implementation Order

Do these first:

1. [ ] adapt `App\\Models\\User`
2. [ ] adapt login to use `password_hash`
3. [ ] adapt register to write current schema fields
4. [ ] adapt verification token flow
5. [ ] adapt password reset token flow

---

## Recommended Next Files to Touch in `involve-next`

Likely starting points:
- `app/Models/User.php`
- `app/Http/Controllers/Auth/*`
- `routes/auth.php`
- auth request validation classes
- mail/notification classes if verification/reset sending is wired now

---

## Important Rule
Do **not** force the current system into Laravel’s default auth schema too early.

For this migration phase, we are making Laravel understand the current INVOLVE schema first.
Schema normalization can happen later when the app is already stable in the new stack.

