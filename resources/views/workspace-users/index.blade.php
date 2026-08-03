@extends('layouts.app')

@section('page_title', 'Workspace Users')

@section('content')
    @php
        $roleOptions = [
            'admin' => 'Admin',
            'member' => 'Member',
            'viewer' => 'Viewer',
        ];
        $sortOptions = [
            'name' => 'Name',
            'email' => 'Email',
            'role' => 'Role',
            'is_active' => 'Status',
            'created_at' => 'Created date',
        ];
    @endphp

    <div class="container-fluid py-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="fs-4 fw-bold mb-1">Workspace users</h1>
                <p class="text-muted mb-0">Manage members assigned to <strong>{{ $workspace->name }}</strong>.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('workspace.show', $workspace) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Workspace
                </a>
                <a href="{{ route('workspace.users.create', $workspace) }}" class="btn btn-primary">
                    <i class="bi bi-person-plus me-1"></i> Invite user
                </a>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('workspace.users.index', $workspace) }}" class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label for="workspace-user-search" class="form-label">Search</label>
                        <input id="workspace-user-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}"
                            class="form-control" maxlength="100" placeholder="Name or email">
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="workspace-user-role" class="form-label">Role</label>
                        <select id="workspace-user-role" name="role" class="form-select">
                            <option value="">All roles</option>
                            @foreach ($roleOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="workspace-user-status" class="form-label">Status</label>
                        <select id="workspace-user-status" name="status" class="form-select">
                            <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>All statuses</option>
                            <option value="active" @selected(($filters['status'] ?? 'all') === 'active')>Active</option>
                            <option value="inactive" @selected(($filters['status'] ?? 'all') === 'inactive')>Inactive</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="workspace-user-sort" class="form-label">Sort by</label>
                        <select id="workspace-user-sort" name="sort" class="form-select">
                            @foreach ($sortOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['sort'] ?? 'name') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2 d-flex gap-2">
                        <select name="direction" class="form-select" aria-label="Sort direction">
                            <option value="asc" @selected(($filters['direction'] ?? 'asc') === 'asc')>Asc</option>
                            <option value="desc" @selected(($filters['direction'] ?? 'asc') === 'desc')>Desc</option>
                        </select>
                        <button class="btn btn-outline-primary" type="submit" title="Apply filters">
                            <i class="bi bi-funnel"></i><span class="visually-hidden">Filter</span>
                        </button>
                    </div>
                    @if (($filters['search'] ?? '') || ($filters['role'] ?? '') || ($filters['status'] ?? 'all') !== 'all' || ($filters['sort'] ?? 'name') !== 'name' || ($filters['direction'] ?? 'asc') !== 'asc')
                        <div class="col-12">
                            <a href="{{ route('workspace.users.index', $workspace) }}" class="btn btn-sm btn-outline-secondary">Reset filters</a>
                        </div>
                    @endif
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 px-4 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h2 class="h6 fw-bold mb-1">Workspace members</h2>
                    <p class="text-muted small mb-0">Review roles, membership status, and available actions.</p>
                </div>
                @if ($users->total() > 0)
                    <span class="text-muted small">Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ $users->total() }}</span>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Role</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <th scope="row">{{ ($users->firstItem() ?? 1) + $loop->index }}</th>
                                <td class="fw-semibold">{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>
                                    <span class="badge text-bg-light border text-capitalize">{{ $user->pivot->role ?? 'member' }}</span>
                                </td>
                                <td>
                                    @if ($user->pivot?->is_active)
                                        <span class="badge text-bg-success">Active</span>
                                    @else
                                        <span class="badge text-bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('workspace.users.show', [$workspace, $user]) }}"
                                        class="btn btn-sm btn-outline-primary" title="View user">
                                        <i class="bi bi-eye me-1"></i> View
                                    </a>
                                    <a href="{{ route('workspace.users.edit', [$workspace, $user]) }}"
                                        class="btn btn-sm btn-outline-secondary" title="Edit user">
                                        <i class="bi bi-pencil-square me-1"></i> Edit
                                    </a>
                                    <form action="{{ route('workspace.users.destroy', [$workspace, $user]) }}" method="POST" class="d-inline"
                                        data-delete-confirm>
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove user">
                                            <i class="bi bi-trash me-1"></i> Remove
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-people fs-3 d-block mb-2"></i>
                                    No workspace members match these filters.
                                    <div class="mt-3">
                                        <a href="{{ route('workspace.users.create', $workspace) }}" class="btn btn-sm btn-primary">Invite user</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="card-footer bg-white">{{ $users->withQueryString()->links() }}</div>
            @endif
        </div>
    </div>
@endsection
