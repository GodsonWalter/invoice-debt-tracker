@extends('layouts.app')
{{-- page title --}}
@section('page_title', 'Edit Workspace')
@section('content')

<div class="container-fluid py-2">
    <div class="mb-4">
        <h2 class="fs-4 fw-bold text-dark mb-1">{{ 'Edit Workspace' }}</h2>
        <p class="text-muted small mb-0">Update the workspace details below.</p>
    </div>

    <div class="card border border-light shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white p-4 border-bottom d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0 fs-6">Workspace Details</h5>
            <a href="{{ route('workspace.index') }}" class="btn btn-sm btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>

        <div class="card-body p-3 p-md-4">
            <form action="{{ route('workspace.update', $workspace->id) }}" method="post">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label for="name" class="form-label">Workspace Name</label>
                    <input type="text" name="name" id="name"
                        class="form-control @error('name') is-invalid @enderror"
                        value="{{ old('name', $workspace->name) }}" placeholder="Enter workspace name" required>
                    @error('name')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="slug" class="form-label">Workspace Slug</label>
                    <input type="text" name="slug" id="slug"
                        class="form-control @error('slug') is-invalid @enderror"
                        value="{{ old('slug', $workspace->slug) }}" placeholder="Enter workspace slug" required>
                    @error('slug')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="subdomain" class="form-label">Subdomain</label>
                    <input type="text" name="subdomain" id="subdomain"
                        class="form-control @error('subdomain') is-invalid @enderror"
                        value="{{ old('subdomain', $workspace->subdomain) }}" placeholder="Enter workspace subdomain">
                    @error('subdomain')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="invoice_prefix" class="form-label">Invoice Prefix</label>
                    <input type="text" name="invoice_prefix" id="invoice_prefix" 
                        class="form-control @error('invoice_prefix') is-invalid @enderror"
                        value="{{ old('invoice_prefix', $workspace->invoice_prefix ?? '') }}"
                        placeholder="e.g. TKE">
                    @error('invoice_prefix')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Generated invoices look like: TKE-2026-0001</div>
                </div>

                <div class="mb-3">
                    <label for="currency_id" class="form-label">Workspace Currency</label>
                    <select name="currency_id" id="currency_id" class="form-select @error('currency_id') is-invalid @enderror" required>
                        @foreach ($currencies as $currency)
                            <option value="{{ $currency->id }}" {{ (string) old('currency_id', $workspace->currency_id) === (string) $currency->id ? 'selected' : '' }}>
                                {{ $currency->code }} - {{ $currency->name }} ({{ $currency->symbol }})
                            </option>
                        @endforeach
                    </select>
                    @error('currency_id')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                    <div class="form-text">New invoices use this currency by default unless overridden.</div>
                </div>

                <div class="mb-3">
                    <label for="metadata" class="form-label">Metadata</label>
                    <textarea name="metadata" id="metadata" rows="4"
                        class="form-control @error('metadata') is-invalid @enderror"
                        placeholder="{"color":"blue","timezone":"UTC"}">{{ old('metadata', json_encode($workspace->metadata ?? [], JSON_PRETTY_PRINT)) }}</textarea>
                    @error('metadata')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="is_active" class="form-label">Is Active?</label>
                    <select name="is_active" id="is_active" class="form-select @error('is_active') is-invalid @enderror" required>
                        <option value="1" {{ old('is_active', $workspace->is_active) ? 'selected' : '' }}>Yes</option>
                        <option value="0" {{ ! old('is_active', $workspace->is_active) ? 'selected' : '' }}>No</option>
                    </select>
                    @error('is_active')
                        <div class="text-danger">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-grid d-md-block mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i> Update Workspace
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
