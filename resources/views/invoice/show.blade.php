@extends('layouts.app')

@section('page_title', 'Invoice Details')

@section('content')
<div class="container-fluid py-2">
    <div class="mb-4 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start">
        <div>
            <h2 class="fs-4 fw-bold text-dark mb-1">Invoice {{ $invoice->invoice_number }}</h2>
            <p class="text-muted small mb-0">Client: <strong>{{ $invoice->client?->name ?? '—' }}</strong></p>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('invoices.index', $workspace) }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Invoices
            </a>
            <a href="{{ route('invoices.edit', [$workspace, $invoice]) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-pencil-square"></i> Edit
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card border-light shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white p-4 border-bottom">
                    <h5 class="fw-bold text-dark mb-0 fs-6">Invoice Line Items</h5>
                </div>
                <div class="card-body p-3 p-md-4">
                    @if ($invoice->items->isEmpty())
                        <div class="text-muted">No items found.</div>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th>Description</th>
                                        <th class="text-end">Qty</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($invoice->items as $item)
                                        <tr>
                                            <td class="fw-medium">{{ $item->item_name }}</td>
                                            <td>{{ $item->description }}</td>
                                            <td class="text-end">{{ $item->quantity }}</td>
                                            <td class="text-end">{{ number_format((float)$item->unit_price, 2) }}</td>
                                            <td class="text-end">{{ number_format((float)$item->total_price, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card border-light shadow-sm rounded-4 overflow-hidden">
                <div class="card-header bg-white p-4 border-bottom">
                    <h5 class="fw-bold text-dark mb-0 fs-6">Summary</h5>
                </div>
                <div class="card-body p-3 p-md-4">
                    <p class="mb-2"><strong>Status:</strong>
                        @php
                            $badge = match($invoice->status) {
                                'draft' => 'secondary',
                                'sent' => 'info',
                                'paid' => 'success',
                                'overdue' => 'danger',
                                default => 'secondary'
                            };
                        @endphp
                        <span class="badge bg-{{ $badge }}">{{ ucfirst($invoice->status) }}</span>
                    </p>

                    <p class="mb-2"><strong>Issue Date:</strong> {{ $invoice->issue_date?->format('Y-m-d') }}</p>
                    <p class="mb-2"><strong>Due Date:</strong> {{ $invoice->due_date?->format('Y-m-d') }}</p>

                    <hr>
                    <p class="mb-2 d-flex justify-content-between"><strong>Subtotal</strong><span>{{ number_format((float)$invoice->subtotal, 2) }}</span></p>
                    <p class="mb-2 d-flex justify-content-between"><strong>Tax</strong><span>{{ number_format((float)$invoice->tax_amount, 2) }}</span></p>
                    <p class="mb-2 d-flex justify-content-between"><strong>Discount</strong><span>{{ number_format((float)$invoice->discount_amount, 2) }}</span></p>
                    <p class="mb-0 d-flex justify-content-between fs-5"><strong>Total</strong><span>{{ number_format((float)$invoice->total_amount, 2) }}</span></p>

                    @if ($invoice->notes)
                        <hr>
                        <h6 class="fw-bold mb-2">Notes</h6>
                        <div class="text-muted" style="white-space: pre-wrap;">{{ $invoice->notes }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

