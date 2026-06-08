@extends('layouts.app')

@section('page_title', 'Failed Reminders')

@section('content')
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="mb-4 d-flex justify-content-between align-items-start">
            <div>
                <h2 class="fs-3 fw-bold text-dark mb-2">Failed Reminders</h2>
                <p class="text-muted">Reminders that failed to send</p>
            </div>
            <a href="{{ route('reminders.index') }}" class="text-primary text-decoration-none fw-medium">
                ← Back to Dashboard
            </a>
        </div>

        <!-- Filters -->
        <form action="{{ route('reminders.failed') }}" method="GET" class="mb-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label small fw-medium">Search</label>
                            <input type="text" name="search" value="{{ request('search') }}" 
                                placeholder="Invoice or Client..." 
                                class="form-control form-control-sm">
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label small fw-medium">Start Date</label>
                            <input type="date" name="start_date" value="{{ request('start_date') }}" 
                                class="form-control form-control-sm">
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label small fw-medium">End Date</label>
                            <input type="date" name="end_date" value="{{ request('end_date') }}" 
                                class="form-control form-control-sm">
                        </div>

                        <div class="col-12 col-md-6 col-lg-3 d-flex gap-2 align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                <i class="fa-solid fa-filter me-2"></i> Filter
                            </button>
                            <a href="{{ route('reminders.failed') }}" class="btn btn-outline-secondary btn-sm flex-grow-1">
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
                @if ($reminders->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Invoice</th>
                                    <th>Client</th>
                                    <th>Reminder Schedule</th>
                                    <th>Error Message</th>
                                    <th>Failed At</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reminders as $log)
                                    <tr>
                                        <td>
                                            <a href="{{ route('invoices.show', [$log->invoice->workspace_id, $log->invoice->id]) }}" class="text-primary text-decoration-none fw-medium">
                                                {{ $log->invoice->invoice_number }}
                                            </a>
                                        </td>
                                        <td>{{ $log->invoice->client->name }}</td>
                                        <td class="text-muted small">{{ $log->reminderSchedule->name }}</td>
                                        <td>
                                            <span class="text-muted small text-truncate d-inline-block" style="max-width: 200px;" title="{{ $log->error_message }}">
                                                {{ $log->error_message ?? '-' }}
                                            </span>
                                        </td>
                                        <td class="text-muted small">{{ $log->created_at->format('M d, Y H:i') }}</td>
                                        <td>
                                            <form action="{{ route('reminders.retry', $log) }}" method="POST" class="d-inline-block">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-primary" title="Retry sending">
                                                    <i class="fa-solid fa-rotate-right"></i> Retry
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="fa-solid fa-circle-check fs-1 text-success mb-3 d-block opacity-50"></i>
                        <p class="text-muted">No failed reminders</p>
                    </div>
                @endif
            </div>

            <!-- Pagination -->
            @if ($reminders->count())
                <div class="card-footer bg-white border-top py-3">
                    {{ $reminders->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
