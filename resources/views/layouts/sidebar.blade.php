<nav id="sidebar" class="d-flex flex-column flex-shrink-0">
    <div class="p-3 fs-4 fw-bold border-bottom border-secondary text-center d-flex justify-content-center align-items-center"
        style="height: 73px;">
        <span class="sidebar-text">
            {{-- get the app name --}}
            {{ config('app.name', 'IDT') }}
        </span>
        <i class="fa-solid fa-water d-none collapsed-show text-info"></i>
    </div>

    <ul class="nav flex-column mt-2 pb-4">
        <li class="nav-section-title">Overview</li>
        <li class="nav-item">
            <a href="{{ route('dashboard') }}" class="nav-link active">
                <i class="fa-solid fa-house fa-fw"></i> <span>Dashboard</span>
            </a>
        </li>

        {{-- <li class="nav-divider"></li>

        <li class="nav-section-title">Network Audit</li> --}}

        {{-- workspace dropdown menu --}}
        <li class="nav-item has-dropdown">
            <a href="{{ route('dashboard') }}" class="nav-link">
                <i class="fa-solid fa-building fa-fw"></i>
                <span>Workspaces</span>
                <i class="fa-solid fa-chevron-down dropdown-toggle-icon"></i>
            </a>
            <ul class="sidebar-dropdown-menu">
                <li><a href="{{ route('workspace.index') }}">Workspaces</a></li>
                @if (isset($currentWorkspace))
                    <li><a href="{{ route('workspace.users.index', $currentWorkspace) }}">Workspace Users</a></li>
                    <li><a href="{{ route('workspace.users.create', $currentWorkspace) }}">Invite Workspace User</a></li>
                @endif
            </ul>
        </li>

        






        {{-- <li class="nav-item">
            <a href="#" class="nav-link">
                <i class="fa-solid fa-user-slash fa-fw"></i> <span>Banned Accounts</span>
            </a>
        </li> --}}

        {{-- <li class="nav-divider"></li>

        <li class="nav-section-title">Financials</li>
        <li class="nav-item">
            <a href="#" class="nav-link">
                <i class="fa-solid fa-money-bill-transfer fa-fw"></i> <span>Manage Balances</span>
            </a>
        </li> --}}
        {{-- <li class="nav-item">
            <a href="#" class="nav-link">
                <i class="fa-solid fa-wallet fa-fw"></i> <span>Withdrawal Requests</span>
            </a>
        </li> --}}

        {{-- <li class="nav-divider"></li>

        <li class="nav-section-title">System</li>
        <li class="nav-item">
            <a href="#" class="nav-link">
                <i class="fa-solid fa-gear fa-fw"></i> <span>Settings</span>
            </a>
        </li> --}}
    </ul>
</nav>
