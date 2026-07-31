@extends('layouts.app')

@section('page_title', 'Platform Workspaces')

@section('content')
    @php
        $statusOptions = [
            'all' => 'Active and inactive',
            'active' => 'Active only',
            'inactive' => 'Inactive only',
            'deleted' => 'Deleted workspaces',
        ];
        $sortOptions = [
            'created_at' => 'Created date',
            'name' => 'Name',
            'slug' => 'Slug',
            'subdomain' => 'Subdomain',
            'is_active' => 'Status',
            'deleted_at' => 'Deleted date',
        ];
    @endphp

    <div class="container-fluid py-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="fs-4 fw-bold mb-1">Platform Workspaces</h1>
                <p class="text-muted mb-0">Manage workspace access, ownership, configuration, and recovery status.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @can('manage-platform-workspace-recovery')
                    <a href="{{ route('platform.recovery.index') }}" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-recycle me-1"></i> Deleted workspaces
                    </a>
                @endcan
                <a href="{{ route('platform.workspaces.create') }}" class="btn btn-primary">
                    <i class="fa-solid fa-building-circle-plus me-1"></i> Add workspace
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label for="platform-workspace-search" class="form-label">Search</label>
                        <input id="platform-workspace-search" name="search" value="{{ $filters['search'] ?? '' }}"
                            class="form-control" maxlength="100" placeholder="Name, slug, subdomain, or owner">
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="platform-workspace-status" class="form-label">Status</label>
                        <select id="platform-workspace-status" name="status" class="form-select">
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? 'all') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="platform-workspace-sort" class="form-label">Sort by</label>
                        <select id="platform-workspace-sort" name="sort" class="form-select">
                            @foreach ($sortOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['sort'] ?? 'created_at') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="platform-workspace-direction" class="form-label">Order</label>
                        <select id="platform-workspace-direction" name="direction" class="form-select">
                            <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Desc</option>
                            <option value="asc" @selected(($filters['direction'] ?? 'desc') === 'asc')>Asc</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <button class="btn btn-outline-primary w-100" type="submit">Filter workspaces</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Workspace</th>
                            <th>Owner</th>
                            <th>Currency</th>
                            <th>Members</th>
                            <th>Clients</th>
                            <th>Invoices</th>
                            <th>Business profile</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($workspaces as $workspace)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $workspace->name }}</div>
                                    <small class="text-muted">{{ $workspace->subdomain ?: $workspace->slug }}</small>
                                </td>
                                <td>
                                    <div>{{ $workspace->owner?->name ?: 'Unknown owner' }}</div>
                                    <small class="text-muted">{{ $workspace->owner?->email ?: 'No email' }}</small>
                                </td>
                                <td>{{ $workspace->currency?->code ?: 'Unspecified' }}</td>
                                <td>{{ number_format($workspace->active_members_count) }}</td>
                                <td>{{ number_format($workspace->clients_count) }}</td>
                                <td>{{ number_format($workspace->invoices_count) }}</td>
                                <td>
                                    @if ($workspace->businessProfile)
                                        <span class="badge text-bg-success">Configured</span>
                                    @else
                                        <span class="badge text-bg-warning">Missing</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($workspace->trashed())
                                        <span class="badge text-bg-danger">Deleted</span>
                                    @elseif ($workspace->is_active)
                                        <span class="badge text-bg-success">Active</span>
                                    @else
                                        <span class="badge text-bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @if ($workspace->trashed())
                                        @can('manage-platform-workspace-recovery')
                                            <a href="{{ route('platform.recovery.show', $workspace->id) }}" class="btn btn-sm btn-outline-warning">Recovery</a>
                                        @else
                                            <span class="text-muted small">Recovery restricted</span>
                                        @endcan
                                    @else
                                        <a href="{{ route('platform.workspaces.edit', $workspace) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        @can('manage-platform-workspace-lifecycle')
                                            <form method="POST" action="{{ route('platform.workspaces.destroy', $workspace) }}" class="d-inline" data-delete-confirm
                                                data-delete-confirm-message="This will soft delete the workspace and move it to platform recovery. It will remain recoverable until the configured permanent-deletion deadline."
                                                data-delete-confirm-name="{{ $workspace->name }}">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="workspace_name" data-delete-confirm-name-input>
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No workspaces match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white">{{ $workspaces->links() }}</div>
        </div>
    </div>
@endsection
