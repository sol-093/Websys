# Brand Asset Notes

## Summary
- **Updated:** May 5, 2026
- **Scope:** Runtime navbar logo, About page brand image, and PDF template assets

The INVOLVE navbar logo and About page brand image are called inline from `src/core/layout.php`.

## Current Files

- Light mode: `public/uploads/involvelogo dark.png`
- Dark mode: `public/uploads/involvelogo light.png`
- Brand images: `public/uploads/involvelogo dark.png`, `public/uploads/involvelogo light.png`
- PDF export template: `public/uploads/pdftemplate.png`

## Maintenance

Change logo sizing in the `.nav-logo` and `.nav-logo-img` CSS rules in `src/core/layout.php`.
Change About page brand image sizing in the `.about-logo` CSS rule in `src/core/layout.php`.
Transaction PDF exports draw `public/uploads/pdftemplate.png` as a full-page A4 background in `src/actions/content_actions.php`.
