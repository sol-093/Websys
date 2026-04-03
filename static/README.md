# Static Frontend Demo Guide

This folder contains the synced static version of the current frontend UX, including dashboard visuals, modal flows, theme switching, and chart interactions.

## Files

- `demo/system-static-demo.html`
- `demo/system-static-demo.css`
- `demo/system-static-demo.js`

## Synced Coverage

- Public pages: home, login, register.
- Role-based dashboard navigation and quick actions.
- Dashboard sections: overview, finance status, monthly trend, live activity, organizations, recent reports, and financial summary.
- Modal interactions: terms and conditions, organizations list, announcements list, and financial health snapshot.
- Light/dark theme persistence via localStorage.
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
