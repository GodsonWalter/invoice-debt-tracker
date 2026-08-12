<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Invoice & Debt Tracker') }}</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --auth-ink: #112441;
            --auth-muted: #63718a;
            --auth-border: #d4dae3;
            --auth-blue: #1168f4;
            --auth-blue-dark: #0754db;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
            margin: 0;
        }

        body {
            background: #ffffff;
            color: var(--auth-ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        a {
            color: var(--auth-blue);
        }

        .auth-shell {
            display: grid;
            grid-template-columns: minmax(0, 1.09fr) minmax(420px, .91fr);
            min-height: 100vh;
            overflow: hidden;
        }

        .auth-aside {
            position: relative;
            isolation: isolate;
            display: flex;
            min-height: 100vh;
            flex-direction: column;
            overflow: hidden;
            padding: 90px 7vw 72px 5.35vw;
            background:
                radial-gradient(circle at 98% 86%, rgba(21, 102, 255, .82), transparent 33%),
                linear-gradient(145deg, #020a27 0%, #041c51 52%, #073d9e 79%, #1266f2 100%);
            color: #ffffff;
        }

        .auth-aside::before {
            position: absolute;
            z-index: -1;
            right: -4%;
            bottom: -5%;
            width: 58%;
            height: 45%;
            background-image: radial-gradient(rgba(103, 160, 255, .22) 1.5px, transparent 1.5px);
            background-size: 16px 16px;
            content: "";
            mask-image: linear-gradient(145deg, transparent 0%, #000000 60%);
        }

        .auth-aside::after {
            position: absolute;
            z-index: -1;
            right: -10%;
            bottom: -8%;
            width: 72%;
            height: 31%;
            border-top: 1px solid rgba(113, 163, 255, .23);
            border-radius: 50%;
            box-shadow:
                0 -26px 0 -25px rgba(113, 163, 255, .2),
                0 -52px 0 -51px rgba(113, 163, 255, .18),
                0 -78px 0 -77px rgba(113, 163, 255, .16);
            content: "";
            transform: rotate(-14deg);
        }

        .auth-brand {
            display: inline-flex;
            z-index: 1;
            align-items: center;
            gap: 25px;
            color: #ffffff;
            text-decoration: none;
        }

        .auth-brand-mark {
            display: grid;
            width: 72px;
            height: 72px;
            flex: 0 0 72px;
            place-items: center;
            border-radius: 11px;
            background: linear-gradient(145deg, #1276ff, #0b5ee1);
            box-shadow: 0 12px 28px rgba(0, 0, 0, .18);
            font-size: 30px;
            font-weight: 800;
            letter-spacing: -1.5px;
        }

        .auth-brand-name {
            font-size: clamp(1.4rem, 1.8vw, 1.75rem);
            font-weight: 650;
            letter-spacing: -.025em;
            white-space: nowrap;
        }

        .auth-aside-copy {
            z-index: 1;
            max-width: 625px;
            margin-top: 112px;
        }

        .auth-aside-copy h2 {
            max-width: 620px;
            margin: 0;
            color: #ffffff;
            font-size: clamp(3.55rem, 5.25vw, 5.2rem);
            font-weight: 750;
            letter-spacing: -.065em;
            line-height: 1.12;
        }

        .auth-aside-copy p {
            max-width: 575px;
            margin: 20px 0 0;
            color: rgba(255, 255, 255, .91);
            font-size: clamp(1.35rem, 1.8vw, 1.8rem);
            letter-spacing: -.018em;
            line-height: 1.45;
        }

        .auth-features {
            display: grid;
            gap: 21px;
            margin: 51px 0 0;
            padding: 0;
            list-style: none;
        }

        .auth-feature {
            display: flex;
            align-items: center;
            gap: 20px;
            color: rgba(255, 255, 255, .98);
            font-size: clamp(1.15rem, 1.45vw, 1.45rem);
            letter-spacing: -.012em;
        }

        .auth-feature-icon {
            display: grid;
            width: 64px;
            height: 64px;
            flex: 0 0 64px;
            place-items: center;
            border: 1px solid rgba(157, 195, 255, .22);
            border-radius: 50%;
            background: rgba(80, 139, 241, .2);
            box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .04);
        }

        .auth-feature-icon svg {
            width: 31px;
            height: 31px;
        }

        .auth-invoice {
            position: absolute;
            z-index: -1;
            fill: none;
            stroke: rgba(90, 145, 255, .7);
            stroke-linecap: round;
            stroke-linejoin: round;
            stroke-width: 5;
        }

        .auth-invoice--top {
            top: 56px;
            right: -9px;
            width: min(390px, 39vw);
            opacity: .28;
            transform: rotate(10deg);
        }

        .auth-invoice--bottom {
            right: 6%;
            bottom: 33px;
            width: min(360px, 35vw);
            opacity: .28;
            transform: rotate(12deg);
        }

        .auth-panel {
            display: flex;
            min-height: 100vh;
            align-items: flex-start;
            justify-content: center;
            overflow-y: auto;
            padding: 13.6vh 6vw 72px;
            background: #ffffff;
        }

        .auth-panel-inner {
            width: 100%;
            max-width: 518px;
        }

        .auth-panel-heading h1 {
            margin: 0;
            color: var(--auth-ink);
            font-size: clamp(2.75rem, 3.8vw, 3.75rem);
            font-weight: 750;
            letter-spacing: -.052em;
            line-height: 1.1;
        }

        .auth-panel-heading p {
            margin: 14px 0 0;
            color: var(--auth-muted);
            font-size: clamp(1.35rem, 1.8vw, 1.75rem);
            letter-spacing: -.025em;
            line-height: 1.35;
        }

        .auth-panel-content {
            margin-top: 55px;
        }

        .auth-form {
            display: grid;
            gap: 0;
        }

        .auth-field {
            margin-bottom: 36px;
        }

        .auth-field label,
        .auth-remember label {
            color: var(--auth-ink);
            font-size: 1.35rem;
            font-weight: 450;
        }

        .auth-field label {
            display: block;
            margin-bottom: 10px;
        }

        .auth-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
            min-height: 72px;
            border: 1px solid var(--auth-border);
            border-radius: 10px;
            background: #ffffff;
            box-shadow: 0 1px 1px rgba(17, 36, 65, .02);
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .auth-input-wrap:focus-within {
            border-color: var(--auth-blue);
            box-shadow: 0 0 0 3px rgba(17, 104, 244, .13);
        }

        .auth-input-icon {
            width: 29px;
            height: 29px;
            margin: 0 19px;
            flex: 0 0 29px;
            color: #32445f;
        }

        .auth-input {
            width: 100%;
            min-width: 0;
            height: 70px;
            padding: 0 18px 0 0;
            border: 0;
            outline: 0;
            color: var(--auth-ink);
            background: transparent;
            box-shadow: none;
            font: inherit;
            font-size: 1.3rem;
        }

        .auth-input::placeholder {
            color: #718099;
            opacity: 1;
        }

        .auth-password-wrap .auth-input {
            padding-right: 20px;
        }

        .auth-password-toggle {
            display: grid;
            width: 76px;
            height: 70px;
            flex: 0 0 76px;
            place-items: center;
            border: 0;
            border-left: 1px solid var(--auth-border);
            border-radius: 0 9px 9px 0;
            color: #32445f;
            background: transparent;
            cursor: pointer;
        }

        .auth-password-toggle:hover,
        .auth-password-toggle:focus-visible {
            color: var(--auth-blue);
            background: #f7faff;
            outline: 0;
        }

        .auth-password-toggle svg {
            width: 28px;
            height: 28px;
        }

        .auth-error {
            margin: 8px 0 0;
            color: #c62828;
            font-size: .95rem;
        }

        .auth-remember {
            display: flex;
            align-items: center;
            gap: 13px;
            margin-top: -3px;
        }

        .auth-remember input {
            width: 25px;
            height: 25px;
            margin: 0;
            appearance: none;
            border: 1px solid var(--auth-border);
            border-radius: 6px;
            background: #ffffff;
            cursor: pointer;
        }

        .auth-remember input:checked {
            border-color: var(--auth-blue);
            background: var(--auth-blue) center / 16px 16px no-repeat;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='white' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m3 8 3 3 7-7'/%3E%3C/svg%3E");
        }

        .auth-remember input:focus-visible {
            outline: 3px solid rgba(17, 104, 244, .16);
            outline-offset: 1px;
        }

        .auth-forgot {
            display: inline-block;
            margin-top: 18px;
            font-size: 1.3rem;
            font-weight: 450;
            text-underline-offset: 3px;
        }

        .auth-submit {
            width: 100%;
            min-height: 73px;
            margin-top: 33px;
            border: 0;
            border-radius: 9px;
            color: #ffffff;
            background: linear-gradient(135deg, #176ff7, #1160e9);
            box-shadow: 0 8px 18px rgba(17, 104, 244, .12);
            cursor: pointer;
            font: inherit;
            font-size: 1.4rem;
            font-weight: 600;
            transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
        }

        .auth-submit:hover {
            background: linear-gradient(135deg, #0d64ee, var(--auth-blue-dark));
            box-shadow: 0 10px 22px rgba(17, 104, 244, .2);
            transform: translateY(-1px);
        }

        .auth-submit:focus-visible {
            outline: 3px solid rgba(17, 104, 244, .26);
            outline-offset: 3px;
        }

        .auth-divider {
            display: flex;
            align-items: center;
            gap: 26px;
            margin: 28px 0 30px;
            color: #718099;
            font-size: 1.2rem;
        }

        .auth-divider::before,
        .auth-divider::after {
            height: 1px;
            flex: 1;
            background: #d7dce4;
            content: "";
        }

        .auth-signup {
            margin: 0;
            color: var(--auth-ink);
            font-size: 1.3rem;
            text-align: center;
        }

        .auth-signup a {
            text-decoration: none;
        }

        @media (max-width: 1100px) {
            .auth-shell {
                grid-template-columns: minmax(0, 1fr) minmax(390px, .85fr);
            }

            .auth-aside {
                padding-left: 6vw;
            }

            .auth-brand {
                gap: 16px;
            }

            .auth-brand-mark {
                width: 60px;
                height: 60px;
                flex-basis: 60px;
                font-size: 25px;
            }

            .auth-aside-copy {
                margin-top: 90px;
            }
        }

        @media (max-width: 850px) {
            .auth-shell {
                display: block;
            }

            .auth-aside {
                display: none;
            }

            .auth-panel {
                min-height: 100vh;
                padding: 55px 24px 64px;
            }

            .auth-panel-inner {
                max-width: 560px;
            }
        }

        @media (max-width: 480px) {
            .auth-panel {
                padding: 36px 20px 48px;
            }

            .auth-panel-heading h1 {
                font-size: 2.55rem;
            }

            .auth-panel-heading p {
                font-size: 1.2rem;
            }

            .auth-panel-content {
                margin-top: 42px;
            }

            .auth-field label,
            .auth-remember label,
            .auth-forgot,
            .auth-signup {
                font-size: 1.1rem;
            }

            .auth-input {
                font-size: 1.1rem;
            }
        }
    </style>

    @stack('styles')
</head>

<body>
    @php
        $authCopy = match (request()->route()?->getName()) {
            'register' => ['title' => 'Create your account', 'subtitle' => 'Start managing your invoices today'],
            'password.request' => ['title' => 'Reset your password', 'subtitle' => 'We will help you get back into your account'],
            'password.reset' => ['title' => 'Choose a new password', 'subtitle' => 'Create a secure password for your account'],
            'password.confirm' => ['title' => 'Confirm your password', 'subtitle' => 'This is a secure area of your account'],
            'verification.notice' => ['title' => 'Verify your email', 'subtitle' => 'One quick step before you get started'],
            'account.status' => ['title' => 'Account unavailable', 'subtitle' => 'We could not sign you in'],
            default => ['title' => 'Welcome back', 'subtitle' => 'Sign in to your IDT account'],
        };
    @endphp

    <main class="auth-shell">
        <aside class="auth-aside" aria-label="Invoice and debt tracker overview">
            <a href="{{ route('home') }}" class="auth-brand">
                <span class="auth-brand-mark" aria-hidden="true">IDT</span>
                <span class="auth-brand-name">Invoice &amp; Debt Tracker</span>
            </a>

            <div class="auth-aside-copy">
                <h2>Stay on top of<br>every invoice</h2>
                <p>Track payments, monitor outstanding debts,<br>and keep your cash flow healthy.</p>

                <ul class="auth-features">
                    <li class="auth-feature">
                        <span class="auth-feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 32 32" fill="none">
                                <path d="M16 3.4 27.3 7v8.1c0 6.8-4.7 11.6-11.3 13.5C10.4 26.7 5.7 21.9 5.7 15.1V7L16 3.4Z" stroke="currentColor" stroke-width="1.9" />
                                <path d="m11 15.9 3.2 3.1 6.8-7" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <span>Secure workspace access</span>
                    </li>
                    <li class="auth-feature">
                        <span class="auth-feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 32 32" fill="none">
                                <path d="m4 23 8.3-8.4 5.2 4.4L28 8.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M21.5 8.5H28v6.4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <span>Real-time payment tracking</span>
                    </li>
                    <li class="auth-feature">
                        <span class="auth-feature-icon" aria-hidden="true">
                            <svg viewBox="0 0 32 32" fill="none">
                                <path d="M8.1 19.7V14a7.9 7.9 0 0 1 15.8 0v5.7l2.2 3.2H5.9l2.2-3.2Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round" />
                                <path d="M13.2 26.1a3.2 3.2 0 0 0 5.6 0M4.1 22.9h23.8" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
                            </svg>
                        </span>
                        <span>Automated reminders</span>
                    </li>
                </ul>
            </div>

            <svg class="auth-invoice auth-invoice--top" viewBox="0 0 320 400" aria-hidden="true">
                <path d="M38 22h190l54 54v296H38V22Z" />
                <path d="M228 22v58h54M68 92h100M68 119h88M68 146h118M68 193h193M68 214h193M68 235h193M68 256h193" />
                <rect x="68" y="182" width="193" height="91" rx="2" />
                <circle cx="211" cy="335" r="28" />
                <path d="M211 315v40M220 322c-2.4-2.8-5.8-4.2-9.1-4.2-5.7 0-9.8 3.2-9.8 7.7 0 11.1 19.9 6.6 19.9 17.3 0 4.5-4.1 7.8-9.9 7.8-4 0-7.6-1.5-10.2-4.2" />
                <path d="m228 22 54 54-54 0V22Z" />
            </svg>

            <svg class="auth-invoice auth-invoice--bottom" viewBox="0 0 320 400" aria-hidden="true">
                <path d="M38 22h190l54 54v296H38V22Z" />
                <path d="M228 22v58h54M68 92h100M68 119h88M68 146h118M68 193h193M68 214h193M68 235h193M68 256h193" />
                <rect x="68" y="182" width="193" height="91" rx="2" />
                <circle cx="211" cy="335" r="28" />
                <path d="M211 315v40M220 322c-2.4-2.8-5.8-4.2-9.1-4.2-5.7 0-9.8 3.2-9.8 7.7 0 11.1 19.9 6.6 19.9 17.3 0 4.5-4.1 7.8-9.9 7.8-4 0-7.6-1.5-10.2-4.2" />
                <path d="m228 22 54 54-54 0V22Z" />
            </svg>
        </aside>

        <section class="auth-panel">
            <div class="auth-panel-inner">
                <header class="auth-panel-heading">
                    <h1>{{ $authCopy['title'] }}</h1>
                    <p>{{ $authCopy['subtitle'] }}</p>
                </header>

                <div class="auth-panel-content">
                    @yield('content')
                </div>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    @stack('scripts')
</body>

</html>
