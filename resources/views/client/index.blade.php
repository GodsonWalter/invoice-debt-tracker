@extends('layouts.app')

@section('page_title', 'Clients')

@section('content')
<div class="container-fluid py-2">
    <div class="mb-4 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start">
        <div>
            <h2 class="fs-4 fw-bold text-dark mb-1">Clients</h2>
            <p class="text-muted small mb-0">Manage your clients for <strong>{{ $workspace->name }}</strong>.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('workspace.show', $workspace) }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Workspace
            </a>
            <a href="{{ route('clients.create', $workspace) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Add Client
            </a>
        </div>
    </div>

    <div class="card border-light shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white p-4 border-bottom d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0 fs-6">Client List</h5>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <input type="text" id="search-box" class="form-control form-control-sm w-auto" placeholder="Search clients..." style="max-width: 320px;">
            </div>
        </div>

        <div class="card-body p-3 p-md-4">
            @if ($clients->isEmpty())
                <div class="text-center py-5 text-muted">
                    <p class="mb-1">No clients found.</p>
                    <a href="{{ route('clients.create', $workspace) }}" class="btn btn-sm btn-primary">
                        Add your first client
                    </a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col" class="px-4 py-3">SN</th>
                                <th scope="col" class="px-4 py-3">Name</th>
                                <th scope="col" class="px-4 py-3">Email</th>
                                <th scope="col" class="px-4 py-3">Phone</th>
                                <th scope="col" class="px-4 py-3">Address</th>
                                <th scope="col" class="text-end px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($clients as $key => $client)
                                <tr>
                                    <th scope="row" class="px-4 py-3">{{ $key + 1 }}</th>
                                    <td class="px-4 py-3">
                                        <div class="fw-semibold text-dark">{{ $client->name }}</div>
                                        @if($client->notes)
                                            <div class="text-muted small">{{ \Illuminate\Support\Str::limit($client->notes, 50) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div>{{ $client->email ?: '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div>{{ $client->phone ?: '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div>{{ $client->address ?: '—' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <a href="{{ route('clients.show', [$workspace, $client]) }}" class="btn btn-sm btn-outline-primary me-1" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('clients.edit', [$workspace, $client]) }}" class="btn btn-sm btn-outline-secondary me-1" title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <form action="{{ route('clients.destroy', [$workspace, $client]) }}" method="POST" class="d-inline-block" onsubmit="return confirm('Delete this client?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="card-footer d-flex justify-content-end">
                    {{ $clients->links() ?? '' }}
                </div>
            @endif
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('search-box');
        const table = document.querySelector('table.table');
        if (!searchInput || !table) return;

        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));

        searchInput.addEventListener('input', function () {
            const term = this.value.trim().toLowerCase();
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = term === '' || text.includes(term) ? '' : 'none';
            });
        });
    });
</script>
@endpush
@endsection

