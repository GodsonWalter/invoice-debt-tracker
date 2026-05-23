<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> {{ config('app.name', 'IDT') }} || @yield('page_title', '')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    @stack('styles')
    <style>
        body {
            background-color: #f8f9fa;
            overflow-x: hidden;
        }

        #wrapper {
            transition: all 0.3s;
        }

        /* Sidebar Styles */
        #sidebar {
            min-width: 250px;
            max-width: 250px;
            height: 100vh;
            background: #0f172a;
            color: #fff;
            transition: all 0.3s;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: #475569 #0f172a;
        }

        #sidebar::-webkit-scrollbar {
            width: 6px;
        }

        #sidebar::-webkit-scrollbar-track {
            background: #0f172a;
        }

        #sidebar::-webkit-scrollbar-thumb {
            background-color: #475569;
            border-radius: 10px;
        }

        #sidebar.collapsed {
            min-width: 80px;
            max-width: 80px;
        }

        /* Links */
        #sidebar .nav-link {
            color: #94a3b8;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            transition: all 0.2s;
            cursor: pointer;
            text-decoration: none;
        }

        #sidebar .nav-link:hover,
        #sidebar .nav-link.active {
            color: #fff;
            background: #1e293b;
        }

        #sidebar .nav-link i {
            font-size: 1.25rem;
            margin-right: 15px;
        }

        /* Sidebar Collapse Logic */
        #sidebar.collapsed .nav-link span {
            display: none;
        }

        #sidebar.collapsed .nav-link i {
            margin-right: 0;
            margin: auto;
        }

        #sidebar.collapsed .sidebar-text {
            display: none;
        }

        #sidebar.collapsed .collapsed-show {
            display: block !important;
            margin: auto;
            font-size: 1.5rem;
        }

        /* Navigation Demarcation Styles */
        #sidebar .nav-section-title {
            color: #64748b;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 15px 20px 5px;
            font-weight: 700;
        }

        #sidebar .nav-divider {
            border-top: 1px solid #1e293b;
            margin: 5px 20px;
        }

        #sidebar.collapsed .nav-section-title,
        #sidebar.collapsed .nav-divider {
            display: none;
        }

        /* --- Sidebar Dropdown Styles --- */
        .sidebar-dropdown-menu {
            display: none;
            background: #0b1120;
            list-style: none;
            padding: 0;
            margin: 0;
        }

        /* When the parent <li> has the class .open, show the nested menu */
        .nav-item.open .sidebar-dropdown-menu {
            display: block;
        }

        .sidebar-dropdown-menu li a {
            color: #94a3b8;
            padding: 10px 20px 10px 50px;
            /* Extra padding on the left to indent */
            display: block;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s;
        }

        .sidebar-dropdown-menu li a:hover {
            color: #fff;
            background: #1e293b;
        }

        /* The chevron arrow indicator */
        .dropdown-toggle-icon {
            margin-left: auto;
            transition: transform 0.3s;
            font-size: 0.8rem !important;
            margin-right: 0 !important;
        }

        .nav-item.open .dropdown-toggle-icon {
            transform: rotate(180deg);
        }

        /* Hide the chevron when the sidebar is collapsed */
        #sidebar.collapsed .dropdown-toggle-icon {
            display: none;
        }

        /* When sidebar is collapsed, hide dropdown menus even if 'open' */
        #sidebar.collapsed .sidebar-dropdown-menu {
            display: none !important;
        }

        /* -------------------------------- */

        /* Content Styles */
        #content {
            margin-left: 250px;
            width: calc(100% - 250px);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: all 0.3s;
        }

        #sidebar.collapsed+#content {
            margin-left: 80px;
            width: calc(100% - 80px);
        }

        .navbar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        /* --- Mobile Responsiveness --- */
        @media (max-width: 768px) {
            #sidebar {
                transform: translateX(-100%);
            }

            #sidebar.mobile-open {
                transform: translateX(0);
                box-shadow: 4px 0 15px rgba(0, 0, 0, 0.2);
            }

            #content {
                margin-left: 0 !important;
                width: 100% !important;
            }

            #sidebar.collapsed+#content {
                margin-left: 0 !important;
                width: 100% !important;
            }

            .mobile-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background: rgba(0, 0, 0, 0.4);
                z-index: 999;
                display: none;
            }

            .mobile-backdrop.show {
                display: block;
            }

            /* Make navbar layout cleaner on small screens */
            .navbar h5.mb-0 {
                font-size: 1.1rem;
            }
        }
    </style>
</head>

