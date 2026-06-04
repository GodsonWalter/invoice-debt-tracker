@extends('layouts.app')

@section('page_title', 'Edit Template')

@section('content')
    <div class="container-fluid py-2">
        <div class="mb-4">
            <h2 class="fs-4 fw-bold text-dark mb-1">Edit Reminder Template</h2>
            <p class="text-muted small mb-0">Update workspace reminder email copy.</p>
        </div>

        @include('email-templates._form', [
            'action' => route('email-templates.update', [$workspace, $emailTemplate]),
            'method' => 'PUT',
            'submitLabel' => 'Save Changes',
        ])
    </div>
@endsection
