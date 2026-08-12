<nav id="sidebar" class="d-flex flex-column flex-shrink-0">
    @php
        $workspace = $currentWorkspace ?? null;
        $canViewWorkspaceDashboard = Auth::check()
            && $workspace instanceof \App\Models\Workspace
            && $workspace->canBeManagedBy(Auth::user());
        $isImpersonating = app(\App\Services\ImpersonationService::class)->isImpersonating();
        $sidebarRouteIs = static fn (string ...$patterns): bool => request()->routeIs(...$patterns);
    @endphp

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
            <a href="{{ $canViewWorkspaceDashboard ? route('workspace.dashboard', $workspace, false) : route('dashboard') }}"
                class="nav-link {{ $sidebarRouteIs('dashboard', 'workspace.dashboard') ? 'active' : '' }}"
                @if ($sidebarRouteIs('dashboard', 'workspace.dashboard')) aria-current="page" @endif>
                <i class="fa-solid fa-house fa-fw"></i> <span>Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="{{ route('home') }}" class="nav-link">
                <i class="fa-solid fa-globe fa-fw"></i> <span>Home</span>
            </a>
        </li>

        {{-- workspaces --}}
        <li class="nav-item">
            <a href="{{ route('workspace.index', [], false) }}"
                class="nav-link {{ $sidebarRouteIs('workspace.index', 'workspace.create', 'workspace.show') ? 'active' : '' }}"
                @if ($sidebarRouteIs('workspace.index', 'workspace.create', 'workspace.show')) aria-current="page" @endif>
                <i class="fa-solid fa-building fa-fw"></i> <span>Workspaces</span>
            </a>
        </li>

        @php
            $sidebarUser = Auth::user();
            $recoverableWorkspaceCount = $sidebarUser
                ? $sidebarUser->ownedDeletedWorkspaces()
                    ->where('deleted_at', '>=', now()->subDays((int) config('workspace-lifecycle.self_service_restore_days')))
                    ->count()
                : 0;
        @endphp

        @if (! $isImpersonating && $sidebarUser && ($sidebarUser->ownedDeletedWorkspaces()->exists() || $sidebarUser->isPlatformOwner()))
            <li class="nav-item">
                <a href="{{ route('workspace.recovery.index') }}"
                    class="nav-link {{ $sidebarRouteIs('workspace.recovery.*') ? 'active' : '' }}"
                    @if ($sidebarRouteIs('workspace.recovery.*')) aria-current="page" @endif>
                    <i class="fa-solid fa-recycle fa-fw"></i>
                    <span>Recovery Center</span>
                    @if ($recoverableWorkspaceCount > 0)
                        <span class="badge bg-warning text-dark ms-auto">{{ $recoverableWorkspaceCount }}</span>
                    @endif
                </a>
            </li>
        @endif

        @can('view-platform-dashboard')
            <li class="nav-divider"></li>
            <li class="nav-section-title">Platform Management</li>
            <li class="nav-item">
                <a href="{{ route('platform.dashboard') }}" class="nav-link {{ $sidebarRouteIs('platform.dashboard') ? 'active' : '' }}"
                    @if ($sidebarRouteIs('platform.dashboard')) aria-current="page" @endif>
                    <i class="fa-solid fa-gauge-high fa-fw"></i> <span>Platform Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('platform.workspaces.index') }}" class="nav-link {{ $sidebarRouteIs('platform.workspaces.*') ? 'active' : '' }}"
                    @if ($sidebarRouteIs('platform.workspaces.*')) aria-current="page" @endif>
                    <i class="fa-solid fa-building-shield fa-fw"></i> <span>Platform Workspaces</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('platform.testimonials.index') }}" class="nav-link {{ $sidebarRouteIs('platform.testimonials.*') ? 'active' : '' }}"
                    @if ($sidebarRouteIs('platform.testimonials.*')) aria-current="page" @endif>
                    <i class="fa-solid fa-quote-left fa-fw"></i> <span>Testimonials</span>
                </a>
            </li>
        @endcan

        @if (! $isImpersonating && $sidebarUser?->isPlatformOwner())
            <li class="nav-item">
                <a href="{{ route('platform.recovery.index') }}"
                    class="nav-link {{ $sidebarRouteIs('platform.recovery.index', 'platform.recovery.show') ? 'active' : '' }}"
                    @if ($sidebarRouteIs('platform.recovery.index', 'platform.recovery.show')) aria-current="page" @endif>
                    <i class="fa-solid fa-shield-halved fa-fw"></i> <span>Deleted Workspaces</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('platform.recovery.audits') }}"
                    class="nav-link {{ $sidebarRouteIs('platform.recovery.audits') ? 'active' : '' }}"
                    @if ($sidebarRouteIs('platform.recovery.audits')) aria-current="page" @endif>
                    <i class="fa-solid fa-clipboard-list fa-fw"></i> <span>Lifecycle Audit</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="{{ route('platform.user-recovery.index') }}"
                    class="nav-link {{ $sidebarRouteIs('platform.user-recovery.*') ? 'active' : '' }}"
                    @if ($sidebarRouteIs('platform.user-recovery.*')) aria-current="page" @endif>
                    <i class="fa-solid fa-user-shield fa-fw"></i> <span>Deleted User Accounts</span>
                </a>
            </li>
        @endif

        @can('manage-platform-users')
            <li class="nav-item">
                <a href="{{ route('platform.users.index') }}"
                    class="nav-link {{ $sidebarRouteIs('platform.users.*') ? 'active' : '' }}"
                    @if ($sidebarRouteIs('platform.users.*')) aria-current="page" @endif>
                    <i class="fa-solid fa-user-gear fa-fw"></i> <span>Platform Users</span>
                </a>
            </li>
        @endcan

        @if (! $isImpersonating && in_array(Auth::user()?->role, ['owner', 'admin'], true))
            <li class="nav-item">
                <a href="{{ route('currencies.index') }}" class="nav-link {{ $sidebarRouteIs('currencies.*') ? 'active' : '' }}"
                    @if ($sidebarRouteIs('currencies.*')) aria-current="page" @endif>
                    <i class="fa-solid fa-coins fa-fw"></i> <span>Currencies</span>
                </a>
            </li>
        @endif

        @if ($canViewWorkspaceDashboard)


            <li class="nav-divider"></li>
            <li class="nav-section-title">{{ $workspace->name }}</li>


            {{-- dashboard --}}
            <li class="nav-item">
                <a href="{{ route('workspace.dashboard', $workspace, false) }}"
                    class="nav-link {{ $sidebarRouteIs('workspace.dashboard') ? 'active' : '' }}"
                    @if ($sidebarRouteIs('workspace.dashboard')) aria-current="page" @endif>
                    <i class="fa-solid fa-house fa-fw"></i> <span>Dashboard</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('dashboard.ai-query') }}"
                    class="nav-link {{ $sidebarRouteIs('dashboard.ai-query', 'dashboard.ai-query.search') ? 'active' : '' }}"
                    @if ($sidebarRouteIs('dashboard.ai-query', 'dashboard.ai-query.search')) aria-current="page" @endif>
                    <i class="fa-solid fa-wand-magic-sparkles fa-fw"></i> <span>AI Query</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('reports.index') }}" class="nav-link {{ $sidebarRouteIs('reports.*') ? 'active' : '' }}"
                    @if ($sidebarRouteIs('reports.*')) aria-current="page" @endif>
                    <i class="fa-solid fa-chart-column fa-fw"></i> <span>Reports</span>
                </a>
            </li>


            {{-- invoices --}}
            <li class="nav-item">
                <a href="{{ route('invoices.index', $workspace, false) }}" class="nav-link {{ $sidebarRouteIs('invoices.*') ? 'active' : '' }}"
                    @if ($sidebarRouteIs('invoices.*')) aria-current="page" @endif>
                    <i class="fa-solid fa-receipt fa-fw"></i> <span>Invoices</span>
                </a>
            </li>


            {{-- clients --}}

            <li class="nav-item">
                <a href="{{ route('clients.index', $workspace, false) }}" class="nav-link {{ $sidebarRouteIs('clients.*') ? 'active' : '' }}"
                    @if ($sidebarRouteIs('clients.*')) aria-current="page" @endif>
                    <i class="fa-solid fa-user-tie fa-fw"></i> <span>Clients</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('testimonials.index', $workspace, false) }}" class="nav-link {{ $sidebarRouteIs('testimonials.*') ? 'active' : '' }}"
                    @if ($sidebarRouteIs('testimonials.*')) aria-current="page" @endif>
                    <i class="fa-solid fa-quote-left fa-fw"></i> <span>Testimonials</span>
                </a>
            </li>

            {{-- users --}}
            <li class="nav-item">
                <a href="{{ route('workspace.users.index', $workspace, false) }}" class="nav-link {{ $sidebarRouteIs('workspace.users.*') ? 'active' : '' }}"
                    @if ($sidebarRouteIs('workspace.users.*')) aria-current="page" @endif>
                    <i class="fa-solid fa-users fa-fw"></i> <span>Users</span>
                </a>
            </li>


            {{-- reminder dashboard dropdown - only visible to owners and admins --}}
            <li class="nav-item has-dropdown {{ $sidebarRouteIs('reminders.*') ? 'open' : '' }}">
                <a href="#" class="nav-link {{ $sidebarRouteIs('reminders.*') ? 'active' : '' }}"
                    aria-expanded="{{ $sidebarRouteIs('reminders.*') ? 'true' : 'false' }}"
                    aria-controls="sidebar-reminders-menu">
                    <i class="fa-solid fa-bell fa-fw"></i>
                    <span>Reminders</span>
                    <i class="fa-solid fa-chevron-down dropdown-toggle-icon"></i>
                </a>
                <ul class="sidebar-dropdown-menu" id="sidebar-reminders-menu">
                    <li><a href="{{ route('reminders.index') }}" class="{{ $sidebarRouteIs('reminders.index') ? 'active' : '' }}"
                            @if ($sidebarRouteIs('reminders.index')) aria-current="page" @endif>
                            <i class="fa-solid fa-chart-line fa-fw"></i> Overview</a>
                    </li>
                    <li><a href="{{ route('reminders.activity') }}" class="{{ $sidebarRouteIs('reminders.activity') ? 'active' : '' }}"
                            @if ($sidebarRouteIs('reminders.activity')) aria-current="page" @endif>
                            <i class="fa-solid fa-history fa-fw"></i> Activity</a>
                    </li>
                    <li><a href="{{ route('reminders.upcoming') }}" class="{{ $sidebarRouteIs('reminders.upcoming') ? 'active' : '' }}"
                            @if ($sidebarRouteIs('reminders.upcoming')) aria-current="page" @endif>
                            <i class="fa-solid fa-calendar-days fa-fw"></i> Upcoming</a></li>
                    <li><a href="{{ route('reminders.sent') }}" class="{{ $sidebarRouteIs('reminders.sent') ? 'active' : '' }}"
                            @if ($sidebarRouteIs('reminders.sent')) aria-current="page" @endif>
                            <i class="fa-solid fa-paper-plane fa-fw"></i> Sent</a></li>
                    <li><a href="{{ route('reminders.failed') }}" class="{{ $sidebarRouteIs('reminders.failed') ? 'active' : '' }}"
                            @if ($sidebarRouteIs('reminders.failed')) aria-current="page" @endif>
                            <i class="fa-solid fa-exclamation-triangle fa-fw"></i> Failed</a></li>
                </ul>
            </li>


            {{-- <li class="nav-divider"></li> --}}
            <li class="nav-section-title">Settings</li>

            {{-- workspace settings --}}
            <li class="nav-item">
                <a href="{{ route('workspace.edit', $workspace->id, false) }}"
                    class="nav-link {{ $sidebarRouteIs('workspace.edit') ? 'active' : '' }}"
                    @if ($sidebarRouteIs('workspace.edit')) aria-current="page" @endif>
                    <i class="fa-solid fa-gear fa-fw"></i> <span>Workspace </span>
                </a>
            </li>

            {{-- business profiles --}}
            <li class="nav-item">
                <a href="{{ route('business-profile.index') }}" class="nav-link {{ $sidebarRouteIs('business-profile.*') ? 'active' : '' }}"
                    @if ($sidebarRouteIs('business-profile.*')) aria-current="page" @endif>
                    <i class="fa-solid fa-briefcase fa-fw"></i> <span>Business Profile</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('email-templates.index', $workspace, false) }}" class="nav-link {{ $sidebarRouteIs('email-templates.*') ? 'active' : '' }}"
                    @if ($sidebarRouteIs('email-templates.*')) aria-current="page" @endif>
                    <i class="fa-solid fa-envelope-open-text fa-fw"></i> <span>Email Templates</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="{{ route('reminder-schedules.index', $workspace, false) }}" class="nav-link {{ $sidebarRouteIs('reminder-schedules.*') ? 'active' : '' }}"
                    @if ($sidebarRouteIs('reminder-schedules.*')) aria-current="page" @endif>
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
