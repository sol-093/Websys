# Project Guidelines

## Architecture
- This is a single-entry PHP application. `index.php` loads bootstrap and route dispatchers.
- Keep startup in `includes/bootstrap.php`, dispatch in `includes/routes/`, feature code in `includes/features/`, shared UI helpers in `includes/shared/`, runtime concerns in `includes/core/`, and reusable domain helpers in `includes/lib/`.
- Prefer extending the existing layered structure instead of introducing new top-level patterns.
- Link to the longer docs instead of duplicating them: [README.md](../README.md), [docs/architecture/PROJECT_DOCUMENTATION.md](../docs/architecture/PROJECT_DOCUMENTATION.md), and [docs/reference/FUNCTION_ANALYSIS.md](../docs/reference/FUNCTION_ANALYSIS.md).

## Code Style
- PHP files use `declare(strict_types=1);` and a pragmatic mixed PHP/HTML style in page renderers.
- Use PDO prepared statements for database access.
- Escape user-facing output with `e()`.
- Guard authenticated routes with `requireLogin()` and `requireRole()`.
- Validate POST requests with CSRF helpers before mutating state.
- Keep edits small and consistent with nearby code; avoid unnecessary refactors or formatting churn.

## Build and Test
- Run the app locally with `php -S localhost:8000`, then open `http://localhost:8000/index.php`.
- Seed demo content with `php scripts/seed/seed_dummy_data.php` and `php scripts/seed/seed_dummy_reports.php`.
- Run the repo regression script with `php scripts/tests/test_organization_helpers.php`.
- There is no Composer setup or PHPUnit suite in this repository.

## Conventions
- `includes/core/db.php` bootstraps and auto-initializes schema compatibility on startup, so changes to schema-related behavior should stay aligned with that flow.
- Uploaded receipts live under `uploads/` and should remain writable in local environments.
- If a change affects behavior, update the relevant docs in the same change set.
