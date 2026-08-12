<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <x-theme-init />
    @php
        $platform = $platformSettings['settings'];
        $platformFaviconUrl = $platformSettings['faviconUrl'];
        $platformOgImageUrl = $platformSettings['ogImageUrl'];
        $platformTwitterImageUrl = $platformSettings['twitterImageUrl'];
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ trim($__env->yieldContent('meta_description', $platform->seo_description ?: $platform->tagline)) }}">
    @if ($platform->seo_keywords)<meta name="keywords" content="{{ $platform->seo_keywords }}">@endif
    <meta name="theme-color" content="#0f1f3d">

    <title>{{ trim($__env->yieldContent('meta_title', $platform->seo_title ?: $platform->product_title)) }}</title>

    <meta property="og:title" content="{{ trim($__env->yieldContent('og_title', $platform->og_title ?: $platform->product_title)) }}">
    <meta property="og:description" content="{{ trim($__env->yieldContent('og_description', $platform->og_description ?: $platform->seo_description)) }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ $platform->canonical_url ?: url()->current() }}">
    <meta property="og:site_name" content="{{ $platform->product_name }}">
    @if ($platformOgImageUrl)<meta property="og:image" content="{{ $platformOgImageUrl }}">@endif
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ trim($__env->yieldContent('twitter_title', $platform->twitter_title ?: $platform->og_title ?: $platform->product_title)) }}">
    <meta name="twitter:description" content="{{ trim($__env->yieldContent('twitter_description', $platform->twitter_description ?: $platform->og_description ?: $platform->seo_description)) }}">
    @if ($platformTwitterImageUrl)<meta name="twitter:image" content="{{ $platformTwitterImageUrl }}">@endif
    @if ($platform->canonical_url)<link rel="canonical" href="{{ $platform->canonical_url }}">@endif
    @if ($platformFaviconUrl)<link rel="icon" href="{{ $platformFaviconUrl }}">@endif

    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/public-home.css', 'resources/css/theme.css', 'resources/js/theme.js'])
    @endif
    @stack('head')
</head>

<body class="public-home">
    @yield('content')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>

</html>
