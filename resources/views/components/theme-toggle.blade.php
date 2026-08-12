<button type="button" {{ $attributes->merge(['class' => 'theme-toggle']) }} data-theme-toggle
    aria-label="Switch to dark mode" aria-pressed="false" title="Switch to dark mode">
    <span data-theme-icon="sun" aria-hidden="true" hidden><i class="bi bi-sun-fill"></i></span>
    <span data-theme-icon="moon" aria-hidden="true"><i class="bi bi-moon-stars-fill"></i></span>
    <span class="visually-hidden">Toggle color theme</span>
</button>
