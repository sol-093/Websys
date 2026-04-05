# Project Guidelines

## Architecture
- This is a single-entry PHP application. `index.php` routes requests, renders pages, and dispatches POST actions.
- Keep runtime concerns in `src/core/`, reusable domain helpers in `src/lib/`, action handlers in `src/actions/`, page renderers in `src/pages/`, and data aggregation in `src/services/`.
- Prefer extending the existing layered structure instead of introducing new top-level patterns.
- Link to the longer docs instead of duplicating them: [README.md](../README.md), [docs/architecture/PROJECT_DOCUMENTATION.md](../docs/architecture/PROJECT_DOCUMENTATION.md), [docs/reference/FUNCTION_ANALYSIS.md](../docs/reference/FUNCTION_ANALYSIS.md), and [static/README.md](../static/README.md).

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
- `src/db.php` bootstraps and auto-initializes schema compatibility on startup, so changes to schema-related behavior should stay aligned with that flow.
- Uploaded receipts live under `public/uploads/` and should remain writable in local and deployment environments.
- If a change affects behavior, update the relevant docs in the same change set.