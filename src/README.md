# Source Layer Guide

## Summary
- **Scope:** `src/` runtime application layers
- **Updated:** April 27, 2026
- **Pattern:** Layered single-entry PHP architecture

## Purpose
This document explains how source code is organized and where new functionality should be added.

## Audience
- Developers implementing new routes/features
- Maintainers reviewing architecture boundaries

## Core Content

### Layer Responsibilities
- `core/`: runtime bootstrap, auth/session, DB bootstrap, shared layout, generic helpers
- `lib/`: reusable domain and utility helpers
- `actions/`: POST mutation handlers and workflow command handlers
- `pages/`: page renderers and route output handlers
- `services/`: data aggregation and service-level composition

### Entry and Dispatch
- `index.php` is the single entry point.
- Route rendering and action dispatch are coordinated from `index.php`.
- Shared shell rendering is handled through `src/core/layout.php`.

### Placement Rules
- Put reusable runtime concerns in `src/core/`.
- Put domain helpers that can be reused by multiple flows in `src/lib/`.
- Put state-changing action handlers in `src/actions/`.
- Put view/page rendering logic in `src/pages/`.
- Put aggregation/query shaping logic in `src/services/`.

## Related Docs
- [Repository Overview](../README.md)
- [Project Architecture](../docs/architecture/PROJECT_DOCUMENTATION.md)
- [Function Analysis](../docs/reference/FUNCTION_ANALYSIS.md)

## Maintenance
- Keep edits small and local to the correct layer.
- Avoid introducing new top-level architectural patterns unless required.
- Keep action handlers CSRF-protected and route guards role-aware.
