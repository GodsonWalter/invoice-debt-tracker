@extends('layouts.app')

@section('page_title', 'Invite Workspace User')

@section('content')
    <div class="container-fluid py-2">
        <div class="mb-4 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start">
            <div>
                <h2 class="fs-4 fw-bold text-dark mb-1">Invite User to {{ $workspace->name }}</h2>
                <p class="text-muted small mb-0">Enter the user email to auto-fill profile details or create a new account.
                    An invitation email will be sent to the user.</p>
            </div>
            <a href="{{ route('workspace.users.index', $workspace) }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Users
            </a>
        </div>

        <div class="card border-light shadow-sm rounded-4 overflow-hidden">
            <div class="card-body p-3 p-md-4">
                <form method="POST" action="{{ route('workspace.users.store', $workspace) }}">
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}"
                            class="form-control @error('email') is-invalid @enderror" required>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div id="email-status" class="form-text text-muted">Enter an email to load existing profile data.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Full name</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}"
                            class="form-control @error('name') is-invalid @enderror" required>
                        <small id="nameHint" class="form-text text-muted"></small>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="role" class="form-label">Workspace role</label>
                            <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
                                <option value="member" {{ old('role') === 'member' ? ' selected' : '' }}>Member</option>
                                <option value="admin" {{ old('role') === 'admin' ? ' selected' : '' }}>Admin</option>
                                <option value="viewer" {{ old('role') === 'viewer' ? ' selected' : '' }}>Viewer</option>
                                <option value="owner" {{ old('role') === 'owner' ? ' selected' : '' }}>Owner</option>
                            </select>
                            @error('role')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="is_active" class="form-label">Activation status</label>
                            <div class="form-text text-muted">
                                Inactive — the user must verify their email address and accept the workspace invitation
                                before gaining access.
                            </div>

                            {{-- <select id="is_active" name="is_active"
                                class="form-select @error('is_active') is-invalid @enderror" required>
                                <option value="1" {{ old('is_active', '1' )==='1' ? ' selected' : '' }}>Active</option>
                                <option value="0" {{ old('is_active')==='0' ? ' selected' : '' }}>Inactive</option>
                            </select>
                            @error('is_active')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror --}}
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Send Invitation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const emailInput = document.querySelector('#email');
                const nameInput = document.querySelector('#name');
                const emailStatus = document.querySelector('#email-status');
                const lookupUrl = '{{ route('workspace.users.lookup', $workspace) }}';
                let timer;

                function updateStatus(message, variant = 'text-muted') {
                    emailStatus.textContent = message;
                    emailStatus.className = 'form-text ' + variant;
                }

                emailInput.addEventListener('input', function () {
                    clearTimeout(timer);
                    const email = this.value.trim();

                    if (!email) {
                        nameInput.disabled = false;
                        nameInput.value = '';
                        document.querySelector('#nameHint').textContent = '';
                        updateStatus('Enter an email to load existing profile data.');
                        return;
                    }

                    timer = setTimeout(async function () {
                        updateStatus('Checking email...', 'text-muted');

                        try {
                            const response = await fetch(`${lookupUrl}?email=${encodeURIComponent(email)}`, {
                                headers: { 'Accept': 'application/json' },
                            });

                            if (!response.ok) {
                                nameInput.disabled = false;
                                nameInput.value = '';
                                document.querySelector('#nameHint').textContent = '';
                                updateStatus('Unable to lookup the email at the moment.', 'text-danger');
                                return;
                            }

                            const data = await response.json();

                            if (data.exists) {
                                nameInput.value = data.user.name || '';
                                // nameInput.disabled = true;
                                document.querySelector('#nameHint').textContent = 'Existing user profile cannot be modified.';
                                updateStatus('Existing user found. Profile details were populated.', 'text-success');
                            } else {
                                nameInput.disabled = false;
                                nameInput.value = '';
                                document.querySelector('#nameHint').textContent = 'Enter the full name for the new user.';
                                updateStatus('No user found. A new account will be created and verified.', 'text-warning');
                            }
                        } catch (error) {
                            nameInput.disabled = false;
                            nameInput.value = '';
                            document.querySelector('#nameHint').textContent = '';
                            updateStatus('Email lookup failed.', 'text-danger');
                        }
                    }, 500);
                });
            });
        </script>
    @endpush
@endsection