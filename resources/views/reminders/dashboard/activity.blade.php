@extends('layouts.app')

@section('page_title', 'Reminder Activity')

@section('content')
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="mb-4 d-flex justify-content-between align-items-start">
            <div>
                <h2 class="fs-3 fw-bold text-dark mb-2">Reminder Activity</h2>
                <p class="text-muted">All reminder logs</p>
            </div>
            <a href="{{ route('reminders.index') }}" class="text-primary text-decoration-none fw-medium">
                ← Back to Dashboard
            </a>
        </div>

        <!-- Filters -->
        <form action="{{ route('reminders.activity') }}" method="GET" class="mb-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-lg-4">
                            <label class="form-label small fw-medium">Search</label>
                            <input type="text" name="search" value="{{ request('search') }}" 
                                placeholder="Invoice or Client..." 
                                class="form-control form-control-sm">
                        </div>

                        <div class="col-12 col-md-6 col-lg-4">
                            <label class="form-label small fw-medium">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="">All Statuses</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                                        {{ ucfirst($status) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-4 d-flex gap-2 align-items-end">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">
                                <i class="fa-solid fa-filter me-2"></i> Filter
                            </button>
                            <a href="{{ route('reminders.activity') }}" class="btn btn-outline-secondary btn-sm flex-grow-1">
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
                                    <th>Schedule</th>
                                    <th>Status</th>
                                    <th>Sent At</th>
                                    <th>Created At</th>
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
                                        <td>@include('reminders.dashboard._status_badge', ['status' => $log->status])</td>
                                        <td class="text-muted small">
                                            @if ($log->sent_at)
                                                {{ $log->sent_at->format('M d, Y H:i') }}
                                            @else
                                                <span class="text-secondary">-</span>
                                            @endif
                                        </td>
                                        <td class="text-muted small">{{ $log->created_at->format('M d, Y H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="fa-solid fa-inbox fs-1 text-muted mb-3 d-block"></i>
                        <p class="text-muted">No reminder activity found</p>
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
