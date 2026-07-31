@extends('layouts.app')

@section('page_title', 'Workspace Dashboard')

@php
    $kpis = $dashboard['kpis'];
    $period = $dashboard['period'];
    $currency = $dashboard['currencyModel'];
    $money = fn ($amount) => $currency?->formatMoney($amount) ?? number_format((float) $amount, 2);
    $statusBadge = fn ($status) => match ($status) {
        'draft' => 'secondary',
        'sent' => 'info',
        'partial' => 'warning',
        'paid' => 'success',
        'overdue' => 'danger',
        default => 'secondary',
    };
@endphp

@section('content')
    <div class="container-fluid py-2">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center mb-4">
            <div>
                <p class="text-primary text-uppercase small fw-semibold mb-1">Workspace overview</p>

                <p class="text-muted mb-0">Daily invoicing, collections, and reminder performance.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('invoices.create', $workspace, false) }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Create invoice
                </a>
                <a href="{{ route('clients.create', $workspace, false) }}" class="btn btn-outline-primary">
                    <i class="bi bi-person-plus me-1"></i> Add customer
                </a>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-3">
                <form method="GET" action="{{ url()->current() }}" class="row g-2 align-items-end">
                    <div class="col-12 col-md-4 col-lg-3">
                        <label for="dashboard-period" class="form-label small fw-semibold mb-1">Analytics period</label>
                        <select id="dashboard-period" name="period" class="form-select form-select-sm">
                            @foreach ([
                                'today' => 'Today',
                                'last_7_days' => 'Last 7 days',
                                'this_month' => 'This month',
                                'last_month' => 'Last month',
                                'last_3_months' => 'Last 3 months',
                                'last_6_months' => 'Last 6 months',
                                'this_year' => 'This year',
                                'custom' => 'Custom range',
                            ] as $value => $label)
                                <option value="{{ $value }}" @selected($period->period === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($dashboard['currencies']->isNotEmpty())
                        <div class="col-12 col-md-4 col-lg-3">
                            <label for="dashboard-currency" class="form-label small fw-semibold mb-1">Currency</label>
                            <select id="dashboard-currency" name="currency_id" class="form-select form-select-sm">
                                @foreach ($dashboard['currencies'] as $option)
                                    <option value="{{ $option->id }}" @selected($currency?->id === $option->id)>{{ $option->code }} - {{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-6 col-md-3 col-lg-2 dashboard-custom-date">
                        <label for="dashboard-start-date" class="form-label small fw-semibold mb-1">From</label>
                        <input id="dashboard-start-date" name="start_date" type="date" class="form-control form-control-sm"
                            value="{{ request('start_date') }}">
                    </div>
                    <div class="col-6 col-md-3 col-lg-2 dashboard-custom-date">
                        <label for="dashboard-end-date" class="form-label small fw-semibold mb-1">To</label>
                        <input id="dashboard-end-date" name="end_date" type="date" class="form-control form-control-sm"
                            value="{{ request('end_date') }}">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-dark">Apply</button>
                    </div>
                    <div class="col-12 col-lg-auto ms-lg-auto text-muted small">
                         Showing {{ $period->label }} in {{ $currency?->code ?? 'an unspecified currency' }}. Current-state cards are labelled separately; period metrics use business dates.
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted small">Total revenue</span>
                            <span class="icon-circle bg-success-subtle text-success"><i class="bi bi-graph-up-arrow"></i></span>
                        </div>
                        <h2 class="h5 fw-bold mb-1">{{ $money($kpis['revenue_all_time']) }}</h2>
                        <p class="text-muted small mb-0">{{ $money($kpis['revenue_period']) }} in selected period</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted small">Outstanding debt · Current</span>
                            <span class="icon-circle bg-warning-subtle text-warning"><i class="bi bi-wallet2"></i></span>
                        </div>
                        <h2 class="h5 fw-bold mb-1">{{ $money($kpis['outstanding_debt']) }}</h2>
                        <p class="text-muted small mb-0">{{ $kpis['unpaid_count'] }} unpaid invoices · {{ $kpis['customers_owing'] }} customers</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted small">Overdue amount · As of today</span>
                            <span class="icon-circle bg-danger-subtle text-danger"><i class="bi bi-exclamation-triangle"></i></span>
                        </div>
                        <h2 class="h5 fw-bold mb-1">{{ $money($kpis['overdue_amount']) }}</h2>
                        <p class="text-muted small mb-0">{{ $kpis['overdue_count'] }} overdue invoices
                            @if ($kpis['oldest_overdue_age'] !== null)
                                · oldest {{ $kpis['oldest_overdue_age'] }}d
                            @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted small">Payments received · Selected period</span>
                            <span class="icon-circle bg-primary-subtle text-primary"><i class="bi bi-cash-stack"></i></span>
                        </div>
                        <h2 class="h5 fw-bold mb-1">{{ $money($kpis['payments_period']) }}</h2>
                        <p class="text-muted small mb-0">{{ $kpis['payment_transactions_period'] }} payment transactions
                            @if ($kpis['revenue_change_percent'] !== null)
                                · {{ $kpis['revenue_change_percent'] >= 0 ? '+' : '' }}{{ $kpis['revenue_change_percent'] }}% vs {{ $period->comparisonLabel }}
                            @endif
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted small">Pending invoices · Current</span>
                            <span class="icon-circle bg-info-subtle text-info"><i class="bi bi-file-earmark-text"></i></span>
                        </div>
                        <h2 class="h5 fw-bold mb-1">{{ $kpis['pending_invoice_count'] }}</h2>
                        <p class="text-muted small mb-0">{{ $money($kpis['pending_invoice_value']) }} invoice value</p>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-xl-4 col-xxl-2">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted small">Reminder success</span>
                            <span class="icon-circle bg-secondary-subtle text-secondary"><i class="bi bi-bell"></i></span>
                        </div>
                        <h2 class="h5 fw-bold mb-1">{{ number_format($kpis['reminder_success_rate'], 1) }}%</h2>
                        <p class="text-muted small mb-0">{{ $kpis['reminders_sent_period'] }} sent · {{ $kpis['reminders_failed_period'] }} failed</p>
                    </div>
                </div>
            </div>
        </div>

        @if (count($dashboard['alerts']) > 0)
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-3">
                    <h2 class="h6 fw-bold mb-3"><i class="bi bi-flag me-1"></i> Attention needed</h2>
                    <div class="row g-2">
                        @foreach ($dashboard['alerts'] as $alert)
                            <div class="col-12 col-lg-6">
                                <a href="{{ $alert['url'] }}" class="alert alert-{{ $alert['severity'] }} d-flex gap-3 align-items-start mb-0 text-decoration-none">
                                    <i class="bi bi-info-circle"></i>
                                    <span><strong>{{ $alert['title'] }}</strong><br><small>{{ $alert['message'] }}</small></span>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="row g-4 mb-4">
            <div class="col-12 col-xl-8">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div><h2 class="h5 fw-bold mb-1">Revenue and collections</h2><p class="text-muted small mb-0">{{ ucfirst($period->granularity) }} invoice and payment movement.</p></div>
                            <span class="badge bg-light text-dark">{{ $dashboard['currency']['code'] ?? 'Workspace currency' }}</span>
                        </div>
                    </div>
                    <div class="card-body px-4"><div style="height: 310px"><canvas id="collectionChart" aria-label="Revenue and collections chart"></canvas></div></div>
                </div>
            </div>
            <div class="col-12 col-xl-4">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4"><h2 class="h5 fw-bold mb-1">Invoice status</h2><p class="text-muted small mb-0">Invoices issued in the selected period.</p></div>
                    <div class="card-body px-4"><div style="height: 245px"><canvas id="invoiceStatusChart" aria-label="Invoice status chart"></canvas></div>
                        <div class="small mt-3">
                            @forelse ($dashboard['invoiceStatuses'] as $status)
                                <div class="d-flex justify-content-between border-top py-2"><span><span class="badge bg-{{ $statusBadge($status->status) }} me-1">{{ ucfirst($status->status) }}</span></span><span>{{ $status->count }} · {{ $money($status->value) }}</span></div>
                            @empty
                                <p class="text-muted mb-0">No invoices in this period.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12 col-xl-5">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4"><h2 class="h5 fw-bold mb-1">Top debtors</h2><p class="text-muted small mb-0">Customers with the highest remaining balances.</p></div>
                    <div class="card-body p-0">
                        @forelse ($dashboard['topDebtors'] as $debtor)
                            <div class="px-4 py-3 border-top"><div class="d-flex justify-content-between gap-3"><div><a href="{{ route('clients.show', [$workspace, $debtor->id], false) }}" class="fw-semibold text-decoration-none">{{ $debtor->name }}</a><div class="small text-muted">{{ $debtor->unpaid_invoice_count }} unpaid invoices · {{ $debtor->oldest_unpaid_date }}</div></div><strong class="text-danger text-nowrap">{{ $money($debtor->outstanding_amount) }}</strong></div><div class="small text-muted mt-1">{{ $money($debtor->overdue_amount) }} overdue</div></div>
                        @empty
                            <div class="p-4 text-center text-muted">No customers currently owe money.</div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-12 col-xl-7">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center"><div><h2 class="h5 fw-bold mb-1">Recent invoices</h2><p class="text-muted small mb-0">Latest activity in this workspace.</p></div><a href="{{ route('invoices.index', $workspace, false) }}" class="small">View all</a></div>
                    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Invoice</th><th>Customer</th><th>Due</th><th class="text-end">Balance</th><th>Status</th></tr></thead><tbody>
                        @forelse ($dashboard['recentInvoices'] as $invoice)
                            <tr><td><a href="{{ route('invoices.show', [$workspace, $invoice], false) }}" class="fw-semibold text-decoration-none">{{ $invoice->invoice_number }}</a></td><td>{{ $invoice->client?->name ?? '—' }}</td><td>{{ $invoice->due_date?->format('d M Y') ?? '—' }}</td><td class="text-end">{{ $money($invoice->remaining_balance) }}</td><td><span class="badge bg-{{ $statusBadge($invoice->status) }}">{{ ucfirst($invoice->status) }}</span></td></tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Create your first invoice to see it here.</td></tr>
                        @endforelse
                    </tbody></table></div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12 col-xl-7">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center"><div><h2 class="h5 fw-bold mb-1">Overdue invoices</h2><p class="text-muted small mb-0">Invoices requiring collection attention.</p></div><a href="{{ route('invoices.index', $workspace, false) }}" class="small">View invoices</a></div>
                    <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Invoice</th><th>Customer</th><th>Days overdue</th><th class="text-end">Balance</th><th>Reminder</th><th></th></tr></thead><tbody>
                        @forelse ($dashboard['overdueInvoices'] as $invoice)
                            @php
                                $reminderStatus = $invoice->last_reminder_status;
                                $reminderBadge = $reminderStatus === 'failed' ? 'danger' : ($reminderStatus === 'sent' ? 'success' : 'warning text-dark');
                                $reminderLabel = $reminderStatus ? ucfirst($reminderStatus) : 'Needed';
                            @endphp
                            <tr><td><a href="{{ route('invoices.show', [$workspace, $invoice], false) }}" class="fw-semibold text-decoration-none">{{ $invoice->invoice_number }}</a></td><td>{{ $invoice->client?->name ?? '—' }}</td><td class="text-danger">{{ $invoice->due_date?->diffInDays(now()) }}d</td><td class="text-end">{{ $money($invoice->remaining_balance) }}</td><td><span class="badge bg-{{ $reminderBadge }}">{{ $reminderLabel }}</span></td><td><a href="{{ route('invoices.show', [$workspace, $invoice], false) }}" class="btn btn-sm btn-outline-primary" title="View invoice"><i class="bi bi-eye"></i></a></td></tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No overdue invoices. Great work.</td></tr>
                        @endforelse
                    </tbody></table></div>
                </div>
            </div>
            <div class="col-12 col-xl-5">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-header bg-white border-0 pt-4 px-4"><h2 class="h5 fw-bold mb-1">Reminder activity</h2><p class="text-muted small mb-0">Sent and failed attempts for {{ $period->label }}; pending reminders are current.</p></div>
                    <div class="card-body px-4"><div class="row g-3 text-center mb-3"><div class="col-4"><div class="h4 fw-bold mb-0">{{ $dashboard['reminders']['sent'] }}</div><small class="text-muted">Sent</small></div><div class="col-4"><div class="h4 fw-bold text-danger mb-0">{{ $dashboard['reminders']['failed'] }}</div><small class="text-muted">Failed</small></div><div class="col-4"><div class="h4 fw-bold text-warning mb-0">{{ $dashboard['reminders']['pending'] }}</div><small class="text-muted">Pending</small></div></div><ul class="list-group list-group-flush small"><li class="list-group-item px-0 d-flex justify-content-between"><span>Invoices without a schedule</span><strong>{{ $dashboard['reminders']['without_schedule'] }}</strong></li><li class="list-group-item px-0 d-flex justify-content-between"><span>Last reminder activity</span><span>{{ $dashboard['reminders']['last_activity_at']?->diffForHumans() ?? 'No activity' }}</span></li></ul>@if ($dashboard['reminders']['failed'] > 0)<a href="{{ route('reminders.failed', [], false) }}" class="btn btn-sm btn-outline-danger mt-3">Review failed reminders</a>@endif</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12 col-xl-5">
                <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center"><div><h2 class="h5 fw-bold mb-1">Recent payments</h2><p class="text-muted small mb-0">Latest confirmed payment records.</p></div><a href="{{ route('invoices.index', $workspace, false) }}" class="small">Payment history</a></div><div class="list-group list-group-flush">
                    @forelse ($dashboard['recentPayments'] as $payment)
                        <a href="{{ $payment->invoice ? route('invoices.show', [$workspace, $payment->invoice], false) : '#' }}" class="list-group-item list-group-item-action px-4 py-3"><div class="d-flex justify-content-between gap-3"><div><strong>{{ $payment->invoice?->client?->name ?? 'Unknown customer' }}</strong><div class="small text-muted">{{ $payment->invoice?->invoice_number ?? 'Invoice unavailable' }} · {{ $payment->payment_method ?: 'Unspecified method' }}</div></div><strong class="text-success text-nowrap">{{ $money($payment->amount) }}</strong></div><div class="small text-muted mt-1">{{ $payment->payment_date?->format('d M Y') }} · Recorded in workspace ledger</div></a>
                    @empty
                        <div class="p-4 text-center text-muted">Payments will appear here after the first collection.</div>
                    @endforelse
                </div></div>
            </div>
            <div class="col-12 col-xl-7">
                <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-header bg-white border-0 pt-4 px-4"><h2 class="h5 fw-bold mb-1">Quick actions</h2><p class="text-muted small mb-0">Common workspace operations.</p></div><div class="card-body px-4"><div class="row g-2">
                    @foreach ($dashboard['quickActions'] as $action)
                        <div class="col-6 col-md-4"><a href="{{ $action['url'] }}" class="btn btn-light border w-100 text-start small py-2"><i class="bi {{ $action['icon'] }} text-primary me-1"></i>{{ $action['label'] }}</a></div>
                    @endforeach
                </div></div></div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4"><div class="card-header bg-white border-0 pt-4 px-4"><h2 class="h5 fw-bold mb-1">Recent workspace activity</h2><p class="text-muted small mb-0">A combined view of recorded workspace events.</p></div><div class="card-body p-0"><div class="list-group list-group-flush">
            @forelse ($dashboard['activities'] as $activity)
                <div class="list-group-item px-4 py-3"><div class="d-flex justify-content-between gap-3"><div><i class="bi bi-clock-history text-primary me-2"></i>@if ($activity['url'])<a href="{{ $activity['url'] }}" class="text-decoration-none">{{ $activity['description'] }}</a>@else{{ $activity['description'] }}@endif</div><small class="text-muted text-nowrap">{{ $activity['date']?->diffForHumans() }}</small></div>@if ($activity['actor'])<small class="text-muted ms-4">By {{ $activity['actor'] }}</small>@endif</div>
            @empty
                <div class="p-4 text-center text-muted">No workspace activity yet. Create a customer or invoice to get started.</div>
            @endforelse
        </div></div></div>
    </div>
@endsection

@push('styles')
    <style>
        .icon-circle { width: 34px; height: 34px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; }
        .dashboard-custom-date { display: none; }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const periodSelect = document.getElementById('dashboard-period');
            const dateFields = document.querySelectorAll('.dashboard-custom-date');
            if (!periodSelect) {
                return;
            }

            const toggleCustomDates = () => dateFields.forEach((field) => field.style.display = periodSelect.value === 'custom' ? 'block' : 'none');
            periodSelect.addEventListener('change', toggleCustomDates);
            toggleCustomDates();

            const collectionCanvas = document.getElementById('collectionChart');
            const statusCanvas = document.getElementById('invoiceStatusChart');
            const collectionData = @json($dashboard['chart']);
            const statusData = @json($dashboard['invoiceStatuses']->map(fn ($item) => ['label' => ucfirst($item->status), 'count' => (int) $item->count])->values());

            if (collectionCanvas && typeof Chart !== 'undefined') {
                new Chart(collectionCanvas, {
                    type: 'line',
                    data: { labels: collectionData.labels, datasets: [
                        { label: 'Invoiced', data: collectionData.invoiced, borderColor: '#0d6efd', backgroundColor: 'rgba(13, 110, 253, .08)', fill: true, tension: .35 },
                        { label: 'Payments received', data: collectionData.payments, borderColor: '#198754', backgroundColor: 'transparent', tension: .35 },
                        { label: 'Outstanding balance', data: collectionData.outstanding, borderColor: '#dc3545', backgroundColor: 'transparent', borderDash: [5, 5], tension: .35 },
                    ] },
                    options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false }, scales: { y: { beginAtZero: true } } },
                });
            }

            if (statusCanvas && typeof Chart !== 'undefined') {
                new Chart(statusCanvas, {
                    type: 'doughnut',
                    data: { labels: statusData.map((item) => item.label), datasets: [{ data: statusData.map((item) => item.count), backgroundColor: ['#6c757d', '#0dcaf0', '#ffc107', '#198754', '#dc3545'] }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } },
                });
            }
        });
    </script>
@endpush
