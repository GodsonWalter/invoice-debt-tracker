@extends('layouts.app')

@section('page_title', 'Workspace Management')

@section('content')
    @php
        $activeWorkspace = $currentWorkspace ?? null;
        $statusOptions = [
            'all' => 'Active and inactive',
            'active' => 'Active only',
            'inactive' => 'Inactive only',
        ];
        $sortOptions = [
            'created_at' => 'Created date',
            'name' => 'Name',
            'slug' => 'Slug',
            'subdomain' => 'Subdomain',
            'role' => 'Role',
            'is_active' => 'Status',
        ];
    @endphp

    <div class="container-fluid py-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="fs-4 fw-bold mb-1">Workspaces</h1>
                <p class="text-muted mb-0">Manage your workspaces and switch between active workspaces.</p>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <span class="badge text-bg-light border">
                        Current: {{ $activeWorkspace?->name ?? 'None selected' }}
                    </span>
                    <span class="badge text-bg-light border">
                        {{ number_format($workspaces->total()) }} total workspaces
                    </span>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <div>
                    <label for="workspace-switcher" class="visually-hidden">Switch workspace</label>
                    <select id="workspace-switcher" class="form-select"
                        onchange="if (this.value) window.location.href = this.value">
                        <option value="">Switch workspace</option>
                        @foreach ($activeWorkSpaces as $activeWorkSpace)
                            <option value="{{ route('workspace.switch', ['workspace' => $activeWorkSpace->subdomain]) }}">
                                {{ $activeWorkSpace->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <a href="{{ route('workspace.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add workspace
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
                <form method="GET" action="{{ route('workspace.index') }}" class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label for="workspace-search" class="form-label">Search</label>
                        <input id="workspace-search" type="search" name="search"
                            value="{{ $filters['search'] ?? '' }}" class="form-control" maxlength="100"
                            placeholder="Name, slug, or subdomain">
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="workspace-status" class="form-label">Status</label>
                        <select id="workspace-status" name="status" class="form-select">
                            @foreach ($statusOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? 'all') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="workspace-sort" class="form-label">Sort by</label>
                        <select id="workspace-sort" name="sort" class="form-select">
                            @foreach ($sortOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['sort'] ?? 'created_at') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="workspace-direction" class="form-label">Order</label>
                        <select id="workspace-direction" name="direction" class="form-select">
                            <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Desc</option>
                            <option value="asc" @selected(($filters['direction'] ?? 'desc') === 'asc')>Asc</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2 d-flex gap-2">
                        <button class="btn btn-outline-primary flex-grow-1" type="submit">
                            <i class="bi bi-funnel me-1"></i> Filter
                        </button>
                        @if (($filters['search'] ?? '') || ($filters['status'] ?? 'all') !== 'all' || ($filters['sort'] ?? 'created_at') !== 'created_at' || ($filters['direction'] ?? 'desc') !== 'desc')
                            <a href="{{ route('workspace.index') }}" class="btn btn-outline-secondary">Reset</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 px-4 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h2 class="h6 fw-bold mb-1">Workspace list</h2>
                    <p class="text-muted small mb-0">View workspace membership, status, and available actions.</p>
                </div>
                @if ($workspaces->total() > 0)
                    <span class="text-muted small">Showing {{ $workspaces->firstItem() }}–{{ $workspaces->lastItem() }} of {{ $workspaces->total() }}</span>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Workspace</th>
                            <th scope="col">Subdomain</th>
                            <th scope="col">Role</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($workspaces as $workspace)
                            <tr>
                                <th scope="row">{{ ($workspaces->firstItem() ?? 1) + $loop->index }}</th>
                                <td>
                                    <div class="fw-semibold">{{ $workspace->name }}</div>
                                    @if (optional($workspace->pivot)->role === 'owner')
                                        <span class="badge text-bg-info">Owned by you</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-muted">{{ $workspace->subdomain ?: 'Not configured' }}</span>
                                </td>
                                <td>
                                    <span class="badge text-bg-light border text-capitalize">
                                        {{ $workspace->pivot->role ?? 'Member' }}
                                    </span>
                                </td>
                                <td>
                                    @if ($workspace->is_active)
                                        <span class="badge text-bg-success">Active</span>
                                    @else
                                        <span class="badge text-bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @if ($activeWorkspace instanceof \App\Models\Workspace && $activeWorkspace->is($workspace))
                                        @if ($workspace->canBeManagedBy(Auth::user()))
                                            <a href="{{ route('workspace.show', $workspace->id, false) }}"
                                                class="btn btn-sm btn-outline-primary" title="View workspace">
                                                <i class="bi bi-eye me-1"></i> View
                                            </a>
                                            <a href="{{ route('workspace.edit', $workspace->id, false) }}"
                                                class="btn btn-sm btn-outline-secondary" title="Edit workspace">
                                                <i class="bi bi-pencil-square me-1"></i> Edit
                                            </a>
                                            @if ($workspace->owner_id === Auth::id())
                                                <form action="{{ route('workspace.destroy', $workspace->id, false) }}" method="POST"
                                                    class="d-inline" data-delete-confirm
                                                    data-delete-confirm-name="{{ $workspace->name }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="workspace_name" data-delete-confirm-name-input>
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete workspace">
                                                        <i class="bi bi-trash me-1"></i> Delete
                                                    </button>
                                                </form>
                                            @endif
                                        @else
                                            <form action="{{ route('workspace.exit', $workspace->id, false) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Exit workspace">
                                                    <i class="bi bi-box-arrow-right me-1"></i> Exit
                                                </button>
                                            </form>
                                        @endif
                                    @elseif ($workspace->is_active && filled($workspace->subdomain))
                                        <a href="{{ route('workspace.switch', ['workspace' => $workspace->subdomain]) }}"
                                            class="btn btn-sm btn-outline-primary" title="Switch to workspace">
                                            <i class="bi bi-arrow-repeat me-1"></i> Switch
                                        </a>
                                    @else
                                        <span class="text-muted small">Unavailable</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-building fs-3 d-block mb-2"></i>
                                    No workspaces match these filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($workspaces->hasPages())
                <div class="card-footer bg-white">{{ $workspaces->withQueryString()->links() }}</div>
            @endif
        </div>
    </div>
@endsection
