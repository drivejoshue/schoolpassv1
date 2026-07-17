<script>
    (function () {
        const storageKey = 'schoolpass.theme';
        const savedTheme = localStorage.getItem(storageKey);
        const preferredTheme = window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light';

        document.documentElement.setAttribute(
            'data-bs-theme',
            savedTheme === 'dark' || savedTheme === 'light'
                ? savedTheme
                : preferredTheme
        );
    })();
</script>
