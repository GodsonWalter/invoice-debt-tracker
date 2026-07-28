@extends('layouts.app')

@section('page_title', 'Workspace User Details')

@section('content')
<div class="container-fluid py-2">
    <div class="mb-4 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start">
        <div>
            <h2 class="fs-4 fw-bold text-dark mb-1">Workspace User Details</h2>
            <p class="text-muted small mb-0">Review the user record for {{ $workspace->name }}.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('workspace.users.index', $workspace) }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Users
            </a>
            <a href="{{ route('workspace.users.edit', [$workspace, $user]) }}" class="btn btn-warning btn-sm">
                <i class="bi bi-pencil-square"></i> Edit User
            </a>
            <form action="{{ route('workspace.users.destroy', [$workspace, $user]) }}" method="POST" class="d-inline-block" data-delete-confirm>
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="bi bi-trash"></i> Remove
                </button>
            </form>
        </div>
    </div>

    <div class="card border-light shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <h6 class="mb-2">Name</h6>
                    <p class="mb-0">{{ $user->name }}</p>
                </div>
                <div class="col-12 col-md-6">
                    <h6 class="mb-2">Email</h6>
                    <p class="mb-0">{{ $user->email }}</p>
                </div>
                <div class="col-12 col-md-6">
                    <h6 class="mb-2">Role</h6>
                    <p class="mb-0">{{ $user->pivot->role }}</p>
                </div>
                <div class="col-12 col-md-6">
                    <h6 class="mb-2">User status</h6>
                    <p class="mb-0">
                        @if ($user->pivot->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </p>
                </div>
                <div class="col-12 col-md-6">
                    <h6 class="mb-2">Email verification</h6>
                    <p class="mb-0">
                        @if ($user->hasVerifiedEmail())
                            <span class="badge bg-success">Verified</span>
                        @else
                            <span class="badge bg-warning text-dark">Pending</span>
                        @endif
                    </p>
                </div>
                <div class="col-12 col-md-6">
                    <h6 class="mb-2">Joined workspace</h6>
                    <p class="mb-0">{{ $user->pivot->created_at->format('F j, Y') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
