# Static Frontend Demo Guide

This folder contains the synced static version of the current frontend UX, including dashboard visuals, modal flows, theme switching, and chart interactions.

Version: 1.1.1  
Last Updated: April 6, 2026

## Static Version History

- 1.1.1 (2026-04-06)
	- Commit: pending (current working tree)
	- Fixed static onboarding reliability (resume state, missing-target progression, tooltip placement timing).
	- Added teacher-facing presentation brief for current frontend concept and visual language.
- 1.1.0 (2026-04-06)
	- Commit: b783bce
	- Synced current runtime UX updates including refreshed visual shell, icon updates, dashboard/chart behavior enhancements, and static docs alignment.
- 1.0.3 (2026-04-04)
	- Commit: 8a74fda
	- Synced organization modal polish and light-mode visual adjustments.
- 1.0.2 (2026-04-03)
	- Commits: dfe17e5, cbf54ad
	- Synced dashboard/dropdown refinements and broader realism/content updates.
- 1.0.1 (2026-03-28)
	- Commits: 9e6ec8c, d906ec8, a9f604d, 1a6f8e1, 5f79c64, cb63c39, 9f59665, 55a74e9
	- Synced registration UX refinements, dark theme polish, and footer layout adjustments.

## Files

- `demo/system-static-demo.html`
- `demo/system-static-demo.css`
- `demo/system-static-demo.js`
- `FRONTEND_IDEAS.md`

## Synced Coverage

- Public pages: home, login, register.
- Home-page icon set synced with live UI updates (dashboard, register/get-started, owners, announcements).
- Role-based dashboard navigation and quick actions.
- Global command palette simulation (`Ctrl+K` / `Cmd+K`) with users, organizations, and announcements results.
- First-login student onboarding tour simulation, including footer replay control.
- Dashboard sections: overview, finance status, monthly trend, live activity, organizations, recent reports, and financial summary.
- Modal interactions: terms and conditions, organizations list, announcements list, and financial health snapshot.
- Light/dark theme persistence via localStorage.
- Shared toast notifications and password visibility toggles in auth forms.
- Layered background treatment synced with the runtime app, including role-aware guest/authenticated variants and texture overlay.
- Chart.js trend and financial ranking chart theme adaptation.
- Footer blocks aligned with the live frontend information architecture.

## Animation and UI Behavior

- Glassmorphism card styling and themed color tokens.
- Page panel entrance animation for active views.
- Modal entrance animation for dashboard overlays.
- Mobile navigation drawer behavior with responsive close handling.
- Role toggle controls (`guest`, `student`, `owner`, `admin`) for frontend state simulation.

## How to Run

Using XAMPP:

- Open `http://localhost/websys/static/demo/system-static-demo.html`

Direct file open:

- Open `static/demo/system-static-demo.html` in a browser.

## Validation Checklist

- Toggle roles from the demo role chips and verify nav + actions update.
- Toggle dark mode and confirm charts restyle correctly.
- Open and close all modals (terms, organizations, announcements, financial summary).
- Check responsive behavior on mobile widths (menu, cards, tables, modal scroll).

## Notes

- This is a frontend-only static simulation with seeded visual data.
- No PHP runtime, database connection, or backend workflow is executed in this mode.
- Presentation-oriented pitch content for the static frontend is available in `FRONTEND_IDEAS.md`.
