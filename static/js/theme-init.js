(function () {
                const saved = localStorage.getItem('websys-theme');
                if (saved === 'dark') {
                    document.body.classList.add('theme-dark');
                }
            })();
