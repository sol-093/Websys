/* ================================================
   INVOLVE - EARLY THEME BOOTSTRAP
   ================================================

    SECTION MAP:
   1. Read Saved Theme
   2. Apply Dark Theme Before Main JS Loads

    WORK GUIDE:
   - Edit this file only for early theme bootstrapping.
   ================================================ */

(function () {
                const saved = localStorage.getItem('websys-theme');
                if (saved === 'dark') {
                    document.body.classList.add('theme-dark');
                }
            })();
