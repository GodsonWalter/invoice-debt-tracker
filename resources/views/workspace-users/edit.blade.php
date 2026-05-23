@extends('layouts.app')

@section('page_title', 'Edit Workspace User')

@section('content')
<div class="container-fluid py-2">
    <div class="mb-4 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start">
        <div>
            <h2 class="fs-4 fw-bold text-dark mb-1">Edit {{ $user->name }}</h2>
            <p class="text-muted small mb-0">Update the workspace role or active status for this user.</p>
        </div>
        <a href="{{ route('workspace.users.show', [$workspace, $user]) }}" class="btn btn-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Back to User Details
        </a>
    </div>
    
    <div class="card border-light shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-3 p-md-4">
            <form method="POST" action="{{ route('workspace.users.update', [$workspace, $user]) }}">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input id="email" type="email" value="{{ $user->email }}" class="form-control" disabled>
                </div>

                <div class="mb-3">
                    <label for="name" class="form-label">Full name</label>
                    <input id="name" name="name" type="text" value="{{ $user->name }}" class="form-control" disabled>                  
                </div>

                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="role" class="form-label">Workspace role</label>
                        <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
                            <option value="member"{{ old('role', $user->pivot->role) === 'member' ? ' selected' : '' }}>Member</option>
                            <option value="admin"{{ old('role', $user->pivot->role) === 'admin' ? ' selected' : '' }}>Admin</option>
                            <option value="viewer"{{ old('role', $user->pivot->role) === 'viewer' ? ' selected' : '' }}>Viewer</option>
                            <option value="owner"{{ old('role', $user->pivot->role) === 'owner' ? ' selected' : '' }}>Owner</option>
                        </select>
                        @error('role')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="is_active" class="form-label">Activation status</label>
                        <select id="is_active" name="is_active" class="form-select @error('is_active') is-invalid @enderror" required>
                            <option value="1"{{ old('is_active', $user->pivot->is_active ? '1' : '0') === '1' ? ' selected' : '' }}>Active</option>
                            <option value="0"{{ old('is_active', $user->pivot->is_active ? '1' : '0') === '0' ? ' selected' : '' }}>Inactive</option>
                        </select>
                        @error('is_active')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Update workspace user</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
