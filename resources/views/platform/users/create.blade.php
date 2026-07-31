@extends('layouts.app')

@section('page_title', 'Add Platform User')

@section('content')
    <div class="container-fluid py-2">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fs-4 fw-bold mb-1">Add Platform User</h2>
                <p class="text-muted mb-0">Create a global account. Workspace membership is managed separately.</p>
            </div>
            <a href="{{ route('platform.users.index') }}" class="btn btn-outline-secondary">Back</a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('platform.users.store') }}" class="row g-3">
                    @csrf
                    @include('platform.users._form', ['submitLabel' => 'Create user', 'passwordRequired' => true])
                </form>
            </div>
        </div>
    </div>
@endsection
