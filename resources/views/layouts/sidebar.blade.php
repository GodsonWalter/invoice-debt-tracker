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
            <a href="{{ route('workspace.index', [], false) }}" class="nav-link">
                <i class="fa-solid fa-building fa-fw"></i> <span>Workspaces</span>
            </a>
        </li>

        @if (in_array(Auth::user()?->role, ['owner', 'admin'], true))
            <li class="nav-item">
                <a href="{{ route('currencies.index') }}" class="nav-link">
                    <i class="fa-solid fa-coins fa-fw"></i> <span>Currencies</span>
                </a>
            </li>
        @endif

        @php
            $workspace = $currentWorkspace ?? null;
        @endphp

        @if (Auth::check() && $workspace instanceof \App\Models\Workspace && $workspace->canBeManagedBy(Auth::user()))


            <li class="nav-divider"></li>
            <li class="nav-section-title">{{ $workspace->name }}</li>


            {{-- dashboard --}}
            <li class="nav-item">
                <a href="{{ route('workspace.dashboard', $workspace, false) }}" class="nav-link">
                    <i class="fa-solid fa-house fa-fw"></i> <span>Dashboard</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('dashboard.ai-query') }}" class="nav-link">
                    <i class="fa-solid fa-wand-magic-sparkles fa-fw"></i> <span>AI Query</span>
                </a>
            </li>


            {{-- invoices --}}
            <li class="nav-item">
                <a href="{{ route('invoices.index', $workspace, false) }}" class="nav-link">
                    <i class="fa-solid fa-receipt fa-fw"></i> <span>Invoices</span>
                </a>
            </li>


            {{-- clients --}}

            <li class="nav-item">
                <a href="{{ route('clients.index', $workspace, false) }}" class="nav-link">
                    <i class="fa-solid fa-user-tie fa-fw"></i> <span>Clients</span>
                </a>
            </li>

            {{-- users --}}
            <li class="nav-item">
                <a href="{{ route('workspace.users.index', $workspace, false) }}" class="nav-link">
                    <i class="fa-solid fa-users fa-fw"></i> <span>Users</span>
                </a>
            </li>


            {{-- reminder dashboard dropdown - only visible to owners and admins --}}
            <li class="nav-item has-dropdown">
                <a href="#" class="nav-link {{ request()->routeIs('reminders.*') ? 'active' : '' }}">
                    <i class="fa-solid fa-bell fa-fw"></i>
                    <span>Reminders</span>
                    <i class="fa-solid fa-chevron-down dropdown-toggle-icon"></i>
                </a>
                <ul class="sidebar-dropdown-menu">
                    <li><a href="{{ route('reminders.index') }}"><i class="fa-solid fa-chart-line fa-fw"></i> Overview</a>
                    </li>
                    <li><a href="{{ route('reminders.activity') }}"><i class="fa-solid fa-history fa-fw"></i> Activity</a>
                    </li>
                    <li><a href="{{ route('reminders.upcoming') }}"><i class="fa-solid fa-calendar-days fa-fw"></i>
                            Upcoming</a></li>
                    <li><a href="{{ route('reminders.sent') }}"><i class="fa-solid fa-paper-plane fa-fw"></i> Sent</a></li>
                    <li><a href="{{ route('reminders.failed') }}"><i class="fa-solid fa-exclamation-triangle fa-fw"></i>
                            Failed</a></li>
                </ul>
            </li>


            {{-- <li class="nav-divider"></li> --}}
            <li class="nav-section-title">Settings</li>

            {{-- workspace settings --}}
            <li class="nav-item">
                <a href="{{ route('workspace.edit', $workspace->id, false) }}" class="nav-link">
                    <i class="fa-solid fa-gear fa-fw"></i> <span>Workspace </span>
                </a>
            </li>

            {{-- business profiles --}}
            <li class="nav-item">
                <a href="{{ route('business-profile.index') }}" class="nav-link">
                    <i class="fa-solid fa-briefcase fa-fw"></i> <span>Business Profile</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('email-templates.index', $workspace, false) }}" class="nav-link">
                    <i class="fa-solid fa-envelope-open-text fa-fw"></i> <span>Email Templates</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('reminder-schedules.index', $workspace, false) }}" class="nav-link">
                    <i class="fa-solid fa-bell fa-fw"></i> <span>Reminder Schedules</span>
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
