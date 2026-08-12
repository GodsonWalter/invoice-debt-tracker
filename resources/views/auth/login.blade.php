@extends('layouts.guest')

@section('content')
    <form method="POST" action="{{ route('login') }}" class="auth-form">
        @csrf

        <div class="auth-field">
            <label for="email">Email address</label>
            <div class="auth-input-wrap">
                <svg class="auth-input-icon" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                    <rect x="4.5" y="7.5" width="23" height="17" rx="2" stroke="currentColor" stroke-width="1.9" />
                    <path d="m5.5 9 10.5 8L26.5 9" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <input id="email" name="email" type="email" class="auth-input" value="{{ old('email') }}"
                    placeholder="Enter your email" autocomplete="username" required autofocus>
            </div>
            @error('email')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-field">
            <label for="password">Password</label>
            <div class="auth-input-wrap auth-password-wrap">
                <svg class="auth-input-icon" viewBox="0 0 32 32" fill="none" aria-hidden="true">
                    <rect x="7" y="14" width="18" height="14" rx="2.5" stroke="currentColor" stroke-width="1.9" />
                    <path d="M11 14v-3a5 5 0 0 1 10 0v3" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
                </svg>
                <input id="password" name="password" type="password" class="auth-input" placeholder="Enter your password"
                    autocomplete="current-password" required>
                <button type="button" class="auth-password-toggle" id="togglePassword" aria-label="Show password">
                    <svg viewBox="0 0 32 32" fill="none" aria-hidden="true">
                        <path d="M3.5 16s4.4-7.2 12.5-7.2S28.5 16 28.5 16 24.1 23.2 16 23.2 3.5 16 3.5 16Z" stroke="currentColor" stroke-width="1.9" />
                        <circle cx="16" cy="16" r="3.2" stroke="currentColor" stroke-width="1.9" />
                    </svg>
                </button>
            </div>
            @error('password')
                <p class="auth-error">{{ $message }}</p>
            @enderror
        </div>

        <div class="auth-remember">
            <input id="remember" name="remember" type="checkbox" value="1">
            <label for="remember">Remember me</label>
        </div>

        <a href="{{ route('password.request') }}" class="auth-forgot">Forgot password?</a>

        <button type="submit" class="auth-submit">Sign in</button>

        <div class="auth-divider" aria-hidden="true"><span>or</span></div>

        <p class="auth-signup">New to IDT? <a href="{{ route('register') }}">Create an account</a></p>
    </form>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleButton = document.getElementById('togglePassword');
            const passwordInput = document.getElementById('password');

            if (!toggleButton || !passwordInput) {
                return;
            }

            toggleButton.addEventListener('click', function () {
                const shouldShowPassword = passwordInput.type === 'password';

                passwordInput.type = shouldShowPassword ? 'text' : 'password';
                toggleButton.setAttribute('aria-label', shouldShowPassword ? 'Hide password' : 'Show password');
            });
        });
    </script>
@endpush
