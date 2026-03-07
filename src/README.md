# Source Structure

The `src/` folder is organized by responsibility:

- `core/`: foundational boot/runtime files
  - `config.php`, `db.php`, `helpers.php`, `auth.php`, `layout.php`
- `lib/`: reusable domain and utility helpers
  - `organization.php`, `pagination.php`, `notifications.php`, `integrations.php`, `maintenance.php`
- `actions/`: POST/action handlers
  - `auth_flows.php`, `workflows.php`, `content_actions.php`
- `pages/`: page render handlers and large page partials
  - `public_pages.php`, `admin_pages.php`, `community_pages.php`, `owner_pages.php`, `dashboard_page.php`, `dashboard_page_markup.php`
- `services/`: data assembly and service-layer logic
  - `dashboard_data.php`

Entry point:
- `index.php` requires files from these folders and dispatches routes/actions.
