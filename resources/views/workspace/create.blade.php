@extends('layouts.app')

@section('page_title', 'New Workspace')

@section('content')
    <div class="container-fluid py-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="fs-4 fw-bold mb-1">Create workspace</h1>
                <p class="text-muted mb-0">Set up a workspace to organize your invoicing and collections.</p>
            </div>
            <a href="{{ route('workspace.index', [], false) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to workspaces
            </a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 px-4 py-3">
                <h2 class="h6 fw-bold mb-1">Workspace details</h2>
                <p class="text-muted small mb-0">Provide the basic information for your new workspace.</p>
            </div>

            <div class="card-body p-4">
                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <p class="fw-semibold mb-1">Please correct the following:</p>
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('workspace.store') }}" method="POST">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="workspace-name" class="form-label">Workspace name</label>
                            <input type="text" name="name" id="workspace-name"
                                class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name') }}" maxlength="255" placeholder="Enter workspace name" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="workspace-slug" class="form-label">Workspace slug</label>
                            <input type="text" name="slug" id="workspace-slug"
                                class="form-control @error('slug') is-invalid @enderror"
                                value="{{ old('slug') }}" maxlength="255" placeholder="Enter workspace slug" required>
                            @error('slug')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="workspace-subdomain" class="form-label">Subdomain</label>
                            <input type="text" name="subdomain" id="workspace-subdomain"
                                class="form-control @error('subdomain') is-invalid @enderror"
                                value="{{ old('subdomain') }}" maxlength="255"
                                placeholder="Enter workspace subdomain (optional)">
                            @error('subdomain')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Used for workspace-specific access where configured.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="workspace-currency" class="form-label">Default currency</label>
                            <input type="text" id="workspace-currency" class="form-control"
                                value="{{ $defaultCurrency ? $defaultCurrency->code.' - '.$defaultCurrency->name : 'System default' }}"
                                readonly>
                            <div class="form-text">New workspaces inherit your profile default currency.</div>
                        </div>

                        <div class="col-12">
                            <label for="workspace-metadata" class="form-label">Metadata</label>
                            <textarea name="metadata" id="workspace-metadata" rows="5"
                                class="form-control @error('metadata') is-invalid @enderror"
                                placeholder="{&quot;color&quot;:&quot;blue&quot;,&quot;timezone&quot;:&quot;UTC&quot;}">{{ old('metadata') }}</textarea>
                            @error('metadata')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Optional JSON metadata for the workspace.</div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-1"></i> Create workspace
                        </button>
                        <a href="{{ route('workspace.index', [], false) }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
