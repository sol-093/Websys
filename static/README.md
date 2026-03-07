# Static Demo Folder Guide

This folder contains the frontend-only version of the system.

## Files

- `system-static-demo.html`
- `system-static-demo.css`
- `system-static-demo.js`

## 1) `system-static-demo.html`

Purpose:
- Defines the page structure (navbar, sections, forms, dashboard panels, modal).
- Loads Tailwind from CDN.
- Loads local CSS and JS files.

Key syntax used:
- **External assets**
  - `<link rel="stylesheet" href="system-static-demo.css">`
  - `<script src="system-static-demo.js"></script>`
- **Section switching**
  - Each page block uses `class="page"`.
  - Active view uses `class="page active"`.
- **IDs for JS hooks**
  - Example: `id="navMenuToggle"`, `id="mobileNavMenu"`, `id="themeToggle"`, `id="privacyModal"`.
- **Role-based dynamic placeholders**
  - Example containers populated by JS: `id="desktopNav"`, `id="mobileNav"`, `id="dashboardQuickActions"`.

## 2) `system-static-demo.css`

Purpose:
- Holds custom styling on top of Tailwind utilities.
- Implements glassmorphism, dark mode overrides, navbar interactions, and control styling.

Key syntax used:
- **CSS variables**
  - `:root { --green-500: ... }`
- **Dark mode selector**
  - `body.theme-dark .className { ... }`
- **Pseudo-elements**
  - Toggle knob: `.theme-switch::after`
- **State-specific classes**
  - Hidden page: `.page { display: none; }`
  - Visible page: `.page.active { display: block; }`

## 3) `system-static-demo.js`

Purpose:
- Adds all interactivity (navigation, role switching, mobile menu, dark/light mode, modal behavior, chart behavior).

Key syntax used:
- **State variables**
  - `let role = 'guest'`
  - `let currentPage = 'home'`
- **DOM selection**
  - `document.getElementById(...)`
  - `document.querySelectorAll(...)`
- **Event listeners**
  - `element.addEventListener('click', handler)`
  - `form.addEventListener('submit', handler)`
- **Class toggling**
  - `element.classList.toggle('hidden', condition)`
  - `document.body.classList.toggle('theme-dark', isDark)`
- **Template strings for dynamic HTML**
  - `` `...${value}...` ``
- **Local storage for theme persistence**
  - `localStorage.setItem('websys-theme', 'dark')`
  - `localStorage.getItem('websys-theme')`
- **Chart.js initialization**
  - `new Chart(canvas, { ... })`
  - Updates chart colors on theme toggle.

## How to run

From XAMPP:
- Open: `http://localhost/websys/static/demo/system-static-demo.html`

From file explorer (direct file open):
- Open `static/demo/system-static-demo.html` in a browser.

## Notes

- This static demo uses dummy data only.
- No PHP, no database, no backend APIs are used.
