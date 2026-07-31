@php
    $isEditing = $workspace instanceof \App\Models\Workspace;
    $metadata = old('metadata', $isEditing ? json_encode($workspace->metadata ?? [], JSON_PRETTY_PRINT) : '');
    $selectedOwner = old('owner_id', $isEditing ? $workspace->owner_id : '');
    $selectedCurrency = old('currency_id', $isEditing ? $workspace->currency_id : '');
    $isActive = (bool) old('is_active', $isEditing ? $workspace->is_active : true);
@endphp

<form method="POST" action="{{ $formAction }}">
    @csrf
    @if ($formMethod !== 'POST')
        @method($formMethod)
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <p class="fw-semibold mb-1">Please correct the following:</p>
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-md-6">
            <label for="platform-workspace-name" class="form-label">Workspace name</label>
            <input id="platform-workspace-name" name="name" value="{{ old('name', $workspace?->name) }}"
                class="form-control @error('name') is-invalid @enderror" maxlength="255" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="platform-workspace-owner" class="form-label">Owner</label>
            <select id="platform-workspace-owner" name="owner_id" class="form-select @error('owner_id') is-invalid @enderror" required>
                <option value="">Select an active user</option>
                @foreach ($owners as $owner)
                    <option value="{{ $owner->id }}" @selected((string) $selectedOwner === (string) $owner->id)>
                        {{ $owner->name ?: 'Unnamed user' }} ({{ $owner->email }})
                    </option>
                @endforeach
            </select>
            @error('owner_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="platform-workspace-slug" class="form-label">Slug</label>
            <input id="platform-workspace-slug" name="slug" value="{{ old('slug', $workspace?->slug) }}"
                class="form-control @error('slug') is-invalid @enderror" maxlength="255" required>
            @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="platform-workspace-subdomain" class="form-label">Subdomain</label>
            <input id="platform-workspace-subdomain" name="subdomain" value="{{ old('subdomain', $workspace?->subdomain) }}"
                class="form-control @error('subdomain') is-invalid @enderror" maxlength="255">
            @error('subdomain')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="platform-workspace-currency" class="form-label">Currency</label>
            <select id="platform-workspace-currency" name="currency_id" class="form-select @error('currency_id') is-invalid @enderror">
                <option value="">Unspecified</option>
                @foreach ($currencies as $currency)
                    <option value="{{ $currency->id }}" @selected((string) $selectedCurrency === (string) $currency->id)>
                        {{ $currency->code }} - {{ $currency->name }} ({{ $currency->symbol }})
                    </option>
                @endforeach
            </select>
            @error('currency_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="platform-workspace-invoice-prefix" class="form-label">Invoice prefix</label>
            <input id="platform-workspace-invoice-prefix" name="invoice_prefix" value="{{ old('invoice_prefix', $workspace?->invoice_prefix ?: 'INV') }}"
                class="form-control @error('invoice_prefix') is-invalid @enderror" maxlength="50">
            @error('invoice_prefix')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label for="platform-workspace-metadata" class="form-label">Metadata</label>
            <textarea id="platform-workspace-metadata" name="metadata" rows="5" class="form-control @error('metadata') is-invalid @enderror">{{ $metadata }}</textarea>
            <div class="form-text">Optional JSON metadata. Tenant-specific business data is managed inside the workspace.</div>
            @error('metadata')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="platform-workspace-status" class="form-label">Status</label>
            <select id="platform-workspace-status" name="is_active" class="form-select @error('is_active') is-invalid @enderror" required>
                <option value="1" @selected($isActive)>Active</option>
                <option value="0" @selected(! $isActive)>Inactive</option>
            </select>
            @error('is_active')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
        <a href="{{ route('platform.workspaces.index') }}" class="btn btn-outline-secondary ms-2">Cancel</a>
    </div>
</form>
