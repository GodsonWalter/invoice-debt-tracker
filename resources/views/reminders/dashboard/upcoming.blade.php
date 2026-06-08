@extends('layouts.app')

@section('page_title', 'Upcoming Reminders')

@section('content')
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="mb-4 d-flex justify-content-between align-items-start">
            <div>
                <h2 class="fs-3 fw-bold text-dark mb-2">Upcoming Reminders</h2>
                <p class="text-muted">Invoices due for reminder processing</p>
            </div>
            <a href="{{ route('reminders.index') }}" class="text-primary text-decoration-none fw-medium">
                ← Back to Dashboard
            </a>
        </div>

        <!-- Filters -->
        <form action="{{ route('reminders.upcoming') }}" method="GET" class="mb-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-lg-5">
                            <label class="form-label small fw-medium">Search</label>
                            <input type="text" name="search" value="{{ request('search') }}" 
                                placeholder="Invoice number or Client..." 
                                class="form-control form-control-sm">
                        </div>

                        <div class="col-12 col-md-6 col-lg-4 d-flex gap-2 align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                <i class="fa-solid fa-filter me-2"></i> Filter
                            </button>
                            <a href="{{ route('reminders.upcoming') }}" class="btn btn-outline-secondary btn-sm flex-grow-1">
                                Reset
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <!-- Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                @if ($invoices->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Invoice Number</th>
                                    <th>Client</th>
                                    <th>Due Date</th>
                                    <th>Amount</th>
                                    <th>Days Until Due</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoices as $invoice)
                                    <tr>
                                        <td>
                                            <a href="{{ route('invoices.show', [$invoice->workspace_id, $invoice->id]) }}" class="text-primary text-decoration-none fw-medium">
                                                {{ $invoice->invoice_number }}
                                            </a>
                                        </td>
                                        <td>{{ $invoice->client->name }}</td>
                                        <td>
                                            <span class="text-muted small">
                                                {{ $invoice->due_date?->format('M d, Y') ?? '-' }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-medium">
                                                {{ $invoice->currency?->symbol ?? '' }}{{ number_format($invoice->total_amount, 2) }}
                                            </span>
                                        </td>
                                        <td>
                                            @php
                                                $daysUntilDue = now()->startOfDay()->diffInDays($invoice->due_date?->startOfDay());
                                                $badgeClass = $daysUntilDue <= 0 ? 'bg-danger' : ($daysUntilDue <= 3 ? 'bg-warning' : 'bg-info');
                                            @endphp
                                            <span class="badge {{ $badgeClass }}">
                                                @if ($daysUntilDue < 0)
                                                    {{ abs($daysUntilDue) }} days overdue
                                                @elseif ($daysUntilDue === 0)
                                                    Due today
                                                @else
                                                    {{ $daysUntilDue }} day{{ $daysUntilDue > 1 ? 's' : '' }}
                                                @endif
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="fa-solid fa-check-double fs-1 text-muted mb-3 d-block"></i>
                        <p class="text-muted">No upcoming reminders</p>
                    </div>
                @endif
            </div>

            <!-- Pagination -->
            @if ($invoices->count())
                <div class="card-footer bg-white border-top py-3">
                    {{ $invoices->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
