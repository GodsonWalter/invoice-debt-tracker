@php
    $templateTypes = [
        \App\Models\EmailTemplate::TYPE_BEFORE_DUE => 'Before Due',
        \App\Models\EmailTemplate::TYPE_DUE_TODAY => 'Due Today',
        \App\Models\EmailTemplate::TYPE_OVERDUE => 'Overdue',
    ];
@endphp

<form method="POST" action="{{ $action }}">
    @csrf

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card border-light shadow-sm rounded-4">
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label fw-semibold">Template Name</label>
                            <input
                                type="text"
                                id="name"
                                name="name"
                                value="{{ old('name', $emailTemplate->name) }}"
                                class="form-control @error('name') is-invalid @enderror"
                                required
                            >
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="type" class="form-label fw-semibold">Template Type</label>
                            <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
                                @foreach ($templateTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('type', $emailTemplate->type) === $value)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="subject" class="form-label fw-semibold">Subject</label>
                            <input
                                type="text"
                                id="subject"
                                name="subject"
                                value="{{ old('subject', $emailTemplate->subject) }}"
                                class="form-control @error('subject') is-invalid @enderror"
                                required
                            >
                            @error('subject')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="body" class="form-label fw-semibold">Body</label>
                            <textarea
                                id="body"
                                name="body"
                                rows="14"
                                class="form-control font-monospace @error('body') is-invalid @enderror"
                                required
                            >{{ old('body', $emailTemplate->body) }}</textarea>
                            @error('body')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6">
                            <label for="invoice_id" class="form-label fw-semibold">Preview Invoice</label>
                            <select id="invoice_id" name="invoice_id" class="form-select @error('invoice_id') is-invalid @enderror">
                                <option value="">Select invoice for preview</option>
                                @foreach ($invoices as $invoice)
                                    <option value="{{ $invoice->id }}" @selected((string) old('invoice_id') === (string) $invoice->id)>
                                        {{ $invoice->invoice_number }} - {{ $invoice->client?->name ?? 'No client' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('invoice_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_active" value="0">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    role="switch"
                                    id="is_active"
                                    name="is_active"
                                    value="1"
                                    @checked((bool) old('is_active', $emailTemplate->is_active ?? true))
                                >
                                <label class="form-check-label" for="is_active">Active</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-white border-top p-4 d-flex flex-wrap gap-2 justify-content-between">
                    <a href="{{ route('email-templates.index', $workspace) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" formaction="{{ route('email-templates.preview', $workspace) }}" formmethod="POST" class="btn btn-outline-primary">
                            <i class="bi bi-eye"></i> Preview Template
                        </button>

                        @if ($method === 'PUT')
                            <button type="submit" name="_method" value="PUT" class="btn btn-primary">
                                <i class="bi bi-check2"></i> {{ $submitLabel }}
                            </button>
                        @else
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check2"></i> {{ $submitLabel }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            @if ($preview)
                <div class="card border-light shadow-sm rounded-4 mt-4">
                    <div class="card-header bg-white p-4 border-bottom">
                        <h5 class="fw-bold text-dark mb-0 fs-6">Preview for Invoice {{ $preview['invoice_number'] }}</h5>
                    </div>
                    <div class="card-body p-4">
                        <p class="small text-muted mb-1">Subject</p>
                        <div class="border rounded-3 p-3 bg-light mb-4">{{ $preview['subject'] }}</div>

                        <p class="small text-muted mb-1">Body</p>
                        <div class="border rounded-3 p-3 bg-light" style="white-space: pre-wrap;">{{ $preview['body'] }}</div>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-12 col-xl-4">
            <div class="card border-light shadow-sm rounded-4">
                <div class="card-header bg-white p-4 border-bottom">
                    <h5 class="fw-bold text-dark mb-0 fs-6">Available Placeholders</h5>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($placeholders as $placeholder)
                            <code class="border rounded-3 bg-light px-2 py-1">{{ $placeholder }}</code>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
