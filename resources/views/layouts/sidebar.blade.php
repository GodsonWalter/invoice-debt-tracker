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
        <li class="nav-item">
            <a href="{{ route('dashboard') }}" class="nav-link active">
                <i class="fa-solid fa-house fa-fw"></i> <span>Dashboard</span>
            </a>
        </li>

        {{-- workspaces --}}
        <li class="nav-item">
            <a href="{{ route('workspace.index') }}" class="nav-link">
                <i class="fa-solid fa-building fa-fw"></i> <span>Workspaces</span>
            </a>
        </li>

        @if (isset($currentWorkspace))             

        <li class="nav-divider"></li>
        <li class="nav-section-title">{{ $currentWorkspace->name ?? ''}}</li>

        {{-- business profiles --}}
        <li class="nav-item">
            <a href="{{ route('business-profile.index') }}" class="nav-link">
                <i class="fa-solid fa-briefcase fa-fw"></i> <span>Business Profile</span>
            </a>
        </li>

        {{-- clients --}}
        <li class="nav-item">
            <a href="{{ route('clients.index', $currentWorkspace) }}" class="nav-link">
                <i class="fa-solid fa-user-tie fa-fw"></i> <span>Clients</span>
            </a>
        </li>

        {{-- users --}}
        <li class="nav-item">
            <a href="{{ route('workspace.users.index', $currentWorkspace) }}" class="nav-link">
                <i class="fa-solid fa-users fa-fw"></i> <span>Users</span>
            </a>
        </li>

        @endif



        


        {{-- drop down example --}}
        {{-- <li class="nav-item has-dropdown">
            <a href="#" class="nav-link">
                <i class="fa-solid fa-users fa-fw"></i>
                <span>Users</span>
                <i class="fa-solid fa-chevron-down dropdown-toggle-icon"></i>
            </a>
            <ul class="sidebar-dropdown-menu">
                <li><a href="#">All Users</a></li>
                <li><a href="#">Active Users</a></li>
                <li><a href="#">Banned Users</a></li>
            </ul>
        </li> --}}

    </ul>
</nav>