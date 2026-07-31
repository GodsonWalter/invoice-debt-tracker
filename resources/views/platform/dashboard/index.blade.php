@extends('layouts.app')

@section('page_title', 'Platform Dashboard')

@section('content')
    @php
        $period = $dashboard['period'];
        $overview = $dashboard['overview'];
        $system = $dashboard['system'];
        $periodOptions = [
            'today' => 'Today',
            'last_7_days' => 'Last 7 days',
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'last_3_months' => 'Last 3 months',
            'last_6_months' => 'Last 6 months',
            'this_year' => 'This year',
            'custom' => 'Custom range',
        ];
        $overviewCards = [
            ['label' => 'Active users', 'value' => $overview['active_users'], 'detail' => $overview['new_users'].' new in period', 'icon' => 'fa-users', 'tone' => 'primary'],
            ['label' => 'Active workspaces', 'value' => $overview['active_workspaces'], 'detail' => $overview['new_workspaces'].' new in period', 'icon' => 'fa-building', 'tone' => 'success'],
            ['label' => 'Active memberships', 'value' => $overview['active_memberships'], 'detail' => 'Across active workspaces', 'icon' => 'fa-user-group', 'tone' => 'info'],
            ['label' => 'Invoices created', 'value' => $overview['invoices_period'], 'detail' => $period->label, 'icon' => 'fa-file-invoice', 'tone' => 'secondary'],
            ['label' => 'Payment transactions', 'value' => $overview['payments_period'], 'detail' => $period->label, 'icon' => 'fa-money-bill-transfer', 'tone' => 'success'],
            ['label' => 'Overdue invoices', 'value' => $overview['overdue_invoices'], 'detail' => 'Currently outstanding', 'icon' => 'fa-triangle-exclamation', 'tone' => 'danger'],
            ['label' => 'Failed reminders', 'value' => $overview['failed_reminders_period'], 'detail' => $period->label, 'icon' => 'fa-bell-slash', 'tone' => 'warning'],
            ['label' => 'Pending reminders', 'value' => $overview['pending_reminders'], 'detail' => 'Current queue', 'icon' => 'fa-bell', 'tone' => 'warning'],
        ];
    @endphp

    <div class="container-fluid py-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="fs-4 fw-bold mb-1">Platform Dashboard</h1>
                <p class="text-muted mb-0">Global operational health across IDT workspaces and accounts.</p>
            </div>
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label for="platform-dashboard-period" class="form-label visually-hidden">Reporting period</label>
                    <select id="platform-dashboard-period" name="period" class="form-select">
                        @foreach ($periodOptions as $value => $label)
                            <option value="{{ $value }}" @selected($period->period === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto platform-dashboard-custom-date">
                    <label for="platform-dashboard-start-date" class="form-label visually-hidden">Start date</label>
                    <input id="platform-dashboard-start-date" type="date" name="start_date" value="{{ request('start_date') }}" class="form-control">
                </div>
                <div class="col-auto platform-dashboard-custom-date">
                    <label for="platform-dashboard-end-date" class="form-label visually-hidden">End date</label>
                    <input id="platform-dashboard-end-date" type="date" name="end_date" value="{{ request('end_date') }}" class="form-control">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i>Apply</button>
                </div>
            </form>
        </div>

        <div class="row g-3 mb-4">
            @foreach ($overviewCards as $card)
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-body d-flex align-items-start justify-content-between gap-3">
                            <div>
                                <p class="text-muted small mb-1">{{ $card['label'] }}</p>
                                <div class="h3 fw-bold mb-1">{{ number_format($card['value']) }}</div>
                                <small class="text-muted">{{ $card['detail'] }}</small>
                            </div>
                            <span class="icon-circle bg-{{ $card['tone'] }}-subtle text-{{ $card['tone'] }}"><i class="fa-solid {{ $card['icon'] }}"></i></span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12 col-xl-8">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h2 class="h5 fw-bold mb-1">Platform growth</h2>
                        <p class="text-muted small mb-0">New platform activity for {{ $period->label }}.</p>
                    </div>
                    <div class="card-body px-4"><div style="height: 300px"><canvas id="platformGrowthChart" aria-label="Platform growth chart"></canvas></div></div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h2 class="h5 fw-bold mb-1">Needs attention</h2>
                        <p class="text-muted small mb-0">Operational items requiring review.</p>
                    </div>
                    <div class="card-body px-4">
                        @forelse ($dashboard['attention'] as $item)
                            <div class="border-bottom py-3 first-child-pt-0">
                                <div class="d-flex align-items-center justify-content-between gap-3">
                                    <div class="d-flex align-items-center gap-2"><span class="badge text-bg-{{ $item['tone'] }}">{{ $item['count'] }}</span><span class="small">{{ $item['label'] }}</span></div>
                                    @if ($item['url'])<a href="{{ $item['url'] }}" class="small text-nowrap">Review</a>@endif
                                </div>
                                @if (! empty($item['workspaces']))
                                    <div class="mt-2 ms-4">
                                        <div class="small text-muted mb-1">Affected workspaces</div>
                                        <ul class="list-unstyled small mb-0">
                                            @foreach ($item['workspaces'] as $workspace)
                                                <li class="d-flex align-items-baseline gap-2 text-break">
                                                    <i class="fa-solid fa-building text-muted"></i>
                                                    <span>{{ $workspace['name'] }}</span>
                                                    @if ($workspace['identifier'])<span class="text-muted">({{ $workspace['identifier'] }})</span>@endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-center text-muted py-5"><i class="fa-solid fa-circle-check text-success fs-2 mb-2"></i><p class="mb-0">No operational issues detected.</p></div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <h2 class="h5 fw-bold mb-1">Financial activity by currency</h2>
                <p class="text-muted small mb-0">Amounts are intentionally kept separate because workspaces may use different currencies.</p>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light"><tr><th>Currency</th><th>Invoices</th><th class="text-end">Invoiced</th><th>Payments</th><th class="text-end">Collected</th><th class="text-end">Outstanding</th></tr></thead>
                    <tbody>
                        @forelse ($dashboard['currencies'] as $currency)
                            <tr>
                                <td><span class="badge text-bg-secondary">{{ $currency['code'] }}</span></td>
                                <td>{{ number_format($currency['invoice_count']) }}</td>
                                <td class="text-end">{{ $currency['symbol'] }}{{ number_format($currency['invoiced_amount'], 2) }}</td>
                                <td>{{ number_format($currency['payment_count']) }}</td>
                                <td class="text-end text-success">{{ $currency['symbol'] }}{{ number_format($currency['collected_amount'], 2) }}</td>
                                <td class="text-end text-danger">{{ $currency['symbol'] }}{{ number_format($currency['outstanding_amount'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No financial activity in the selected period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12 col-xl-8">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4"><h2 class="h5 fw-bold mb-1">Workspace health</h2><p class="text-muted small mb-0">Recent active workspaces and their operating footprint.</p></div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light"><tr><th>Workspace</th><th>Owner</th><th>Currency</th><th>Members</th><th>Invoices</th><th>Last activity</th></tr></thead>
                            <tbody>
                                @forelse ($dashboard['workspace_health'] as $workspace)
                                    <tr>
                                        <td><div class="fw-semibold">{{ $workspace->name }}</div><small class="text-muted">{{ $workspace->subdomain ?: $workspace->slug }}</small></td>
                                        <td>{{ $workspace->owner?->name ?: $workspace->owner?->email ?: 'Unknown' }}</td>
                                        <td>{{ $workspace->currency?->code ?: 'Unspecified' }}</td>
                                        <td>{{ number_format($workspace->active_members_count) }}</td>
                                        <td>{{ number_format($workspace->invoices_count) }}</td>
                                        <td>{{ $workspace->last_activity_at ? \Carbon\CarbonImmutable::parse($workspace->last_activity_at)->diffForHumans() : 'No activity' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-4">No active workspaces found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4"><h2 class="h5 fw-bold mb-1">System health</h2><p class="text-muted small mb-0">Current queue and lifecycle signals.</p></div>
                    <div class="card-body px-4">
                        <ul class="list-group list-group-flush small">
                            <li class="list-group-item px-0 d-flex justify-content-between"><span>Queue connection</span><strong>{{ $system['queue_connection'] }}</strong></li>
                            <li class="list-group-item px-0 d-flex justify-content-between"><span>Queued jobs</span><strong>{{ $system['queued_jobs'] ?? 'Unavailable' }}</strong></li>
                            <li class="list-group-item px-0 d-flex justify-content-between"><span>Failed jobs</span><strong class="{{ ($system['failed_jobs'] ?? 0) > 0 ? 'text-danger' : 'text-success' }}">{{ $system['failed_jobs'] ?? 'Unavailable' }}</strong></li>
                            <li class="list-group-item px-0 d-flex justify-content-between"><span>Pending lifecycle mail</span><strong>{{ $system['pending_lifecycle_notifications'] }}</strong></li>
                            <li class="list-group-item px-0 d-flex justify-content-between"><span>Scheduler</span><strong class="text-muted">{{ $system['scheduler_status'] }}</strong></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12 col-xl-7">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4"><h2 class="h5 fw-bold mb-1">Recent platform activity</h2><p class="text-muted small mb-0">Privileged account and workspace lifecycle events.</p></div>
                    <div class="list-group list-group-flush">
                        @forelse ($dashboard['activity'] as $activity)
                            <div class="list-group-item px-4 py-3"><div class="d-flex justify-content-between gap-3"><div><i class="fa-solid {{ $activity['type'] === 'user' ? 'fa-user-shield' : 'fa-building-shield' }} text-primary me-2"></i><strong>{{ str($activity['event'])->replace('_', ' ')->title() }}</strong> <span class="text-muted">· {{ $activity['target'] }}</span></div><small class="text-muted text-nowrap">{{ $activity['date']?->diffForHumans() }}</small></div><small class="text-muted ms-4">By {{ $activity['actor'] }}@if ($activity['reason']) · {{ str($activity['reason'])->limit(100) }}@endif</small></div>
                        @empty
                            <div class="p-4 text-center text-muted">No platform activity recorded yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-5">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4"><h2 class="h5 fw-bold mb-1">Quick actions</h2><p class="text-muted small mb-0">Open existing platform management tools.</p></div>
                    <div class="card-body px-4"><div class="row g-2">
                        @foreach ($dashboard['quick_actions'] as $action)
                            <div class="col-6"><a href="{{ $action['url'] }}" class="btn btn-light border w-100 text-start small py-2"><i class="fa-solid {{ $action['icon'] }} text-primary me-1"></i>{{ $action['label'] }}</a></div>
                        @endforeach
                    </div></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .icon-circle { width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; }
        .platform-dashboard-custom-date { display: none; }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const periodSelect = document.getElementById('platform-dashboard-period');
            const dateFields = document.querySelectorAll('.platform-dashboard-custom-date');
            const toggleCustomDates = () => dateFields.forEach((field) => field.style.display = periodSelect?.value === 'custom' ? 'block' : 'none');
            periodSelect?.addEventListener('change', toggleCustomDates);
            toggleCustomDates();

            const canvas = document.getElementById('platformGrowthChart');
            const trendData = @json($dashboard['trends']);

            if (canvas && typeof Chart !== 'undefined') {
                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: trendData.labels,
                        datasets: [
                            { label: 'Users', data: trendData.users, borderColor: '#0d6efd', backgroundColor: 'rgba(13, 110, 253, .08)', fill: true, tension: .35 },
                            { label: 'Workspaces', data: trendData.workspaces, borderColor: '#198754', backgroundColor: 'transparent', tension: .35 },
                            { label: 'Invoices', data: trendData.invoices, borderColor: '#6f42c1', backgroundColor: 'transparent', tension: .35 },
                            { label: 'Payments', data: trendData.payments, borderColor: '#fd7e14', backgroundColor: 'transparent', tension: .35 },
                        ],
                    },
                    options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } },
                });
            }
        });
    </script>
@endpush
