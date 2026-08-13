<div class="card border-light shadow-sm rounded-4 overflow-hidden">
    <div class="card-body p-4">
        <form action="{{ $action }}" method="POST">
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif

            <div class="row g-4">
                <div class="col-12 col-xl-8">
                    <div class="mb-3">
                        <label class="form-label">Reminder Name</label>
                        <input type="text" name="name" value="{{ old('name', $reminderSchedule->name) }}" class="form-control @error('name') is-invalid @enderror" maxlength="100" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Direction</label>
                        <select name="direction" class="form-select @error('direction') is-invalid @enderror" required>
                            <option value="before_due" {{ old('direction', $reminderSchedule->direction) === 'before_due' ? 'selected' : '' }}>Before Due Date</option>
                            <option value="after_due" {{ old('direction', $reminderSchedule->direction) === 'after_due' ? 'selected' : '' }}>After Due Date</option>
                        </select>
                        @error('direction')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Days Offset</label>
                        <input type="number" name="days_offset" value="{{ old('days_offset', $reminderSchedule->days_offset) }}" class="form-control @error('days_offset') is-invalid @enderror" min="0" required>
                        <div class="form-text">Number of days before or after due date (depending on direction).</div>
                        @error('days_offset')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" value="1" {{ old('is_active', $reminderSchedule->is_active) ? 'checked' : '' }}>
                        <label class="form-check-label" for="isActive">Enable Reminder</label>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input type="hidden" name="include_invoice_pdf" value="0">
                        <input class="form-check-input" type="checkbox" name="include_invoice_pdf" id="includeInvoicePdf" value="1" {{ old('include_invoice_pdf', $reminderSchedule->include_invoice_pdf) ? 'checked' : '' }}>
                        <label class="form-check-label" for="includeInvoicePdf">Attach latest invoice PDF</label>
                        <div class="form-text">Include the current invoice PDF with this reminder email.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
                        <a href="{{ route('reminder-schedules.index', $workspace) }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </div>

                <div class="col-12 col-xl-4">
                    <div class="card border-light shadow-sm rounded-4">
                        <div class="card-header bg-white p-3 border-bottom">
                            <h5 class="fw-bold text-dark mb-0 fs-6">Preview</h5>
                        </div>
                        <div class="card-body p-3">
                            <div class="mb-3">
                                <p class="text-muted small mb-2">Reminder Type</p>
                                <p id="preview-type" class="text-dark fw-semibold mb-0">-</p>
                            </div>
                            <div>
                                <p class="text-muted small mb-2">Description</p>
                                <p id="preview-desc" class="text-dark mb-0">-</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script>
        const directionSelect = document.querySelector('select[name="direction"]');
        const daysOffsetInput = document.querySelector('input[name="days_offset"]');
        const previewType = document.getElementById('preview-type');
        const previewDesc = document.getElementById('preview-desc');

        function updatePreview() {
            const direction = directionSelect.value;
            const days = parseInt(daysOffsetInput.value) || 0;

            let type = '';
            let desc = '';

            if (direction === 'before_due') {
                if (days === 0) {
                    type = 'Due Today';
                    desc = 'Send reminder on the due date.';
                } else {
                    type = 'Before Due';
                    desc = `Send reminder ${days} day${days !== 1 ? 's' : ''} before due date.`;
                }
            } else if (direction === 'after_due') {
                type = 'Overdue';
                desc = `Send reminder ${days} day${days !== 1 ? 's' : ''} after due date.`;
            }

            previewType.textContent = type;
            previewDesc.textContent = desc;
        }

        directionSelect.addEventListener('change', updatePreview);
        daysOffsetInput.addEventListener('input', updatePreview);

        updatePreview();
    </script>
@endpush
