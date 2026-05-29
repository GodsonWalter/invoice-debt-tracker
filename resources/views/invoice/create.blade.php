@extends('layouts.app')

@section('page_title', 'Create Invoice')

@section('content')
    <div class="container-fluid py-2">
        <div class="mb-4 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start">
            <div>
                <h2 class="fs-4 fw-bold text-dark mb-1">Create Invoice</h2>
                <p class="text-muted small mb-0">Add an invoice for <strong>{{ $workspace->name }}</strong>.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('invoices.index', $workspace) }}" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to Invoices
                </a>
            </div>
        </div>

        <div class="card border-light shadow-sm rounded-4 overflow-hidden">
            <div class="card-body p-3 p-md-4">
                <form method="POST" action="{{ route('invoices.store', $workspace) }}" id="invoice-form">
                    @csrf

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Invoice Number</label>
                            <input type="text" name="invoice_number" class="form-control" value="{{ $invoiceNumber }}"
                                readonly>
                            <div class="form-text">Auto-generated for this workspace.</div>
                            @error('invoice_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                        </div>


                        <div class="col-12 col-md-6">
                            <label class="form-label">Client</label>
                            <select name="client_id" class="form-select @error('client_id') is-invalid @enderror" required>
                                <option value="" disabled selected>Select client</option>
                                @foreach($clients as $client)
                                    <option value="{{ $client->id }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>
                                        {{ $client->name }}</option>
                                @endforeach
                            </select>
                            @error('client_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">Currency</label>
                            <select name="currency_id" class="form-select @error('currency_id') is-invalid @enderror" required>
                                @foreach ($currencies as $currency)
                                    <option value="{{ $currency->id }}" {{ (string) old('currency_id', $defaultCurrency?->id) === (string) $currency->id ? 'selected' : '' }}>
                                        {{ $currency->code }} - {{ $currency->name }} ({{ $currency->symbol }})
                                    </option>
                                @endforeach
                            </select>
                            @error('currency_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">Issue Date</label>
                            <input type="date" name="issue_date"
                                class="form-control @error('issue_date') is-invalid @enderror"
                                value="{{ old('issue_date') }}" required>
                            @error('issue_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">Due Date</label>
                            <input type="date" name="due_date" class="form-control @error('due_date') is-invalid @enderror"
                                value="{{ old('due_date') }}" required>
                            @error('due_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select @error('status') is-invalid @enderror">
                                @foreach(['draft', 'sent', 'paid', 'overdue'] as $st)
                                    <option value="{{ $st }}" {{ old('status', 'draft') == $st ? 'selected' : '' }}>
                                        {{ ucfirst($st) }}</option>
                                @endforeach
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <hr>
                            <h5 class="fw-bold mb-3">Line Items</h5>

                            <div id="items-container">
                                <div class="item-row border rounded-3 p-3 mb-3" data-index="0">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-12 col-md-3">
                                            <button type="button" class="btn btn-link p-0 ms-auto text-danger remove-item"
                                                title="Remove" aria-label="Remove row">×</button>
                                            <label class="form-label">Item Name</label>
                                            <input type="text" name="items[0][item_name]" class="form-control"
                                                value="{{ old('items.0.item_name') }}" required>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label">Description</label>
                                            <input type="text" name="items[0][description]" class="form-control"
                                                value="{{ old('items.0.description') }}">
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label">Qty</label>
                                            <input type="number" min="1" name="items[0][quantity]" class="form-control"
                                                value="{{ old('items.0.quantity', 1) }}" required>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label">Unit Price</label>
                                            <input type="number" step="0.01" min="0" name="items[0][unit_price]"
                                                class="form-control" value="{{ old('items.0.unit_price', 0) }}" required>
                                        </div>
                                        <div class="col-12 col-md-2">
                                            <label class="form-label">Total</label>
                                            <input type="number" step="0.01" min="0" name="items[0][total_price]"
                                                class="form-control" value="{{ old('items.0.total_price', 0) }}" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="button" class="btn btn-outline-primary btn-sm" id="add-item">
                                <i class="bi bi-plus-lg"></i> Add Row
                            </button>
                        </div>

                        <div class="col-12">
                            <hr>
                            <h5 class="fw-bold mb-3">Totals & Amounts</h5>
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Subtotal</label>
                                    <input type="number" step="0.01" min="0" name="subtotal" class="form-control"
                                        value="{{ old('subtotal', 0) }}" readonly>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Tax Amount</label>
                                    <input type="number" step="0.01" min="0" name="tax_amount"
                                        class="form-control @error('tax_amount') is-invalid @enderror"
                                        value="{{ old('tax_amount', 0) }}">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Discount Amount</label>
                                    <input type="number" step="0.01" min="0" name="discount_amount"
                                        class="form-control @error('discount_amount') is-invalid @enderror"
                                        value="{{ old('discount_amount', 0) }}">
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Total Amount</label>
                                    <input type="number" step="0.01" min="0" name="total_amount" class="form-control"
                                        value="{{ old('total_amount', 0) }}" readonly>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">Save Invoice</button>
                        <a href="{{ route('invoices.index', $workspace) }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function recalcRow(row) {
                const qty = parseFloat(row.querySelector('input[name$="[quantity]"]').value || 0);
                const unit = parseFloat(row.querySelector('input[name$="[unit_price]"]').value || 0);
                const total = qty * unit;
                row.querySelector('input[name$="[total_price]"]').value = total.toFixed(2);
            }

            function recalcTotals() {
                const rows = document.querySelectorAll('#items-container .item-row');
                let subtotal = 0;
                rows.forEach(row => {
                    const total = parseFloat(row.querySelector('input[name$="[total_price]"]').value || 0);
                    subtotal += total;
                });
                document.querySelector('input[name="subtotal"]').value = subtotal.toFixed(2);

                const tax = parseFloat(document.querySelector('input[name="tax_amount"]').value || 0);
                const discount = parseFloat(document.querySelector('input[name="discount_amount"]').value || 0);
                const totalAmount = subtotal + tax - discount;
                document.querySelector('input[name="total_amount"]').value = totalAmount.toFixed(2);
            }

            document.addEventListener('input', function (e) {
                const target = e.target;
                if (!target) return;
                if (target.name && (target.name.includes('[quantity]') || target.name.includes('[unit_price]'))) {
                    const row = target.closest('.item-row');
                    if (row) {
                        recalcRow(row);
                        recalcTotals();
                    }
                }
                if (target.name === 'tax_amount' || target.name === 'discount_amount') {
                    recalcTotals();
                }
            });

            document.getElementById('items-container')?.addEventListener('click', function (e) {
                const btn = e.target.closest('.remove-item');
                if (!btn) return;
                const row = btn.closest('.item-row');
                if (!row) return;
                // if user tries to remove the first row, just clear values
                const rows = document.querySelectorAll('#items-container .item-row');
                if (rows.length <= 1) {
                    row.querySelectorAll('input').forEach(input => {
                        if (input.hasAttribute('readonly')) return;
                        if (input.type === 'number') input.value = 0;
                        else input.value = '';
                    });
                    recalcRow(row);
                    recalcTotals();
                    return;
                }
                row.remove();
                recalcTotals();
            });

            document.getElementById('add-item')?.addEventListener('click', function () {

                const container = document.getElementById('items-container');
                const index = container.querySelectorAll('.item-row').length;

                const wrapper = document.createElement('div');
                wrapper.className = 'item-row border rounded-3 p-3 mb-3';
                wrapper.dataset.index = index;

                wrapper.innerHTML = `
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-3">
                            <button type="button" class="btn btn-link p-0 ms-auto text-danger remove-item" title="Remove" aria-label="Remove row">×</button>
                            <label class="form-label">Item Name</label>
                            <input type="text" name="items[${index}][item_name]" class="form-control" required>
                        </div>                
                        <div class="col-12 col-md-3">
                            <label class="form-label">Description</label>
                            <input type="text" name="items[${index}][description]" class="form-control">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label">Qty</label>
                            <input type="number" min="1" name="items[${index}][quantity]" class="form-control" value="1" required>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label">Unit Price</label>
                            <input type="number" step="0.01" min="0" name="items[${index}][unit_price]" class="form-control" value="0" required>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label">Total</label>
                            <input type="number" step="0.01" min="0" name="items[${index}][total_price]" class="form-control" value="0" readonly>
                        </div>
                    </div>
                `;

                container.appendChild(wrapper);
                recalcTotals();
            });

            // initial calc
            (function () {
                document.querySelectorAll('#items-container .item-row').forEach(row => {
                    recalcRow(row);
                });
                recalcTotals();
            })();
        </script>
    @endpush
@endsection
