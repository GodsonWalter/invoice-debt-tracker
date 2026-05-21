@extends('layouts.app')
{{-- page title --}}
@section('page_title', 'Workspace Management')
@section('content')

    <div class="container-fluid py-2">
        <div class="mb-4">
            <div class="col-12 my-3 bg-secondary bg-opacity-10 p-4 rounded-4 border border-secondary shadow-sm">
                <h2 class="fs-4 fw-bold text-dark mb-1">
                    <i class="bi bi-diagram-3-fill me-2"></i> Workspaces <br>
                </h2>

                Current workspace: <span
                    class="badge bg-secondary">{{ $currentWorkspace ? $currentWorkspace->name : 'None' }}</span>
                total workspaces: <span class="badge bg-secondary">{{ $workspaces->total() }}</span>

            </div>


            <p class="text-muted small mb-0">Manage and view all workspaces in the system.</p>

            {{-- Create class button with plus icon--}}
            <a href="{{ route('workspace.create') }}" class="btn btn-sm btn-primary mt-2">
                <i class="bi bi-plus-lg"></i> Create New Workspace
            </a>

            {{-- switch to workspace (subdomain based) --}}
            <select class="form-select form-select-sm d-inline-block w-auto ms-2 btn btn-secondary"
                onchange="if (this.value) window.location.href = this.value">

                <option value="">Switch Workspace</option>

                @foreach ($activeWorkSpaces as $activeWorkSpace)
                    <option value="{{ route('workspace.switch', ['workspace' => $activeWorkSpace->subdomain]) }}">
                        {{ $activeWorkSpace->name }}
                    </option>
                @endforeach

            </select>
        </div>

        <div class="card border border-light shadow-sm rounded-4 overflow-hidden">
            <div
                class="card-header bg-white p-4 border-bottom d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0 fs-6">Workspace List</h5>
                <input type="text" id="search-box" class="form-control form-control-sm w-auto"
                    placeholder="Search workspaces..." style="max-width: 300px;">
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th scope="col" class="px-4 py-3 sortable cursor-pointer" data-column="sn" style="cursor: pointer;">SN
                                    <i class="bi bi-chevron-expand ms-1" style="font-size: 0.75rem;"></i></th>
                                <th scope="col" class="px-4 py-3 sortable cursor-pointer" data-column="name"
                                    style="cursor: pointer;">
                                    Name <i class="bi bi-chevron-expand ms-1" style="font-size: 0.75rem;"></i>
                                </th>
                                <th scope="col" class="px-4 py-3 sortable cursor-pointer" data-column="subdomain"
                                    style="cursor: pointer;">
                                    Subdomain <i class="bi bi-chevron-expand ms-1" style="font-size: 0.75rem;"></i>
                                </th>
                                <th scope="col" class="px-4 py-3 sortable cursor-pointer" data-column="role"
                                    style="cursor: pointer;">
                                    Role <i class="bi bi-chevron-expand ms-1" style="font-size: 0.75rem;"></i>
                                </th>
                                <th scope="col" class="px-4 py-3 sortable cursor-pointer" data-column="status"
                                    style="cursor:pointer">
                                    Status <i class="bi bi-chevron-expand ms-1" style="font-size: 0.75rem;"></i>
                                </th>
                                <th scope="col" class="px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($workspaces->isEmpty())
                                <tr>
                                    <td colspan="6" class="text-center py-4">No workspaces found. Create a new workspace to get
                                        started.</td>
                                </tr>
                            @endif
                            @foreach ($workspaces as $key => $workspace)
                                <tr>
                                    <th scope="row" class="px-4 py-3">{{ $key + 1 }}</th>
                                    <td class="px-4 py-3">
                                        {{ $workspace->name }}
                                    </td>
                                    <td class="px-4 py-3">{{ $workspace->subdomain }}</td>
                                    <td class="px-4 py-3">
                                        {{ $workspace->pivot->role ?? 'Member' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="badge rounded-pill {{ $workspace->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">

                                            {{ $workspace->is_active ? 'Active' : 'Inactive' }}

                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('workspace.show', $workspace->id) }}"
                                            class="btn btn-sm btn-secondary me-1">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <a href="{{ route('workspace.edit', $workspace->id) }}"
                                            class="btn btn-sm btn-info me-1">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                        <form action="{{ route('workspace.destroy', $workspace->id) }}" method="POST"
                                            class="d-inline-block"
                                            onsubmit="return confirm('Are you sure you want to delete this workspace?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-danger">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>


                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{-- pagination links --}}
                <div class="card-footer d-flex justify-content-end">
                    {{ $workspaces->links() }}
                </div>
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