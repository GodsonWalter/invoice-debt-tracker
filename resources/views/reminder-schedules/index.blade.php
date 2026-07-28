@extends('layouts.app')

@section('page_title', 'Reminder Schedules')

@section('content')
    <div class="container-fluid py-2">
        <div class="mb-4 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start">
            <div>
                <h2 class="fs-4 fw-bold text-dark mb-1">Reminder Schedules</h2>
                <p class="text-muted small mb-0">Manage when reminder emails are sent for this workspace.</p>
            </div>

            <a href="{{ route('reminder-schedules.create', $workspace) }}" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-plus"></i> Add Schedule
            </a>
        </div>

        <div class="card border-light shadow-sm rounded-4 overflow-hidden">
            <div class="card-header bg-white p-4 border-bottom">
                <h5 class="fw-bold text-dark mb-0 fs-6">Schedules</h5>
            </div>

            <div class="card-body p-3 p-md-4">
                @if ($reminderSchedules->isEmpty())
                    <div class="text-center py-5 text-muted">
                        <p class="mb-1">No reminder schedules found for this workspace.</p>
                        <a href="{{ route('reminder-schedules.create', $workspace) }}" class="btn btn-sm btn-primary">Create schedule</a>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Direction</th>
                                    <th>Days Offset</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($reminderSchedules as $schedule)
                                    <tr>
                                        <td class="fw-semibold">{{ $schedule->name }}</td>
                                        <td>
                                            <span class="badge bg-{{ $schedule->direction === 'before_due' ? 'primary' : 'danger' }} text-dark">
                                                {{ $schedule->direction === 'before_due' ? 'Before Due' : 'After Due' }}
                                            </span>
                                        </td>
                                        <td>{{ $schedule->days_offset }} day{{ $schedule->days_offset === 1 ? '' : 's' }}</td>
                                        <td>
                                            <span class="badge bg-{{ $schedule->is_active ? 'success' : 'secondary' }}">
                                                {{ $schedule->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('reminder-schedules.edit', [$workspace, $schedule]) }}" class="btn btn-sm btn-outline-secondary me-1" title="Edit">
                                                <i class="fa-solid fa-pencil"></i>
                                            </a>

                                            <form action="{{ route('reminder-schedules.toggle', [$workspace, $schedule]) }}" method="POST" class="d-inline-block">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-outline-{{ $schedule->is_active ? 'warning' : 'success' }} me-1" title="{{ $schedule->is_active ? 'Disable' : 'Enable' }}">
                                                    <i class="fa-solid fa-power-off"></i>
                                                </button>
                                            </form>

                                            <form action="{{ route('reminder-schedules.destroy', [$workspace, $schedule]) }}" method="POST" class="d-inline-block" data-delete-confirm>
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        {{ $reminderSchedules->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
