<script>
    (() => {
        try {
            const savedTheme = window.localStorage.getItem('idt.theme');
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = savedTheme === 'dark' || savedTheme === 'light'
                ? savedTheme
                : (systemPrefersDark ? 'dark' : 'light');

            document.documentElement.dataset.theme = theme;
        } catch (error) {
            document.documentElement.dataset.theme = 'light';
        }
    })();
</script>
