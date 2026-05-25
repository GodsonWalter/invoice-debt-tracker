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
            <div
                class="card-header bg-white p-4 border-bottom d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0 fs-6">Client List</h5>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <input type="text" id="search-box" class="form-control form-control-sm w-auto"
                        placeholder="Search clients..." style="max-width: 320px;">
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
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th scope="col" class="px-4 py-3 sortable cursor-pointer" data-column="sn"
                                        style="cursor: pointer;">SN
                                        <i class="bi bi-chevron-expand ms-1" style="font-size: 0.75rem;"></i>
                                    </th>
                                     <th scope="col" class="px-4 py-3 sortable cursor-pointer" data-column="name"
                                    style="cursor: pointer;">
                                    Name <i class="bi bi-chevron-expand ms-1" style="font-size: 0.75rem;"></i>
                                    
                                </th>
                                
                                <th scope="col" class="px-4 py-3 sortable cursor-pointer" data-column="phone"
                                    style="cursor: pointer;">
                                    Phone <i class="bi bi-chevron-expand ms-1" style="font-size: 0.75rem;"></i>
                                </th>
                                    <th scope="col" class="text-end px-4 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($clients as $key => $client)
                                    <tr>
                                        <th scope="row">{{ $key + 1 }}</th>
                                        <td>
                                            {{ $client->name }}
                                        </td>                                       
                                        <td>
                                            {{ $client->phone ?: '—' }}
                                        </td>

                                       <td class="px-4 py-3 text-end">
                                            <a href="{{ route('clients.show', [$workspace, $client]) }}"
                                                class="btn btn-sm btn-outline-primary me-1" title="View">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ route('clients.edit', [$workspace, $client]) }}"
                                                class="btn btn-sm btn-outline-secondary me-1" title="Edit">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <form action="{{ route('clients.destroy', [$workspace, $client]) }}" method="POST"
                                                class="d-inline-block" onsubmit="return confirm('Delete this client?');">
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
                setupTableSearch('search-box', 'table');
                setupTableSorting('table');
            });

            function setupTableSearch(searchInputId, tableSelector) {
                const searchInput = document.getElementById(searchInputId);
                const table = document.querySelector(tableSelector);

                if (!searchInput || !table) {
                    return;
                }

                const tbody = table.querySelector('tbody');

                searchInput.addEventListener('input', function () {
                    const searchTerm = this.value.trim().toLowerCase();
                    const rows = Array.from(tbody.querySelectorAll('tr'));

                    rows.forEach((row) => {
                        const cells = Array.from(row.querySelectorAll('td'));
                        const rowText = cells
                            .map((cell) => cell.textContent.toLowerCase())
                            .join(' ');

                        row.style.display = searchTerm === '' || rowText.includes(searchTerm) ? '' : 'none';
                    });
                });
            }

            function setupTableSorting(tableSelector) {
                const table = document.querySelector(tableSelector);

                if (!table) {
                    return;
                }

                const headers = Array.from(table.querySelectorAll('th.sortable'));
                let currentHeader = null;
                let ascending = true;

                headers.forEach((header) => {
                    header.addEventListener('click', () => {
                        const columnIndex = Array.prototype.indexOf.call(header.parentNode.children, header) + 1;

                        if (currentHeader === header) {
                            ascending = !ascending;
                        } else {
                            currentHeader = header;
                            ascending = true;
                        }

                        sortTable(table, columnIndex, ascending);
                        updateSortIndicators(headers, currentHeader, ascending);
                    });
                });
            }

            function sortTable(table, columnIndex, ascending) {
                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));

                rows.sort((a, b) => {
                    const aCell = a.querySelector(`td:nth-child(${columnIndex})`);
                    const bCell = b.querySelector(`td:nth-child(${columnIndex})`);
                    const aValue = aCell ? aCell.textContent.trim() : '';
                    const bValue = bCell ? bCell.textContent.trim() : '';

                    const comparison = aValue.localeCompare(bValue, undefined, { numeric: true, sensitivity: 'base' });
                    return ascending ? comparison : -comparison;
                });

                rows.forEach((row) => tbody.appendChild(row));
            }

            function updateSortIndicators(headers, activeHeader, ascending) {
                headers.forEach((header) => {
                    const icon = header.querySelector('i');

                    if (!icon) {
                        return;
                    }

                    if (header === activeHeader) {
                        icon.className = ascending ? 'bi bi-sort-up ms-1' : 'bi bi-sort-down ms-1';
                    } else {
                        icon.className = 'bi bi-chevron-expand ms-1';
                    }
                });
            }
        </script>
    @endpush
@endsection