<body>
    <div class="mobile-backdrop" id="sidebarBackdrop"></div>
    <div id="wrapper">

        <aside>
            {{-- include sidebar --}}
            @include('layouts.sidebar')
        </aside>


        <div id="content">
            <nav class="navbar navbar-expand-lg px-2 px-md-4 py-2 py-md-3 border-bottom bg-white">
                <div class="container-fluid px-1 px-md-2">
                    <div class="d-flex align-items-center">
                        <button class="btn btn-light border-0 me-2 shadow-sm" id="sidebarToggle">
                            <i class="fa-solid fa-bars fs-5"></i>
                        </button>
                        {{-- back button --}}
                        <a href="{{ url()->previous() ?: route('dashboard') }}"
                            class="btn btn-light border-0 mx-2 shadow-sm d-none d-md-inline-block" title="Go Back">
                            <i class="fa-solid fa-arrow-left fs-5"></i>
                        </a>
                        <h5 class="mb-0 fw-bold text-dark text-truncate" style="max-width: 150px;">
                            @yield('page_title', 'Dashboard')
                        </h5>
                    </div>

                    <div class="d-flex align-items-center">
                        <button class="btn btn-light border-0 me-2 shadow-sm d-none d-sm-inline-block"
                            onclick="toggleFullScreen()" title="Full Screen">
                            <i class="fa-solid fa-expand"></i>
                        </button>

                        <div class="vr mx-2 mx-sm-3 text-muted d-none d-sm-block"
                            style="height: 25px; align-self: center;"></div>

                        <div class="dropdown">
                            <a class="d-flex align-items-center text-decoration-none" href="#" role="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <span
                                    class="me-2 fw-medium text-dark d-none d-sm-inline-block">{{ explode(' ', (Auth::user()->name ?? 'Admin'))[0] }}</span>
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center shadow-sm"
                                    style="width: 38px; height: 38px; font-size: 1.1rem;">
                                    {{ substr(Auth::user()->name ?? 'A', 0, 1) }}
                                </div>
                            </a>

                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-3 rounded-4"
                                style="min-width: 220px;">
                                <li>
                                    <div class="px-4 py-3 border-bottom mb-2 bg-light rounded-top-4">
                                        <p class="mb-0 fw-bold text-dark">{{ Auth::user()->name ?? 'Admin User' }}</p>
                                        <p class="mb-0 text-muted small">{{ Auth::user()->email ?? 'admin@waltech.sbs'
                                            }}</p>
                                        <p class="mb-0 text-muted small">{{ Auth::user()->role ?? 'fetch role' }}</p>
                                    </div>
                                </li>

                                <li>
                                    <a class="dropdown-item d-flex align-items-center py-2 px-4"
                                        href="{{ route('profile.edit') ?? '#' }}">
                                        <i class="fa-solid fa-user fa-fw text-primary me-3 fs-5"></i> My Profile
                                    </a>
                                </li>
                                <li>
                                    <h6 class="dropdown-header px-4 py-2 mb-0">Switch Workspace</h6>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center py-2 px-4"
                                        href="{{ config('app.url') ?? '#' }}dashboard" title="Default workspace">
                                        <i class="fa-solid fa-house fa-fw text-secondary me-3 fs-5"></i>
                                        Default ({{ config('app.base_domain') }})
                                    </a>
                                </li>
                                <li>
                                    <hr class="dropdown-divider mx-3 my-1">
                                </li>
                                @php
                                    $switchableWorkspaces = Auth::user()->workspaces()
                                        ->whereNotNull('subdomain')
                                        ->where('workspaces.is_active', true)
                                        ->get();
                                @endphp
                                @forelse ($switchableWorkspaces as $switchWorkspace)
                                    <li>
                                        <a class="dropdown-item d-flex align-items-center py-2 px-4"
                                            href="{{ route('workspace.switch', ['workspace' => $switchWorkspace->subdomain]) }}">
                                            <i class="fa-solid fa-arrow-right-to-bracket fa-fw text-success me-3 fs-5"></i>
                                            {{ $switchWorkspace->name }}
                                        </a>
                                    </li>
                                @empty
                                    <li>
                                        <span class="dropdown-item text-muted py-2 px-4">No active workspaces
                                            available</span>
                                    </li>
                                @endforelse
                                <li>
                                    <div class="dropdown-divider mx-3"></div>
                                </li>
                                <li>
                                    <a class="dropdown-item d-flex align-items-center py-2 px-4" href="#">
                                        <i class="fa-solid fa-gear fa-fw text-secondary me-3 fs-5"></i> Settings
                                    </a>
                                </li>

                                <li>
                                    <hr class="dropdown-divider mx-3">
                                </li>

                                <li>
                                    <form method="POST" action="{{ route('logout') ?? '#' }}">
                                        @csrf
                                        <button type="submit"
                                            class="dropdown-item d-flex align-items-center py-2 px-4 text-danger">
                                            <i class="fa-solid fa-right-from-bracket fa-fw me-3 fs-5"></i> Logout
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>




            <main class="container-fluid p-2 p-md-4 flex-grow-1 overflow-y-auto">                
                <div class="m-0">                 
                    <h2 class=" fw-bold text-muted mb-1">{{ $currentWorkspace->name ?? '' }}</h2>
                </div>
                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Sidebar Toggle Logic
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('sidebarToggle');
        const backdrop = document.getElementById('sidebarBackdrop');

        function toggleSidebar() {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('mobile-open');
                backdrop.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                // If sidebar collapses, forcefully close any open dropdowns to prevent visual bugs
                if (sidebar.classList.contains('collapsed')) {
                    document.querySelectorAll('.has-dropdown.open').forEach(el => {
                        el.classList.remove('open');
                    });
                }
            }
        }

        toggleBtn.addEventListener('click', toggleSidebar);

        backdrop.addEventListener('click', () => {
            sidebar.classList.remove('mobile-open');
            backdrop.classList.remove('show');
        });

        // --- Sidebar Dropdown Menu Logic ---
        const dropdownToggles = document.querySelectorAll('.has-dropdown > .nav-link');

        dropdownToggles.forEach(toggle => {
            toggle.addEventListener('click', function (e) {
                e.preventDefault();

                // Only allow opening if the sidebar is NOT collapsed
                if (!sidebar.classList.contains('collapsed')) {
                    const parentItem = this.parentElement;

                    // Toggle the 'open' class on the parent <li>
                    parentItem.classList.toggle('open');
                }
            });
        });
        // ------------------------------------

        // Fullscreen Logic
        function toggleFullScreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => {
                    console.log(`Error attempting to enable fullscreen: ${err.message}`);
                });
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        }
    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
    @stack('scripts')

    @foreach (['success', 'error', 'warning', 'info'] as $msg)
        @if (session()->has($msg))
            <script>
                toastr.{{ $msg }}("{{ session($msg) }}");
            </script>
        @endif

    @endforeach

    {{-- link table-search.js --}}

    <script>
        // Reusable table search function
        function setupTableSearch(searchInputId, tableSelector) {
            const searchInput = document.getElementById(searchInputId);
            const tableBody = document.querySelector(tableSelector + ' tbody');
            const rows = Array.from(tableBody.querySelectorAll('tr'));

            searchInput.addEventListener('input', function () {
                const searchTerm = this.value.toLowerCase();

                rows.forEach(row => {
                    // Get all cells except the last one (Actions column)
                    const cells = Array.from(row.cells).slice(0, -1);
                    const rowText = cells.map(cell => cell.textContent.toLowerCase()).join(' ');

                    if (rowText.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        // Reusable table sorting function
        function setupTableSorting(tableSelector) {
            const table = document.querySelector(tableSelector);
            if (!table) {
                return;
            }

            const tableBody = table.querySelector('tbody');
            const sortableHeaders = Array.from(table.querySelectorAll('.sortable'));

            sortableHeaders.forEach(header => {
                header.addEventListener('click', function () {
                    const currentSort = this.dataset.sort || 'asc';
                    const newSort = currentSort === 'asc' ? 'desc' : 'asc';
                    const columnIndex = this.cellIndex;

                    sortableHeaders.forEach(h => {
                        if (h !== this) {
                            delete h.dataset.sort;
                            const hIcon = h.querySelector('i');
                            if (hIcon) {
                                hIcon.className = 'fa-solid fa-sort';
                            }
                        }
                    });

                    this.dataset.sort = newSort;
                    const icon = this.querySelector('i');
                    if (icon) {
                        icon.className = newSort === 'asc' ? 'fa-solid fa-sort-up' : 'fa-solid fa-sort-down';
                    }

                    const rows = Array.from(tableBody.querySelectorAll('tr'));

                    const sortedRows = rows.sort((a, b) => {
                        const aValue = getCellText(a, columnIndex);
                        const bValue = getCellText(b, columnIndex);
                        const aNumber = parseFloat(aValue);
                        const bNumber = parseFloat(bValue);
                        const isNumeric = !Number.isNaN(aNumber) && !Number.isNaN(bNumber);

                        const first = isNumeric ? aNumber : aValue;
                        const second = isNumeric ? bNumber : bValue;

                        if (first === second) {
                            return 0;
                        }

                        if (newSort === 'asc') {
                            return first > second ? 1 : -1;
                        }

                        return first < second ? 1 : -1;
                    });

                    sortedRows.forEach(row => tableBody.appendChild(row));
                });
            });

            function getCellText(row, index) {
                const cell = row.cells[index];
                return cell ? cell.textContent.trim().toLowerCase() : '';
            }
        }

    </script>

    {{--
    <script>
        @if (session('success'))
            toastr.success("{{ session('success') }}");
        @endif

        @if (session('error'))
            toastr.error("{{ session('error') }}");
        @endif

        @if (session('info'))
            toastr.info("{{ session('info') }}");
        @endif

        @if (session('warning'))
            toastr.warning("{{ session('warning') }}");
        @endif
    </script> --}}
</body>

</html>