# Brand Asset Notes

## Summary
- **Updated:** May 1, 2026
- **Scope:** Runtime navbar logo, wordmark, and PDF template assets

The navbar logo and NEXUS wordmark are called inline from `src/core/layout.php`.

## Current Files

- Light mode: `public/uploads/logodark.png`
- Dark mode: `public/uploads/logolight.png`
- Wordmark font: `static/fonts/akira-expanded-demo.otf`
- PDF export template: `public/uploads/pdftemplate.png`

## Maintenance

Change logo sizing in the `.nav-logo` and `.nav-logo-img` CSS rules in `src/core/layout.php`.
Change wordmark sizing in the `.nav-wordmark-main` and `.about-wordmark .nav-wordmark-main` CSS rules in `src/core/layout.php`.
Transaction PDF exports draw `public/uploads/pdftemplate.png` as a full-page A4 background in `src/actions/content_actions.php`.
