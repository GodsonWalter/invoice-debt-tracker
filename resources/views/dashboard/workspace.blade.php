@extends('layouts.app')

@section('content')
    <div class="container-fluid py-4">

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">Dashboard Overview</h2>
                <p class="text-muted mb-0">
                    Welcome back {{ Auth::user()->name }}!. Here's a snapshot of your business performance.
                </p>

            </div>



            <div>
                <button class="btn btn-primary">
                    <i class="bi bi-plus-lg"></i>
                    Create Invoice
                </button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-4 mb-4">

            <div class="col-md-6 col-xl-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <small class="text-muted">Total Revenue</small>
                        <h3 class="fw-bold mt-2 mb-0">
                            {{ $workspace->formatMoney($revenue['total_revenue']) }}
                        </h3>

                        <span class="text-muted small">
                            Total paid invoices revenue
                        </span>
                        <span class="text-success small">
                            ↑ 18% from last month
                        </span>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <small class="text-muted">Outstanding Debt</small>
                        <h3 class="fw-bold mt-2 mb-0">
                            {{ $workspace->formatMoney($debt['total_debt']) }}
                        </h3>

                        <span class="text-danger small">
                            {{ $debt['unpaid_count'] }} unpaid {{ Str::plural('invoice', $debt['unpaid_count']) }}<br>
                            
                            {{ $debt['customers_owing'] }} {{ Str::plural('customer', $debt['customers_owing']) }} owing
                        </span>

                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <small class="text-muted">Overdue Invoices</small>
                        <h3 class="fw-bold mt-2 mb-0">
                            {{ $workspace->formatMoney($overdue['overdue_amount']) }}
                        </h3>

                        <span class="text-warning small">
                            {{ $overdue['overdue_count'] }} overdue 
                            {{ Str::plural('invoice', $overdue['overdue_count'] ) }}
                        </span>

                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <small class="text-muted">Customers</small>
                        <h3 class="fw-bold mt-2 mb-0">
                            {{ $debt['customers_owing'] }}
                        </h3>

                        <span class="text-muted small">
                            Customers with outstanding balances this month
                        </span>

                    </div>
                </div>
            </div>

        </div>

        <!-- Revenue Chart + Reminder Stats -->
        <div class="row g-4 mb-4">

            <div class="col-lg-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Revenue Trend</h5>
                    </div>

                    <div class="card-body">

                        <div style="height:300px;">
                            <canvas id="revenueChart"></canvas>
                        </div>

                    </div>
                </div>
            </div>

            <div class="col-lg-4">

                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Reminder Statistics</h5>
                    </div>

                    <div class="card-body">

                        <div class="d-flex justify-content-between mb-3">
                            <span>Sent</span>
                            <strong>{{ $reminderStats['sent'] ?? '-' }}</strong>
                        </div>

                        <div class="d-flex justify-content-between mb-3">
                            <span>Failed</span>
                            <strong class="text-danger">
                                {{ $reminderStats['failed'] ?? '-' }}
                            </strong>
                        </div>

                        <div class="d-flex justify-content-between">
                            <span>Success Rate</span>
                            <strong class="text-success">
                                {{ $reminderStats['success_rate'] ?? '-'}}%
                            </strong>
                        </div>

                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Top Debtors</h5>
                    </div>

                    <div class="card-body">

                        @forelse($topDebtors as $debtor)

                            <div class="mb-3">

                                <strong>
                                    {{ $debtor->name }}
                                </strong>

                                <div class="text-danger">
                                    {{ $workspace->formatMoney($debtor->total_debt) }}
                                </div>

                            </div>

                        @empty

                            <p class="text-muted mb-0">
                                No debtors found.
                            </p>

                        @endforelse

                    </div>
                </div>

            </div>

        </div>

        <!-- Outstanding Invoices -->
        <div class="card shadow-sm border-0 mb-4">

            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Outstanding Invoices</h5>

                <a href="#" class="btn btn-sm btn-outline-primary">
                    View All
                </a>
            </div>

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">
                        <tr>
                            <th>Invoice No</th>
                            <th>Customer</th>
                            <th>Due Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse($outstandingInvoices as $invoice)

                            <tr>

                                <td>
                                    {{ $invoice->invoice_number }}
                                </td>

                                <td>
                                    {{ $invoice->client->name }}
                                </td>

                                <td>
                                    {{ $invoice->due_date->format('d M Y') }}
                                </td>

                                <td>
                                    <small class="fw-bold">Total: {{ $invoice->formatMoney($invoice->total_amount) }}</small>
                                    <br> <small class="text-success">Paid: {{ $invoice->formatMoney($invoice->total_paid) }}</small>
                                    <br> <small class="text-danger">Balance: {{ $invoice->formatMoney($invoice->remaining_balance) }}</small>
                                </td>

                                <td>

                                    @if($invoice->status === 'overdue')

                                        <span class="badge bg-danger">
                                            Overdue
                                        </span>

                                    @else

                                        <span class="badge bg-warning text-dark">
                                            Pending
                                        </span>

                                    @endif

                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    No outstanding invoices found.
                                </td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>
        </div>

        <!-- Recent Activities -->
        <div class="card shadow-sm border-0">

            <div class="card-header bg-white">
                <h5 class="mb-0">Recent Activity</h5>
            </div>

            <div class="card-body">

                <ul class="list-group list-group-flush">

                    @forelse($activities as $activity)

                        <li class="list-group-item">

                            {{ $activity->description }}

                            <small class="text-muted d-block">
                                {{ $activity->created_at->diffForHumans() }}
                            </small>

                        </li>

                    @empty

                        <li class="list-group-item text-center text-muted">
                            {{-- No recent activities found. --}}
                            Recent activities coming soon...
                        </li>

                    @endforelse

                </ul>

            </div>

        </div>

    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        new Chart(document.getElementById('revenueChart'), {
            type: 'line',
            data: {
                labels: [
                    'Jan', 'Feb', 'Mar', 'Apr',
                    'May', 'Jun', 'Jul', 'Aug',
                    'Sep', 'Oct', 'Nov', 'Dec'
                ],
                datasets: [{
                    label: 'Revenue',
                    data: [
                        500000,
                        800000,
                        1200000,
                        1000000,
                        1500000,
                        1800000,
                        1700000,
                        2000000,
                        2200000,
                        2500000,
                        2300000,
                        2800000
                    ],
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    </script>
@endpush