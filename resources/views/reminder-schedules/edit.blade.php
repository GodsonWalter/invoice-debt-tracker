@extends('layouts.app')

@section('page_title', 'Edit Reminder Schedule')

@section('content')
    <div class="container-fluid py-2">
        <div class="mb-4">
            <h2 class="fs-4 fw-bold text-dark mb-1">Edit Reminder Schedule</h2>
            <p class="text-muted small mb-0">Modify this reminder schedule.</p>
        </div>

        @include('reminder-schedules._form', [
            'workspace' => $workspace,
            'reminderSchedule' => $reminderSchedule,
            'action' => route('reminder-schedules.update', [$workspace, $reminderSchedule]),
            'method' => 'PUT',
            'submitLabel' => 'Update Schedule',
        ])
    </div>
@endsection
