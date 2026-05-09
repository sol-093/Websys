# Production Deployment Checklist

Use this checklist before packaging or publishing INVOLVE.

## Exclude From Deployment
- `.git/`, `.env`, `.venv/`, `.vscode/`, `.phpunit.cache/`
- `tests/`, `scripts/tests/`, demo seed scripts under `scripts/seed/`
- local database files, temporary cache files, logs, and unneeded IDE metadata

The repository includes `.gitattributes` export rules so `git archive` excludes common development-only paths.

## Required Production Setup
- Create a production `.env` from `.env.example`; never copy local credentials.
- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Configure MySQL credentials, SMTP credentials, `APP_URL`/`BASE_URL`, and Google OAuth only when used.
- Ensure `uploads/`, `uploads/users/`, `uploads/organizations/`, `uploads/receipts/`, and `storage/cache/` are writable by PHP but not executable.
- Run `composer install --no-dev --prefer-dist --no-progress --optimize-autoloader`.

## Preflight Checks
- Run `composer validate --strict`.
- Run PHP lint on tracked PHP files.
- Run PHPUnit in CI before deploying.
- Run `composer audit`.
- Confirm security headers are present in a browser/network inspector.
- Confirm login, dashboard, organization, budgeting, transaction, announcement, and export flows still work.

## Post-Deploy Checks
- Confirm `.env` is not web-accessible.
- Confirm uploaded receipts/images are not executable.
- Confirm cache files are created under `storage/cache/`.
- Review PHP and web server logs after the first real user session.
