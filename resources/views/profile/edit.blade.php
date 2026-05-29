@extends('layouts.app')

@section('page_title', 'Profile')

@section('content')
    <div class="container-fluid py-2">
        <div class="mb-4 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start">
            <div>
                <h2 class="fs-4 fw-bold text-dark mb-1">User Profile</h2>
                <p class="text-muted small mb-0">Manage your personal details, security, and account preferences.</p>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <div class="card border-light shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white p-4 border-bottom">
                        <h5 class="fw-bold text-dark mb-0 fs-6">Profile Information</h5>
                    </div>
                    <div class="card-body p-3 p-md-4">
                        <form id="send-verification" method="POST" action="{{ route('verification.send') }}">
                            @csrf
                        </form>

                        <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                            @csrf
                            @method('PATCH')

                            <div class="d-flex flex-column flex-md-row gap-3 align-items-start mb-4">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center overflow-hidden shadow-sm"
                                    style="width: 88px; height: 88px; font-size: 2rem;">
                                    @if ($user->avatar)
                                        <img src="{{ asset('storage/'.$user->avatar) }}" alt="{{ $user->name }}" class="w-100 h-100 object-fit-cover">
                                    @else
                                        {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                                    @endif
                                </div>

                                <div class="flex-grow-1 w-100">
                                    <label for="avatar" class="form-label">Avatar</label>
                                    <input id="avatar" name="avatar" type="file" accept="image/*" class="form-control @error('avatar') is-invalid @enderror">
                                    @error('avatar')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label for="name" class="form-label">Name</label>
                                    <input id="name" name="name" type="text" class="form-control @error('name') is-invalid @enderror"
                                        value="{{ old('name', $user->name) }}" required autocomplete="name">
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror"
                                        value="{{ old('email', $user->email) }}" required autocomplete="username">
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror

                                    @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                                        <div class="form-text">
                                            Email is unverified.
                                            <button form="send-verification" class="btn btn-link btn-sm p-0 align-baseline" type="submit">
                                                Resend verification email
                                            </button>
                                        </div>
                                    @endif
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input id="phone" name="phone" type="text" class="form-control @error('phone') is-invalid @enderror"
                                        value="{{ old('phone', $user->phone) }}" autocomplete="tel">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="default_currency_id" class="form-label">Default Currency</label>
                                    <select id="default_currency_id" name="default_currency_id" class="form-select @error('default_currency_id') is-invalid @enderror">
                                        <option value="">Use system default</option>
                                        @foreach ($currencies as $currency)
                                            <option value="{{ $currency->id }}" {{ (string) old('default_currency_id', $user->default_currency_id) === (string) $currency->id ? 'selected' : '' }}>
                                                {{ $currency->code }} - {{ $currency->name }} ({{ $currency->symbol }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('default_currency_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-12">
                                    <label for="address" class="form-label">Address</label>
                                    <input id="address" name="address" type="text" class="form-control @error('address') is-invalid @enderror"
                                        value="{{ old('address', $user->address) }}" autocomplete="street-address">
                                    @error('address')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Save Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card border-light shadow-sm rounded-4 overflow-hidden">
                    <div class="card-header bg-white p-4 border-bottom">
                        <h5 class="fw-bold text-dark mb-0 fs-6">Update Password</h5>
                    </div>
                    <div class="card-body p-3 p-md-4">
                        <form method="POST" action="{{ route('password.update') }}">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label for="update_password_current_password" class="form-label">Current Password</label>
                                <input id="update_password_current_password" name="current_password" type="password"
                                    class="form-control {{ $errors->updatePassword->has('current_password') ? 'is-invalid' : '' }}"
                                    autocomplete="current-password">
                                @if ($errors->updatePassword->has('current_password'))
                                    <div class="invalid-feedback">{{ $errors->updatePassword->first('current_password') }}</div>
                                @endif
                            </div>

                            <div class="mb-3">
                                <label for="update_password_password" class="form-label">New Password</label>
                                <input id="update_password_password" name="password" type="password"
                                    class="form-control {{ $errors->updatePassword->has('password') ? 'is-invalid' : '' }}"
                                    autocomplete="new-password">
                                @if ($errors->updatePassword->has('password'))
                                    <div class="invalid-feedback">{{ $errors->updatePassword->first('password') }}</div>
                                @endif
                            </div>

                            <div class="mb-3">
                                <label for="update_password_password_confirmation" class="form-label">Confirm Password</label>
                                <input id="update_password_password_confirmation" name="password_confirmation" type="password"
                                    class="form-control {{ $errors->updatePassword->has('password_confirmation') ? 'is-invalid' : '' }}"
                                    autocomplete="new-password">
                                @if ($errors->updatePassword->has('password_confirmation'))
                                    <div class="invalid-feedback">{{ $errors->updatePassword->first('password_confirmation') }}</div>
                                @endif
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-shield-lock"></i> Update Password
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card border-light shadow-sm rounded-4 overflow-hidden mt-3">
                    <div class="card-header bg-white p-4 border-bottom">
                        <h5 class="fw-bold text-danger mb-0 fs-6">Danger Zone</h5>
                    </div>
                    <div class="card-body p-3 p-md-4">
                        <form method="POST" action="{{ route('profile.destroy') }}" onsubmit="return confirm('Delete your account permanently?');">
                            @csrf
                            @method('DELETE')

                            <div class="mb-3">
                                <label for="delete_password" class="form-label">Confirm Password</label>
                                <input id="delete_password" name="password" type="password"
                                    class="form-control {{ $errors->userDeletion->has('password') ? 'is-invalid' : '' }}"
                                    autocomplete="current-password">
                                @if ($errors->userDeletion->has('password'))
                                    <div class="invalid-feedback">{{ $errors->userDeletion->first('password') }}</div>
                                @endif
                            </div>

                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                <i class="bi bi-trash"></i> Delete Account
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
