@extends('layouts.app')

@section('page_title', 'Workspace Users')

@section('content')
<div class="container-fluid py-2">
    <div class="mb-4 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start">
        <div>
            <h2 class="fs-4 fw-bold text-dark mb-1">Workspace Users</h2>
            <p class="text-muted small mb-0">Manage members assigned to {{ $workspace->name }}.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('workspace.show', $workspace) }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Workspace
            </a>
            <a href="{{ route('workspace.users.create', $workspace) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-person-plus"></i> Invite User
            </a>
            <a href="{{ route('workspace.users.create', $workspace) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Add Workspace User
            </a>
        </div>
    </div>

    <div class="card border-light shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-3 p-md-4">
            @if ($users->isEmpty())
                <div class="text-center py-5 text-muted">
                    <p class="mb-1">No users have been added to this workspace yet.</p>
                    <a href="{{ route('workspace.users.create', $workspace) }}" class="btn btn-sm btn-primary">Invite a user</a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Verified</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                <tr>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>{{ $user->pivot->role ?? 'member' }}</td>
                                    <td>
                                        @if ($user->pivot->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($user->hasVerifiedEmail())
                                            <span class="badge bg-success">Verified</span>
                                        @else
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('workspace.users.show', [$workspace, $user]) }}" class="btn btn-sm btn-outline-primary me-1" title="View user">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('workspace.users.edit', [$workspace, $user]) }}" class="btn btn-sm btn-outline-secondary me-1" title="Edit user">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        @can('delete', [$workspace, $user])
                                            <form action="{{ route('workspace.users.destroy', [$workspace, $user]) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Remove this user from workspace?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove user">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">{{ $users->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
