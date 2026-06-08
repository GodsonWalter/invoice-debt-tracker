@extends('layouts.app')

@section('page_title', 'Create Reminder Schedule')

@section('content')
    <div class="container-fluid py-2">
        <div class="mb-4">
            <h2 class="fs-4 fw-bold text-dark mb-1">Create Reminder Schedule</h2>
            <p class="text-muted small mb-0">Add a new reminder schedule for this workspace.</p>
        </div>

        @include('reminder-schedules._form', [
            'workspace' => $workspace,
            'reminderSchedule' => $reminderSchedule,
            'action' => route('reminder-schedules.store', $workspace),
            'method' => 'POST',
            'submitLabel' => 'Create Schedule',
        ])
    </div>
@endsection
