@extends('layouts.guest')
@section('content')
    <form method="POST" action="{{ route('login') }}">
        @csrf

        {{-- Email Address --}}
        <div class="mb-3">
            <label class="form-lable"><i class="bi bi-envelope"></i> Email</label>
            <input type="email" name="email" class="form-control" required autofocus value="{{ old('email') }}">
            @error('email')
                <div class="text-danger mt-1">{{ $message }}</div>
            @enderror
        </div>

        {{-- Password with show password --}}
        <div class="mb-3">
            <label class="form-lable"> <i class="bi bi-lock"></i> Password</label>
            <div class="input-group">
                <input type="password" name="password" class="form-control" required id="password">
                <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            @error('password')
                <div class="text-danger mt-1">{{ $message }}</div>
            @enderror
        </div>


        {{-- Remember Me --}}
        <div class="mb-3 form-check">
            <input type="checkbox" name="remember" class="form-check-input" id="remember">
            <label class="form-check-label" for="remember">Remember Me</label>
        </div>

        <div class="d-flex justify-content-between align-items-center">
            <a href="{{ route('password.request') }}">Forgot your password?</a>
        </div>

        <div class="mt-3 text-center">
            Don't have an account? <a href="{{ route('register') }}">Register</a>
        </div>

        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const toggleBtn = document.getElementById('togglePassword');
                    const passwordField = document.getElementById('password');

                    if (!toggleBtn || !passwordField) return;

                    toggleBtn.addEventListener('click', function () {
                        const isPassword = passwordField.getAttribute('type') === 'password';
                        passwordField.setAttribute('type', isPassword ? 'text' : 'password');

                        const icon = this.querySelector('i');
                        if (icon) {
                            icon.classList.toggle('bi-eye');
                            icon.classList.toggle('bi-eye-slash');
                        }
                    });
                });
            </script>
        @endpush
        {{-- login button --}}
        <div class="d-grid">
            <button type="submit" class="btn btn-primary">Login</button>
        </div>

    </form>
@endsection