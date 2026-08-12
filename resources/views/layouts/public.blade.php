<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ trim($__env->yieldContent('meta_description', 'IDT helps businesses create invoices, track payments, and manage customer debts from one secure workspace.')) }}">
    <meta name="theme-color" content="#0f1f3d">

    <title>{{ trim($__env->yieldContent('meta_title', config('app.name', 'IDT').' | Invoice and debt management')) }}</title>

    <meta property="og:title" content="{{ trim($__env->yieldContent('og_title', 'Create Invoices. Track Payments. Recover Debts Faster.')) }}">
    <meta property="og:description" content="{{ trim($__env->yieldContent('og_description', 'Create professional invoices, monitor payments, automate reminders, and manage customer debts from one secure workspace.')) }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="{{ config('app.name', 'IDT') }}">
    <meta name="twitter:card" content="summary">

    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite('resources/css/public-home.css')
    @endif
    @stack('head')
</head>

<body class="public-home">
    @yield('content')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>

</html>
