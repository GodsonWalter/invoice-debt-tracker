@extends('layouts.app')

@section('page_title', 'Create Platform Workspace')

@section('content')
    <div class="container-fluid py-2">
        <div class="mb-4">
            <h1 class="fs-4 fw-bold mb-1">Create Platform Workspace</h1>
            <p class="text-muted mb-0">Create a workspace and assign it to an active application user.</p>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-4 px-4">
                <a href="{{ route('platform.workspaces.index') }}" class="btn btn-sm btn-outline-secondary">Back to workspaces</a>
            </div>
            <div class="card-body px-4 pb-4">
                @include('platform.workspaces._form', [
                    'formAction' => route('platform.workspaces.store'),
                    'formMethod' => 'POST',
                    'submitLabel' => 'Create workspace',
                    'workspace' => null,
                ])
            </div>
        </div>
    </div>
@endsection
