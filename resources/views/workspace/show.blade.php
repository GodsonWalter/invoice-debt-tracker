@extends('layouts.app')

@section('page_title', 'Workspace Details')

@section('content')
    @php
        $isActiveWorkspace = ($currentWorkspace ?? null)?->is($workspace);
    @endphp

    <div class="container-fluid py-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="fs-4 fw-bold mb-1">{{ $workspace->name }}</h1>
                <p class="text-muted mb-0">Review workspace configuration, access details, and current status.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('workspace.index', [], false) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to workspaces
                </a>
                @if ($isActiveWorkspace && $workspace->canBeManagedBy(Auth::user()))
                    <a href="{{ route('workspace.edit', $workspace->id, false) }}" class="btn btn-primary">
                        <i class="bi bi-pencil-square me-1"></i> Edit workspace
                    </a>
                @endif
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12 col-xl-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 px-4 py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h2 class="h6 fw-bold mb-1">Workspace information</h2>
                            <p class="text-muted small mb-0">Core details used across this workspace.</p>
                        </div>
                        @if ($workspace->is_active)
                            <span class="badge text-bg-success">Active</span>
                        @else
                            <span class="badge text-bg-secondary">Inactive</span>
                        @endif
                    </div>

                    <div class="card-body px-4 pb-4">
                        <div class="row g-4">
                            <div class="col-12 col-md-6">
                                <span class="text-uppercase text-muted small fw-semibold">Workspace name</span>
                                <p class="fw-semibold mb-0 mt-1">{{ $workspace->name }}</p>
                            </div>
                            <div class="col-12 col-md-6">
                                <span class="text-uppercase text-muted small fw-semibold">Slug</span>
                                <p class="mb-0 mt-1">{{ $workspace->slug }}</p>
                            </div>
                            <div class="col-12 col-md-6">
                                <span class="text-uppercase text-muted small fw-semibold">Subdomain</span>
                                <p class="mb-0 mt-1">{{ $workspace->subdomain ?: 'Not assigned' }}</p>
                            </div>
                            <div class="col-12 col-md-6">
                                <span class="text-uppercase text-muted small fw-semibold">Invoice prefix</span>
                                <p class="mb-0 mt-1">{{ $workspace->invoice_prefix ?: 'Not assigned' }}</p>
                            </div>
                            <div class="col-12 col-md-6">
                                <span class="text-uppercase text-muted small fw-semibold">Currency</span>
                                <p class="mb-0 mt-1">
                                    {{ $workspace->currency?->code ?? 'Not assigned' }}
                                    @if ($workspace->currency?->symbol)
                                        <span class="text-muted">({{ $workspace->currency->symbol }})</span>
                                    @endif
                                </p>
                            </div>
                            <div class="col-12 col-md-6">
                                <span class="text-uppercase text-muted small fw-semibold">Created</span>
                                <p class="mb-0 mt-1">{{ $workspace->created_at?->format('F j, Y, g:i a') ?? 'Not available' }}</p>
                            </div>
                            <div class="col-12 col-md-6">
                                <span class="text-uppercase text-muted small fw-semibold">Last updated</span>
                                <p class="mb-0 mt-1">{{ $workspace->updated_at?->format('F j, Y, g:i a') ?? 'Not available' }}</p>
                            </div>
                            <div class="col-12 col-md-6">
                                <span class="text-uppercase text-muted small fw-semibold">Owner</span>
                                @if ($workspace->owner)
                                    <p class="mb-0 mt-1 fw-semibold">{{ $workspace->owner->name }}</p>
                                    <p class="text-muted small mb-0">{{ $workspace->owner->email }}</p>
                                @else
                                    <p class="text-muted mb-0 mt-1">No owner assigned</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white border-0 px-4 py-3">
                        <h2 class="h6 fw-bold mb-1">Workspace metadata</h2>
                        <p class="text-muted small mb-0">Optional configuration stored for this workspace.</p>
                    </div>
                    <div class="card-body px-4">
                        <pre class="bg-light border rounded-3 p-3 mb-0 small overflow-auto">{{ json_encode($workspace->metadata ?? [], JSON_PRETTY_PRINT) }}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
