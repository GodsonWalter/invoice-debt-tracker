@extends('layouts.app')

@section('page_title', 'Workspace Users')

@section('content')
    <div class="container-fluid py-2">
        <div class="mb-4 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start">
            <div>
               
                <p class="text-muted small mb-0">Manage members assigned to {{ $workspace->name }}.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('workspace.show', $workspace) }}" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to Workspace
                </a>
                <a href="{{ route('workspace.users.create', $workspace) }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-person-plus"></i> Invite User
                </a>
                
            </div>
        </div>

        <div class="card border-light shadow-sm rounded-4 overflow-hidden">
            <div
                class="card-header bg-white p-4 border-bottom d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0 fs-6">Workspace Users</h5>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <input type="text" id="search-box" class="form-control form-control-sm w-auto"
                        placeholder="Search workspace users..." style="max-width: 300px;">
                  
                </div>
            </div>
            <div class="card-body p-3 p-md-4">
                @if ($users->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <p class="mb-1">No users have been added to this workspace yet.</p>
                        <a href="{{ route('workspace.users.create', $workspace) }}" class="btn btn-sm btn-primary">Invite a
                            user</a>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col" class="px-4 py-3 sortable cursor-pointer" data-column="sn"
                                        style="cursor: pointer;">
                                        SN <i class="bi bi-chevron-expand ms-1" style="font-size: 0.75rem;"></i></th>
                                    <th class="px-4 py-3 sortable cursor-pointer" data-column="name" style="cursor: pointer;">
                                        Name <i class="bi bi-chevron-expand ms-1" style="font-size: 0.75rem;"></i>
                                    </th>
                                    <th class="px-4 py-3 sortable cursor-pointer" data-column="email" style="cursor: pointer;">
                                        Email <i class="bi bi-chevron-expand ms-1" style="font-size: 0.75rem;"></i>
                                    </th>
                                    <th class="px-4 py-3 sortable cursor-pointer" data-column="role" style="cursor: pointer;">
                                        Role <i class="bi bi-chevron-expand ms-1" style="font-size: 0.75rem;"></i>
                                    </th>
                                    <th class="px-4 py-3 sortable cursor-pointer" data-column="status" style="cursor:pointer;">
                                        Status <i class="bi bi-chevron-expand ms-1" style="font-size: 0.75rem;"></i>
                                    </th>
                                    {{-- <th class="px-4 py-3 sortable cursor-pointer" data-column="verified"
                                        style="cursor: pointer;">
                                        Verified <i class="bi bi-chevron-expand ms-1" style="font-size: 0.75rem;"></i>
                                    </th> --}}
                                    <th class="text-end px-4 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $key => $user)
                                    <tr>
                                        <th scope="row" class="px-4 py-3">{{ $key + 1 }}</th>
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
                                        {{-- <td>
                                            @if ($user->hasVerifiedEmail())
                                                <span class="badge bg-success">Verified</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            @endif
                                        </td> --}}
                                        <td class="text-end">
                                            <a href="{{ route('workspace.users.show', [$workspace, $user]) }}"
                                                class="btn btn-sm btn-outline-primary me-1" title="View user">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ route('workspace.users.edit', [$workspace, $user]) }}"
                                                class="btn btn-sm btn-outline-secondary me-1" title="Edit user">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <form action="{{ route('workspace.users.destroy', [$workspace, $user]) }}" method="POST"
                                                class="d-inline-block" data-delete-confirm>
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove user">
                                                    <i class="bi bi-trash"></i>
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
                        {{ $users->links() }}
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
