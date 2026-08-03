@extends('layouts.app')

@section('page_title', 'Edit Workspace')

@section('content')
    <div class="container-fluid py-2">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h1 class="fs-4 fw-bold mb-1">Edit workspace</h1>
                <p class="text-muted mb-0">Update the settings and identifying details for {{ $workspace->name }}.</p>
            </div>
            <a href="{{ route('workspace.index', [], false) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Back to workspaces
            </a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 px-4 py-3">
                <h2 class="h6 fw-bold mb-1">Workspace details</h2>
                <p class="text-muted small mb-0">Changes apply to this workspace and its future invoices.</p>
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

                <form action="{{ route('workspace.update', $workspace->id, false) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="workspace-name" class="form-label">Workspace name</label>
                            <input type="text" name="name" id="workspace-name"
                                class="form-control @error('name') is-invalid @enderror"
                                value="{{ old('name', $workspace->name) }}" maxlength="255" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="workspace-slug" class="form-label">Workspace slug</label>
                            <input type="text" name="slug" id="workspace-slug"
                                class="form-control @error('slug') is-invalid @enderror"
                                value="{{ old('slug', $workspace->slug) }}" maxlength="255" required>
                            @error('slug')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="workspace-subdomain" class="form-label">Subdomain</label>
                            <input type="text" name="subdomain" id="workspace-subdomain"
                                class="form-control @error('subdomain') is-invalid @enderror"
                                value="{{ old('subdomain', $workspace->subdomain) }}" maxlength="255"
                                placeholder="Enter workspace subdomain">
                            @error('subdomain')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="workspace-invoice-prefix" class="form-label">Invoice prefix</label>
                            <input type="text" name="invoice_prefix" id="workspace-invoice-prefix"
                                class="form-control @error('invoice_prefix') is-invalid @enderror"
                                value="{{ old('invoice_prefix', $workspace->invoice_prefix ?? '') }}"
                                maxlength="50" placeholder="e.g. TKE">
                            @error('invoice_prefix')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Generated invoices look like: TKE-2026-0001.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="workspace-currency" class="form-label">Workspace currency</label>
                            <select name="currency_id" id="workspace-currency"
                                class="form-select @error('currency_id') is-invalid @enderror" required>
                                @foreach ($currencies as $currency)
                                    <option value="{{ $currency->id }}"
                                        @selected((string) old('currency_id', $workspace->currency_id) === (string) $currency->id)>
                                        {{ $currency->code }} - {{ $currency->name }} ({{ $currency->symbol }})
                                    </option>
                                @endforeach
                            </select>
                            @error('currency_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">New invoices use this currency by default unless overridden.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="workspace-status" class="form-label">Status</label>
                            <select name="is_active" id="workspace-status"
                                class="form-select @error('is_active') is-invalid @enderror" required>
                                <option value="1" @selected(old('is_active', $workspace->is_active))>Active</option>
                                <option value="0" @selected(! old('is_active', $workspace->is_active))>Inactive</option>
                            </select>
                            @error('is_active')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="workspace-metadata" class="form-label">Metadata</label>
                            <textarea name="metadata" id="workspace-metadata" rows="5"
                                class="form-control @error('metadata') is-invalid @enderror"
                                placeholder="{&quot;color&quot;:&quot;blue&quot;,&quot;timezone&quot;:&quot;UTC&quot;}">{{ old('metadata', json_encode($workspace->metadata ?? [], JSON_PRETTY_PRINT)) }}</textarea>
                            @error('metadata')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Optional JSON metadata for the workspace.</div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Save changes
                        </button>
                        <a href="{{ route('workspace.show', [$workspace->id], false) }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
