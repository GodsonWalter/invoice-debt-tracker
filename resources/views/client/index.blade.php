@extends('layouts.app')

@section('page_title', 'Clients')

@section('content')
    @php
        $sortOptions = [
            'created_at' => 'Created date',
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
        ];
    @endphp

    <div class="container-fluid py-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="fs-4 fw-bold mb-1">Clients</h1>
                <p class="text-muted mb-0">Manage clients for <strong>{{ $workspace->name }}</strong>.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('workspace.show', $workspace) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Workspace
                </a>
                <a href="{{ route('clients.create', $workspace) }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add client
                </a>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('clients.index', $workspace) }}" class="row g-3 align-items-end">
                    <div class="col-lg-5">
                        <label for="client-search" class="form-label">Search</label>
                        <input id="client-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}"
                            class="form-control" maxlength="100" placeholder="Name, email, phone, or address">
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label for="client-sort" class="form-label">Sort by</label>
                        <select id="client-sort" name="sort" class="form-select">
                            @foreach ($sortOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['sort'] ?? 'created_at') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label for="client-direction" class="form-label">Order</label>
                        <select id="client-direction" name="direction" class="form-select">
                            <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Desc</option>
                            <option value="asc" @selected(($filters['direction'] ?? 'desc') === 'asc')>Asc</option>
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2 d-flex gap-2">
                        <button class="btn btn-outline-primary flex-grow-1" type="submit">
                            <i class="bi bi-funnel me-1"></i> Filter
                        </button>
                        @if (($filters['search'] ?? '') || ($filters['sort'] ?? 'created_at') !== 'created_at' || ($filters['direction'] ?? 'desc') !== 'desc')
                            <a href="{{ route('clients.index', $workspace) }}" class="btn btn-outline-secondary">Reset</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 px-4 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h2 class="h6 fw-bold mb-1">Client list</h2>
                    <p class="text-muted small mb-0">Review customer contact information and account actions.</p>
                </div>
                @if ($clients->total() > 0)
                    <span class="text-muted small">Showing {{ $clients->firstItem() }}–{{ $clients->lastItem() }} of {{ $clients->total() }}</span>
                @endif
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Phone</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($clients as $client)
                            <tr>
                                <th scope="row">{{ ($clients->firstItem() ?? 1) + $loop->index }}</th>
                                <td class="fw-semibold">{{ $client->name }}</td>
                                <td>{{ $client->email ?: '—' }}</td>
                                <td>{{ $client->phone ?: '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('clients.show', [$workspace, $client]) }}"
                                        class="btn btn-sm btn-outline-primary" title="View client">
                                        <i class="bi bi-eye me-1"></i> View
                                    </a>
                                    <a href="{{ route('clients.edit', [$workspace, $client]) }}"
                                        class="btn btn-sm btn-outline-secondary" title="Edit client">
                                        <i class="bi bi-pencil-square me-1"></i> Edit
                                    </a>
                                    <form action="{{ route('clients.destroy', [$workspace, $client]) }}" method="POST" class="d-inline"
                                        data-delete-confirm>
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete client">
                                            <i class="bi bi-trash me-1"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="bi bi-people fs-3 d-block mb-2"></i>
                                    No clients match these filters.
                                    <div class="mt-3">
                                        <a href="{{ route('clients.create', $workspace) }}" class="btn btn-sm btn-primary">Add client</a>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($clients->hasPages())
                <div class="card-footer bg-white">{{ $clients->withQueryString()->links() }}</div>
            @endif
        </div>
    </div>
@endsection
