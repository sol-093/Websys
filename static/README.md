# Static Frontend Demo Guide

## Summary
- **Scope:** Frontend-only static demonstration assets
- **Updated:** April 27, 2026
- **Primary entry file:** `static/demo/system-static-demo.html`

## Purpose
This document explains how to use the static demo bundle that mirrors current UI/UX behavior without backend execution.

## Audience
- Instructors/reviewers viewing UI direction
- Developers validating visual behavior quickly
- Team members preparing demos and presentations

## Core Content

### Included Files
- `demo/system-static-demo.html`
- `demo/system-static-demo.css`
- `demo/system-static-demo.js`
- `FRONTEND_IDEAS.md`

### What the Demo Covers
- Public pages and dashboard visual flow
- Theme switching and responsive behavior
- Modal interactions and dashboard chart states
- Command-palette/onboarding simulation
- Role switch simulation (`guest`, `student`, `owner`, `admin`)

### Run Instructions
- XAMPP route:
  - `http://localhost/websys/static/demo/system-static-demo.html`
- Direct file open:
  - Open `static/demo/system-static-demo.html` in a browser

### Validation Checklist
- Verify role toggle affects navigation/actions
- Verify dark mode updates chart visuals
- Verify all modal open/close interactions
- Verify mobile/tablet responsiveness

## Related Docs
- [Repository Overview](../README.md)
- [Frontend Pitch Notes](FRONTEND_IDEAS.md)
- [Architecture Reference](../docs/architecture/PROJECT_DOCUMENTATION.md)

## Maintenance
- Keep static demo behavior synchronized with runtime UX updates.
- Update this document when adding/removing demo views or interactions.
- Treat static assets as presentation aids, not runtime source of truth.
