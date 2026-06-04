@extends('layouts.app')

@section('page_title', 'Create Template')

@section('content')
    <div class="container-fluid py-2">
        <div class="mb-4">
            <h2 class="fs-4 fw-bold text-dark mb-1">Create Reminder Template</h2>
            <p class="text-muted small mb-0">Create a workspace reminder email template.</p>
        </div>

        @include('email-templates._form', [
            'action' => route('email-templates.store', $workspace),
            'method' => 'POST',
            'submitLabel' => 'Create Template',
        ])
    </div>
@endsection
