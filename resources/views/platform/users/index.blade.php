@extends('layouts.app')

@section('page_title', 'Platform Users')

@section('content')
    <div class="container-fluid py-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="fs-4 fw-bold mb-1">Platform Users</h2>
                <p class="text-muted mb-0">Manage global application accounts, separate from workspace memberships.</p>
            </div>
            <a href="{{ route('platform.users.create') }}" class="btn btn-primary">
                <i class="fa-solid fa-user-plus me-1"></i> Add platform user
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label for="platform-user-search" class="form-label">Search</label>
                        <input id="platform-user-search" name="search" value="{{ $filters['search'] ?? '' }}"
                            class="form-control" maxlength="100" placeholder="Name, email, or phone">
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="platform-user-role" class="form-label">Role</label>
                        <select id="platform-user-role" name="role" class="form-select">
                            <option value="">All roles</option>
                            @foreach (['owner', 'admin', 'manager', 'staff', 'user'] as $role)
                                <option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ ucfirst($role) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="platform-user-status" class="form-label">Status</label>
                        <select id="platform-user-status" name="status" class="form-select">
                            @foreach (['all' => 'Active and inactive', 'active' => 'Active only', 'inactive' => 'Inactive only', 'deleted' => 'Deleted accounts'] as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? 'all') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="platform-user-sort" class="form-label">Sort by</label>
                        <select id="platform-user-sort" name="sort" class="form-select">
                            @foreach (['created_at' => 'Created date', 'name' => 'Name', 'email' => 'Email', 'role' => 'Role', 'last_login_at' => 'Last login'] as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['sort'] ?? 'created_at') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-1">
                        <label for="platform-user-direction" class="form-label">Order</label>
                        <select id="platform-user-direction" name="direction" class="form-select">
                            <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Desc</option>
                            <option value="asc" @selected(($filters['direction'] ?? 'desc') === 'asc')>Asc</option>
                        </select>
                    </div>
                    <div class="col-lg-1 d-flex gap-2">
                        <button class="btn btn-outline-primary w-100" type="submit">Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Platform role</th>
                            <th>Status</th>
                            <th>Workspaces</th>
                            <th>Last login</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $user->name ?: 'Unnamed account' }}</div>
                                    <small class="text-muted">Created {{ $user->created_at?->format('M j, Y') }}</small>
                                </td>
                                <td>{{ $user->email }}</td>
                                <td><span class="badge text-bg-secondary">{{ ucfirst($user->role) }}</span></td>
                                <td>
                                    @if ($user->trashed())
                                        <span class="badge text-bg-danger">Deleted</span>
                                    @elseif ($user->is_active)
                                        <span class="badge text-bg-success">Active</span>
                                    @else
                                        <span class="badge text-bg-warning">Inactive</span>
                                    @endif
                                </td>
                                <td>{{ $user->active_workspaces_count }}</td>
                                <td>{{ $user->last_login_at?->format('M j, Y H:i') ?? 'Never' }}</td>
                                <td class="text-end">
                                    @if ($user->trashed())
                                        @can('manage-platform-user-recovery')
                                            <a href="{{ route('platform.user-recovery.show', $user->id) }}" class="btn btn-sm btn-outline-warning">Recovery</a>
                                        @else
                                            <span class="text-muted small">Recovery restricted</span>
                                        @endcan
                                    @else
                                        @can('manage-platform-user-target', $user)
                                            <a href="{{ route('platform.users.edit', $user) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <form method="POST" action="{{ route('platform.users.destroy', $user) }}" class="d-inline" data-delete-confirm
                                                data-delete-confirm-message="This will soft delete the account and deactivate all workspaces it owns.">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        @else
                                            <span class="text-muted small">View only</span>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No platform users match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white">{{ $users->links() }}</div>
        </div>
    </div>
@endsection
