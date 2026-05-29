@csrf

@isset($currency)
    @method('PUT')
@endisset

<div class="row g-3">
    <div class="col-12 col-md-4">
        <label for="code" class="form-label">Currency Code</label>
        <input
            id="code"
            name="code"
            type="text"
            maxlength="3"
            value="{{ old('code', $currency->code ?? '') }}"
            class="form-control text-uppercase @error('code') is-invalid @enderror"
            placeholder="USD"
            {{ isset($currency) ? 'readonly' : 'required' }}
        >
        @error('code')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        @isset($currency)
            <div class="form-text">Currency codes are locked after creation.</div>
        @endisset
    </div>

    <div class="col-12 col-md-4">
        <label for="symbol" class="form-label">Symbol</label>
        <input
            id="symbol"
            name="symbol"
            type="text"
            maxlength="10"
            value="{{ old('symbol', $currency->symbol ?? '') }}"
            class="form-control @error('symbol') is-invalid @enderror"
            placeholder="$"
            required
        >
        @error('symbol')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-md-4">
        <label for="name" class="form-label">Currency Name</label>
        <input
            id="name"
            name="name"
            type="text"
            value="{{ old('name', $currency->name ?? '') }}"
            class="form-control @error('name') is-invalid @enderror"
            placeholder="US Dollar"
            required
        >
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <input type="hidden" name="is_active" value="0">
        <div class="form-check form-switch">
            <input
                id="is_active"
                name="is_active"
                type="checkbox"
                value="1"
                class="form-check-input @error('is_active') is-invalid @enderror"
                {{ old('is_active', $currency->is_active ?? true) ? 'checked' : '' }}
            >
            <label for="is_active" class="form-check-label">Active</label>
            @error('is_active')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

<div class="d-flex gap-2 mt-4">
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-save"></i> Save Currency
    </button>
    <a href="{{ route('currencies.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>

@push('scripts')
    <script>
        document.getElementById('code')?.addEventListener('input', function () {
            this.value = this.value.toUpperCase();
        });
    </script>
@endpush
