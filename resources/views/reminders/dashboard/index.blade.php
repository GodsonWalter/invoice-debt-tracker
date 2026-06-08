@extends('layouts.app')

@section('page_title', 'Reminder Dashboard')

@section('content')
    <div class="container-fluid py-4">
        <div class="mb-4">
            <h2 class="fs-3 fw-bold text-dark mb-2">Reminder Dashboard</h2>
            <p class="text-muted">Monitor reminder activity and system health</p>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-4 mb-4">
            <!-- Sent Card -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Total Sent</p>
                                <h3 class="text-success mb-0">{{ $statistics['total_sent'] }}</h3>
                            </div>
                            <div class="bg-success bg-opacity-10 rounded p-3">
                                <i class="fa-solid fa-check-circle text-success fs-4"></i>
                            </div>
                        </div>
                        <a href="{{ route('reminders.sent') }}" class="btn btn-sm btn-link btn-success ps-0 mt-3">
                            View sent <i class="fa-solid fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Failed Card -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Total Failed</p>
                                <h3 class="text-danger mb-0">{{ $statistics['total_failed'] }}</h3>
                            </div>
                            <div class="bg-danger bg-opacity-10 rounded p-3">
                                <i class="fa-solid fa-exclamation-circle text-danger fs-4"></i>
                            </div>
                        </div>
                        <a href="{{ route('reminders.failed') }}" class="btn btn-sm btn-link btn-danger ps-0 mt-3">
                            View failed <i class="fa-solid fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Upcoming Card -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Upcoming</p>
                                <h3 class="text-primary mb-0">{{ $statistics['upcoming_reminders'] }}</h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 rounded p-3">
                                <i class="fa-solid fa-clock text-primary fs-4"></i>
                            </div>
                        </div>
                        <a href="{{ route('reminders.upcoming') }}" class="btn btn-sm btn-link btn-primary ps-0 mt-3">
                            View upcoming <i class="fa-solid fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Today Activity Card -->
            <div class="col-12 col-md-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Today's Activity</p>
                                <h3 class="text-info mb-0">{{ $statistics['today_activity'] }}</h3>
                            </div>
                            <div class="bg-info bg-opacity-10 rounded p-3">
                                <i class="fa-solid fa-bolt text-info fs-4"></i>
                            </div>
                        </div>
                        <a href="{{ route('reminders.activity') }}" class="btn btn-sm btn-link btn-info ps-0 mt-3">
                            View activity <i class="fa-solid fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="fw-bold text-dark mb-0">Recent Activity</h5>
            </div>

            <div class="card-body p-0">
                @if ($recentActivity->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Invoice</th>
                                    <th>Client</th>
                                    <th>Schedule</th>
                                    <th>Status</th>
                                    <th>Sent At</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recentActivity as $log)
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
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5">
                        <i class="fa-solid fa-bell-slash fs-1 text-muted mb-3 d-block"></i>
                        <p class="text-muted">No recent activity</p>
                    </div>
                @endif
            </div>

            <div class="card-footer bg-white border-top py-3">
                <a href="{{ route('reminders.activity') }}" class="text-primary text-decoration-none fw-medium">
                    View all activity →
                </a>
            </div>
        </div>
    </div>
@endsection
