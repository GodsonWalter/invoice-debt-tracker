@extends('layouts.app')
{{-- page title --}}
@section('page_title', 'Workspace Details')
@section('content')

<div class="container-fluid py-2">
    <div class="mb-4">
        <h2 class="fs-4 fw-bold text-dark mb-1">{{ 'Workspace Details' }}</h2>
        <p class="text-muted small mb-0">Review the workspace details below.</p>
    </div>

    <div class="card border border-light shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white p-4 border-bottom d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0 fs-6">Workspace Information</h5>
            <div class="d-flex gap-2">
                <a href="{{ route('workspace.index') }}" class="btn btn-sm btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
                @if ($workspace->canBeManagedBy(Auth::user()))
                    <a href="{{ route('workspace.edit', $workspace->id) }}" class="btn btn-sm btn-warning">
                        <i class="bi bi-pencil-square"></i> Edit Workspace
                    </a>
                @endif
            </div>
        </div>

        <div class="card-body p-3 p-md-4">
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <h6 class="mb-2">Name</h6>
                    <p class="mb-0">{{ $workspace->name }}</p>
                </div>

                <div class="col-12 col-md-6">
                    <h6 class="mb-2">Slug</h6>
                    <p class="mb-0">{{ $workspace->slug }}</p>
                </div>

                <div class="col-12 col-md-6">
                    <h6 class="mb-2">Subdomain</h6>
                    <p class="mb-0">{{ $workspace->subdomain ?? 'Not assigned' }}</p>
                </div>

                <div class="col-12 col-md-6">
                    <h6 class="mb-2">Invoice Prefix</h6>
                    <p class="mb-0">{{ $workspace->invoice_prefix ?? 'Not assigned' }}</p>
                </div>

                <div class="col-12 col-md-6">
                    <h6 class="mb-2">Status</h6>
                    <p class="mb-0">
                        @if ($workspace->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-secondary">Inactive</span>
                        @endif
                    </p>
                </div>

                <div class="col-12">
                    <h6 class="mb-2">Metadata</h6>
                    <pre class="bg-light p-3 rounded">{{ json_encode($workspace->metadata ?? [], JSON_PRETTY_PRINT) }}</pre>
                </div>

                <div class="col-12 col-md-6">
                    <h6 class="mb-2">Created At</h6>
                    <p class="mb-0">{{ $workspace->created_at->format('F j, Y, g:i a') }}</p>
                </div>

                <div class="col-12 col-md-6">
                    <h6 class="mb-2">Owner</h6>
                    <p class="mb-0">
                        @if ($workspace->owner)
                            {{ $workspace->owner->name }} ({{ $workspace->owner->email }})
                        @else
                            <span class="text-muted">No owner assigned</span>
                        @endif
                    </p>
                </div>

                <div class="col-12 col-md-6">
                    <h6 class="mb-2">Updated At</h6>
                    <p class="mb-0">{{ $workspace->updated_at->format('F j, Y, g:i a') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
