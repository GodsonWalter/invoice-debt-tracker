const themeStorageKey = 'idt.theme';

function getStoredTheme() {
    try {
        const storedTheme = window.localStorage.getItem(themeStorageKey);

        return storedTheme === 'dark' || storedTheme === 'light' ? storedTheme : null;
    } catch (error) {
        return null;
    }
}

function getCurrentTheme() {
    return document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
}

function updateThemeControls(theme) {
    const isDark = theme === 'dark';
    const nextThemeLabel = isDark ? 'Switch to light mode' : 'Switch to dark mode';

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-label', nextThemeLabel);
        button.setAttribute('aria-pressed', isDark ? 'true' : 'false');
        button.setAttribute('title', nextThemeLabel);
        button.querySelector('[data-theme-icon="sun"]')?.toggleAttribute('hidden', !isDark);
        button.querySelector('[data-theme-icon="moon"]')?.toggleAttribute('hidden', isDark);
    });

    document.querySelector('meta[name="theme-color"]')?.setAttribute(
        'content',
        isDark ? '#0b1220' : '#0f1f3d',
    );
}

function setTheme(theme, persist = true) {
    const nextTheme = theme === 'dark' ? 'dark' : 'light';

    document.documentElement.dataset.theme = nextTheme;
    updateThemeControls(nextTheme);

    if (persist) {
        try {
            window.localStorage.setItem(themeStorageKey, nextTheme);
        } catch (error) {
            // Theme preference persistence is optional when storage is unavailable.
        }
    }
}

function initializeThemeControls() {
    const savedTheme = getStoredTheme();

    setTheme(savedTheme ?? getCurrentTheme(), false);

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            setTheme(getCurrentTheme() === 'dark' ? 'light' : 'dark');
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeThemeControls);
} else {
    initializeThemeControls();
}
