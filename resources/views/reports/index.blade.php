@extends('layouts.app')

@section('page_title', $report['title'])

@php
    $filters = $report['filters'];
    $period = $report['period'];
    $money = fn ($value) => $workspace->formatMoney($value);
    $query = $filters->toQuery();
    $formatValue = function (string $key, mixed $value) use ($money): string {
        if ($value === '' || $value === null) {
            return '—';
        }

        if (in_array($key, ['amount', 'total_amount', 'amount_paid', 'outstanding_balance', 'total_invoiced', 'total_paid', 'overdue_balance'], true)) {
            return $money($value);
        }

        return (string) $value;
    };
@endphp

@section('content')
    <div class="container-fluid py-2">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center mb-4">
            <div>
                <p class="text-primary text-uppercase small fw-semibold mb-1">Reports & exports</p>
                <h1 class="h3 fw-bold mb-1">{{ $report['title'] }}</h1>
                <p class="text-muted mb-0">Workspace-scoped reporting for {{ $workspace->name }}.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @foreach (['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $format => $label)
                    <a href="{{ route('reports.export', ['report' => $filters->type->value, 'format' => $format] + $query) }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-download me-1"></i>{{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        @if ($exports->isNotEmpty())
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body">
                    <h2 class="h6 fw-bold mb-3">Recent exports</h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Report</th><th>Format</th><th>Status</th><th>Created</th><th class="text-end">Action</th></tr></thead>
                            <tbody>
                                @foreach ($exports as $export)
                                    <tr>
                                        <td>{{ ucfirst($export->report_type) }}</td>
                                        <td class="text-uppercase">{{ $export->format }}</td>
                                        <td><span class="badge text-bg-{{ $export->status === \App\Models\ReportExport::STATUS_COMPLETED ? 'success' : ($export->status === \App\Models\ReportExport::STATUS_FAILED ? 'danger' : 'warning') }}">{{ ucfirst($export->status) }}</span></td>
                                        <td>{{ $export->created_at?->format('Y-m-d H:i') }}</td>
                                        <td class="text-end">
                                            @if ($export->status === \App\Models\ReportExport::STATUS_COMPLETED)
                                                <a href="{{ route('reports.exports.download', $export) }}" class="btn btn-sm btn-outline-primary">Download</a>
                                            @elseif ($export->status === \App\Models\ReportExport::STATUS_FAILED)
                                                <span class="text-danger small">{{ $export->error_message }}</span>
                                            @else
                                                <span class="text-muted small">Processing</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    @foreach ($reportTypes as $reportType)
                        <a href="{{ route('reports.index', ['report' => $reportType->value]) }}" class="btn btn-sm {{ $reportType === $filters->type ? 'btn-primary' : 'btn-light border' }}">
                            {{ $reportType->label() }}
                        </a>
                    @endforeach
                </div>
                <form method="GET" action="{{ route('reports.index', ['report' => $filters->type->value]) }}" class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label for="report-period" class="form-label small fw-semibold">Period</label>
                        <select id="report-period" name="period" class="form-select form-select-sm">
                            @foreach ([
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
                    <div class="col-6 col-md-2 report-custom-date">
                        <label for="report-start-date" class="form-label small fw-semibold">From</label>
                        <input id="report-start-date" type="date" name="start_date" class="form-control form-control-sm" value="{{ $period->period === 'custom' ? $period->start->toDateString() : request('start_date') }}">
                    </div>
                    <div class="col-6 col-md-2 report-custom-date">
                        <label for="report-end-date" class="form-label small fw-semibold">To</label>
                        <input id="report-end-date" type="date" name="end_date" class="form-control form-control-sm" value="{{ $period->period === 'custom' ? $period->end->toDateString() : request('end_date') }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="report-customer" class="form-label small fw-semibold">Customer</label>
                        <select id="report-customer" name="customer_id" class="form-select form-select-sm">
                            <option value="">All customers</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected($filters->customerId === $customer->id)>{{ $customer->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label for="report-search" class="form-label small fw-semibold">Search</label>
                        <input id="report-search" type="search" name="search" class="form-control form-control-sm" value="{{ $filters->search }}" placeholder="Invoice or customer">
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="report-status" class="form-label small fw-semibold">Invoice status</label>
                        <select id="report-status" name="invoice_status" class="form-select form-select-sm">
                            <option value="">All statuses</option>
                            @foreach (\App\Models\Invoice::STATUSES as $status)
                                <option value="{{ $status }}" @selected($filters->invoiceStatus === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="report-reminder-status" class="form-label small fw-semibold">Reminder status</label>
                        <select id="report-reminder-status" name="reminder_status" class="form-select form-select-sm">
                            <option value="">All reminders</option>
                            @foreach (['pending', 'sent', 'failed'] as $status)
                                <option value="{{ $status }}" @selected($filters->reminderStatus === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="report-payment-method" class="form-label small fw-semibold">Payment method</label>
                        <input id="report-payment-method" type="text" name="payment_method" class="form-control form-control-sm" value="{{ $filters->paymentMethod }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="report-min" class="form-label small fw-semibold">Min amount</label>
                        <input id="report-min" type="number" step="0.01" min="0" name="amount_min" class="form-control form-control-sm" value="{{ $filters->amountMin }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label for="report-max" class="form-label small fw-semibold">Max amount</label>
                        <input id="report-max" type="number" step="0.01" min="0" name="amount_max" class="form-control form-control-sm" value="{{ $filters->amountMax }}">
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-sm btn-dark" type="submit">Apply filters</button>
                    </div>
                    <div class="col-12 text-muted small">Showing {{ $period->label }}. Debt and customer balances are current-state reports; transactional reports use their business date.</div>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-4">
            @foreach ($report['summary'] as $key => $value)
                @if (is_scalar($value))
                    <div class="col-6 col-xl-3">
                        <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body"><span class="text-muted small text-capitalize">{{ str_replace('_', ' ', $key) }}</span><div class="h4 fw-bold mt-2 mb-0">{{ str_contains($key, 'rate') ? number_format((float) $value, 1).'%' : (str_contains($key, 'total') || str_contains($key, 'paid') || str_contains($key, 'outstanding') || $key === 'average_payment' || $key === 'average_invoice' || $key === 'overdue' ? $money($value) : number_format((float) $value)) }}</div></div></div>
                    </div>
                @endif
            @endforeach
        </div>

        @if (isset($report['breakdown']['trend']))
            <div class="card border-0 shadow-sm rounded-4 mb-4"><div class="card-body"><h2 class="h5 fw-bold">{{ $filters->type === \App\ReportType::REVENUE ? 'Revenue trend' : 'Reminder activity' }}</h2><div style="height: 280px"><canvas id="reportTrendChart" aria-label="Report trend chart"></canvas></div></div></div>
        @endif

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-header bg-white border-0 pt-4 px-4"><h2 class="h5 fw-bold mb-1">{{ $report['title'] }} details</h2><p class="text-muted small mb-0">Showing the first 100 rows on screen. Use an export for the complete filtered dataset.</p></div>
            <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr>@foreach ($report['columns'] as $label)<th>{{ $label }}</th>@endforeach</tr></thead><tbody>
                @forelse ($report['rows'] as $row)
                    <tr>@foreach ($report['columns'] as $key => $label)<td>{{ $formatValue($key, $row[$key] ?? null) }}</td>@endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($report['columns']) }}" class="text-center text-muted py-5">No records match the selected filters.</td></tr>
                @endforelse
            </tbody></table></div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .report-custom-date { display: none; }
        .report-custom-date.is-visible { display: block; }
    </style>
@endpush

@push('scripts')
    @if (isset($report['breakdown']['trend']))
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const period = document.getElementById('report-period');
            const dateFields = document.querySelectorAll('.report-custom-date');
            const toggleDates = () => dateFields.forEach((field) => field.classList.toggle('is-visible', period?.value === 'custom'));
            period?.addEventListener('change', toggleDates);
            toggleDates();

            const canvas = document.getElementById('reportTrendChart');
            const trend = @json($report['breakdown']['trend'] ?? []);
            if (canvas && typeof Chart !== 'undefined') {
                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: trend.map((item) => item.label),
                        datasets: [{
                            label: '{{ $filters->type === \App\ReportType::REVENUE ? 'Payments received' : 'Reminder attempts' }}',
                            data: trend.map((item) => item.total ?? item.count),
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13, 110, 253, .08)',
                            fill: true,
                            tension: .3,
                        }],
                    },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } },
                });
            }
        });
    </script>
@endpush
