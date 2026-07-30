@extends('layouts.app')

@section('page_title', 'Invoice Details')

@push('styles')
    <style>
        .invoice-document {
            background: #fff;
        }

        .invoice-brand-logo {
            width: 88px;
            height: 88px;
            object-fit: contain;
            background: #f8fafc;
        }

        .invoice-meta-label {
            min-width: 96px;
        }

        .invoice-total-row {
            border-top: 1px solid #e2e8f0;
        }

        .invoice-notes {
            white-space: pre-wrap;
        }

        .payment-timeline {
            position: relative;
        }

        .payment-timeline-item {
            position: relative;
            padding-left: 2.25rem;
        }

        .payment-timeline-item::before {
            position: absolute;
            top: 1.75rem;
            bottom: -1rem;
            left: 0.6rem;
            width: 1px;
            content: "";
            background: #e2e8f0;
        }

        .payment-timeline-item:last-child::before {
            display: none;
        }

        .payment-timeline-marker {
            position: absolute;
            top: 0.25rem;
            left: 0;
            width: 1.25rem;
            height: 1.25rem;
        }

        @media print {
            body {
                background: #fff;
            }

            #sidebar,
            .navbar,
            .invoice-screen-actions,
            .invoice-payment-actions,
            .mobile-backdrop {
                display: none !important;
            }

            #content {
                margin-left: 0 !important;
                width: 100% !important;
            }

            main.container-fluid {
                padding: 0 !important;
            }

            .invoice-document {
                border: 0 !important;
                box-shadow: none !important;
            }
        }
    </style>
@endpush

@section('content')
@php
    $businessProfile = $invoice->businessProfile();
    $businessName = $businessProfile?->display_name ?? $workspace->name;
    $businessContactLines = $businessProfile?->contact_lines ?? [];
    $businessLogoUrl = $businessProfile?->logo_url ?? asset('images/business-logo-placeholder.svg');
    $businessTaxLabel = $businessProfile?->tax_label;
    $invoiceCurrencyCode = $invoice->currency?->code ?? $workspace->currency?->code;
    $badge = match ($invoice->status) {
        'draft' => 'secondary',
        'sent' => 'info',
        'partial' => 'warning',
        'paid' => 'success',
        'overdue' => 'danger',
        default => 'secondary'
    };
@endphp

<div class="container-fluid py-2">
    <div class="mb-4 d-flex flex-column flex-md-row justify-content-between gap-3 align-items-start invoice-screen-actions">
        <div>
            <h2 class="fs-4 fw-bold text-dark mb-1">Invoice {{ $invoice->invoice_number }}</h2>
            <p class="text-muted small mb-0">Client: <strong>{{ $invoice->client?->name ?? '-' }}</strong></p>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('invoices.index', $workspace) }}" class="btn btn-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Back to Invoices
            </a>
            <a href="{{ route('invoices.edit', [$workspace, $invoice]) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-pencil-square"></i> Edit
            </a>
            <a href="{{ route('invoices.pdf', [$workspace, $invoice]) }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-file-earmark-pdf"></i> Download PDF
            </a>
            <form action="{{ route('invoices.send', [$workspace, $invoice]) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-send"></i>
                    {{ $invoice->emailLogs->where('status', 'sent')->isNotEmpty() ? 'Resend Invoice' : 'Send Invoice' }}
                </button>
            </form>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card border-light shadow-sm rounded-4 overflow-hidden invoice-document">
                <div class="card-body p-3 p-md-5">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-4 pb-4 mb-4 border-bottom">
                        <div class="d-flex flex-column flex-sm-row gap-3">
                            <div class="border rounded-3 p-2 d-flex align-items-center justify-content-center flex-shrink-0">
                                <img
                                    src="{{ $businessLogoUrl }}"
                                    alt="{{ $businessName }} logo"
                                    class="invoice-brand-logo"
                                    loading="lazy"
                                >
                            </div>
                            <div>
                                <h3 class="fs-4 fw-bold text-dark mb-2">{{ $businessName }}</h3>

                                @forelse ($businessContactLines as $contactLine)
                                    <p class="text-muted small mb-1">{{ $contactLine }}</p>
                                @empty
                                    <p class="text-muted small mb-1">{{ $workspace->name }}</p>
                                @endforelse

                                @if ($businessTaxLabel)
                                    <p class="text-muted small mb-0">{{ $businessTaxLabel }}</p>
                                @endif

                                @if ($invoiceCurrencyCode)
                                    <span class="badge bg-light text-dark border mt-2">
                                        Currency: {{ $invoiceCurrencyCode }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="text-md-end">
                            <p class="text-uppercase small fw-bold text-muted mb-1">Invoice</p>
                            <h4 class="fw-bold text-dark mb-3">{{ $invoice->invoice_number }}</h4>
                            <div class="d-flex justify-content-md-end justify-content-between gap-3 mb-2">
                                <span class="text-muted small invoice-meta-label">Status</span>
                                <span class="badge bg-{{ $badge }}">{{ ucfirst($invoice->status) }}</span>
                            </div>
                            <div class="d-flex justify-content-md-end justify-content-between gap-3 mb-2">
                                <span class="text-muted small invoice-meta-label">Issue date</span>
                                <strong class="small">{{ $invoice->issue_date?->format('Y-m-d') ?? '-' }}</strong>
                            </div>
                            <div class="d-flex justify-content-md-end justify-content-between gap-3">
                                <span class="text-muted small invoice-meta-label">Due date</span>
                                <strong class="small">{{ $invoice->due_date?->format('Y-m-d') ?? '-' }}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <p class="text-uppercase small fw-bold text-muted mb-2">Bill to</p>
                            <h5 class="fw-bold text-dark mb-1">{{ $invoice->client?->name ?? 'Client' }}</h5>
                            @if ($invoice->client?->email)
                                <p class="text-muted small mb-1">{{ $invoice->client->email }}</p>
                            @endif
                            @if ($invoice->client?->phone)
                                <p class="text-muted small mb-1">{{ $invoice->client->phone }}</p>
                            @endif
                            @if ($invoice->client?->address)
                                <p class="text-muted small mb-0">{{ $invoice->client->address }}</p>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <div class="bg-light rounded-3 p-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total due</span>
                                    <strong class="text-danger">{{ $invoice->formatMoney($invoice->remaining_balance) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Total paid</span>
                                    <strong class="text-success">{{ $invoice->formatMoney($invoice->paid_amount) }}</strong>
                                </div>
                                <div class="d-flex justify-content-between invoice-total-row pt-2">
                                    <span class="fw-bold">Invoice total</span>
                                    <strong>{{ $invoice->formatMoney($invoice->total_amount) }}</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h5 class="fw-bold text-dark mb-0 fs-6">Invoice Line Items</h5>
                    <div class="mt-3">
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
                                                <td class="text-end">{{ $invoice->formatMoney($item->unit_price) }}</td>
                                                <td class="text-end">{{ $invoice->formatMoney($item->total_price) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    @if ($invoice->notes)
                        <div class="mt-4 pt-4 border-top">
                            <h6 class="fw-bold mb-2">Notes</h6>
                            <div class="text-muted text-break invoice-notes">{{ $invoice->notes }}</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card border-light shadow-sm rounded-4 overflow-hidden mt-3">
                <div class="card-header bg-white p-4 border-bottom">
                    <div class="d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h5 class="fw-bold text-dark mb-1 fs-6">Payment History &amp; Activity Timeline</h5>
                            <p class="text-muted small mb-0">Audit trail for invoice emails, payments, and status changes.</p>
                        </div>
                        <span class="badge bg-light text-dark border align-self-sm-start">
                            {{ $invoice->payments->count() }} {{ \Illuminate\Support\Str::plural('payment', $invoice->payments->count()) }}
                        </span>
                    </div>
                </div>
                <div class="card-body p-3 p-md-4">
                    @if ($invoice->payments->isEmpty())
                        <div class="text-muted mb-3">No payments recorded yet.</div>
                    @endif

                    <div class="payment-timeline">
                        @foreach ($paymentTimeline as $event)
                            <div class="payment-timeline-item pb-4">
                                    <span class="payment-timeline-marker rounded-circle bg-{{ $event['badge'] }} d-inline-flex align-items-center justify-content-center">
                                    <i class="bi {{ $event['type'] === 'payment' ? 'bi-cash-coin' : ($event['type'] === 'email' ? 'bi-envelope-paper' : 'bi-receipt') }} text-white small"></i>
                                </span>

                                <div class="border rounded-3 p-3 bg-white">
                                    <div class="d-flex flex-column flex-sm-row justify-content-between gap-2 mb-2">
                                        <div>
                                            <h6 class="fw-bold text-dark mb-1">{{ $event['title'] }}</h6>
                                            <p class="text-muted small mb-0">
                                                {{ $event['date'] ? $event['date']->format('Y-m-d') : '-' }}
                                            </p>
                                        </div>
                                        @if (array_key_exists('amount', $event))
                                            <strong class="text-dark">{{ $invoice->formatMoney($event['amount']) }}</strong>
                                        @endif
                                    </div>

                                    @if ($event['type'] === 'payment')
                                        <div class="row g-2 small text-muted">
                                            <div class="col-sm-6">
                                                <span class="fw-semibold text-dark">Method:</span>
                                                {{ $event['method'] ?: 'Not specified' }}
                                            </div>
                                            <div class="col-sm-6">
                                                <span class="fw-semibold text-dark">Reference:</span>
                                                {{ $event['reference'] ?: 'Not provided' }}
                                            </div>
                                        </div>
                                    @endif

                                    @if ($event['type'] === 'email')
                                        <div class="row g-2 small text-muted">
                                            <div class="col-sm-12">
                                                <span class="fw-semibold text-dark">Recipient:</span>
                                                {{ $event['recipient_email'] }}
                                            </div>
                                            <div class="col-sm-12">
                                                <span class="fw-semibold text-dark">Subject:</span>
                                                {{ $event['subject'] }}
                                            </div>
                                        </div>
                                    @endif

                                    @if (! empty($event['notes']))
                                        <div class="small text-muted mt-2 invoice-notes">{{ $event['notes'] }}</div>
                                    @endif

                                    @if (array_key_exists('remaining_balance', $event))
                                        <div class="d-flex justify-content-between small mt-3 pt-2 border-top">
                                            <span class="text-muted">Remaining balance</span>
                                            <strong>{{ $invoice->formatMoney($event['remaining_balance']) }}</strong>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
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
                        <span class="badge bg-{{ $badge }}">{{ ucfirst($invoice->status) }}</span>
                    </p>
                    <p class="mb-2"><strong>Currency:</strong>
                        <span class="badge bg-light text-dark border">
                            {{ $invoiceCurrencyCode ?? 'Default' }}
                        </span>
                    </p>

                    <p class="mb-2"><strong>Issue Date:</strong> {{ $invoice->issue_date?->format('Y-m-d') }}</p>
                    <p class="mb-2"><strong>Due Date:</strong> {{ $invoice->due_date?->format('Y-m-d') }}</p>

                    <hr>
                    <p class="mb-2 d-flex justify-content-between"><strong>Subtotal</strong><span>{{ $invoice->formatMoney($invoice->subtotal) }}</span></p>
                    <p class="mb-2 d-flex justify-content-between"><strong>Tax</strong><span>{{ $invoice->formatMoney($invoice->tax_amount) }}</span></p>
                    <p class="mb-2 d-flex justify-content-between"><strong>Discount</strong><span>{{ $invoice->formatMoney($invoice->discount_amount) }}</span></p>
                    <p class="mb-2 d-flex justify-content-between fs-5"><strong>Total</strong><span>{{ $invoice->formatMoney($invoice->total_amount) }}</span></p>
                    <p class="mb-2 d-flex justify-content-between text-success"><strong>Total Paid</strong><span>{{ $invoice->formatMoney($invoice->paid_amount) }}</span></p>
                    <p class="mb-0 d-flex justify-content-between text-danger"><strong>Remaining</strong><span>{{ $invoice->formatMoney($invoice->remaining_balance) }}</span></p>

                    @if ($businessTaxLabel)
                        <hr>
                        <p class="mb-0"><strong>{{ $businessTaxLabel }}</strong></p>
                    @endif
                </div>
            </div>

            @if (! $invoice->is_paid)
                <div class="card border-light shadow-sm rounded-4 overflow-hidden mt-3 invoice-payment-actions">
                    <div class="card-header bg-white p-4 border-bottom">
                        <h5 class="fw-bold text-dark mb-0 fs-6">Record Payment</h5>
                    </div>
                    <div class="card-body p-3 p-md-4">
                        <form action="{{ route('invoices.payments.store', [$workspace, $invoice]) }}" method="POST">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">

                            <div class="mb-3">
                                <label for="amount" class="form-label">Amount</label>
                                <input
                                    id="amount"
                                    name="amount"
                                    type="number"
                                    min="0.01"
                                    max="{{ number_format((float) $invoice->remaining_balance, 2, '.', '') }}"
                                    step="0.01"
                                    value="{{ old('amount', number_format((float) $invoice->remaining_balance, 2, '.', '')) }}"
                                    class="form-control @error('amount') is-invalid @enderror"
                                    required
                                >
                                @error('amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="payment_date" class="form-label">Payment Date</label>
                                <input
                                    id="payment_date"
                                    name="payment_date"
                                    type="date"
                                    value="{{ old('payment_date', now()->toDateString()) }}"
                                    class="form-control @error('payment_date') is-invalid @enderror"
                                    required
                                >
                                @error('payment_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="payment_method" class="form-label">Payment Method</label>
                                <input
                                    id="payment_method"
                                    name="payment_method"
                                    type="text"
                                    value="{{ old('payment_method') }}"
                                    class="form-control @error('payment_method') is-invalid @enderror"
                                    placeholder="Bank transfer, cash, card"
                                >
                                @error('payment_method')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="reference" class="form-label">Payment Reference</label>
                                <input
                                    id="reference"
                                    name="reference"
                                    type="text"
                                    value="{{ old('reference') }}"
                                    class="form-control @error('reference') is-invalid @enderror"
                                    placeholder="Transaction ID or receipt number"
                                >
                                @error('reference')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea
                                    id="notes"
                                    name="notes"
                                    rows="3"
                                    class="form-control @error('notes') is-invalid @enderror"
                                >{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm w-100">
                                <i class="bi bi-plus-lg"></i> Add Payment
                            </button>
                        </form>

                        <form action="{{ route('invoices.payments.store', [$workspace, $invoice]) }}" method="POST" class="mt-2">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                            <input type="hidden" name="amount" value="{{ number_format((float) $invoice->remaining_balance, 2, '.', '') }}">
                            <input type="hidden" name="payment_date" value="{{ now()->toDateString() }}">
                            <input type="hidden" name="payment_method" value="Manual">
                            <input type="hidden" name="reference" value="Manual paid shortcut">
                            <input type="hidden" name="notes" value="Marked as paid from invoice shortcut.">

                            <button type="submit" class="btn btn-outline-success btn-sm w-100">
                                <i class="bi bi-check2-circle"></i> Mark as Paid
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            <div class="card border-light shadow-sm rounded-4 overflow-hidden mt-3">
                <div class="card-header bg-white p-4 border-bottom">
                    <div class="d-flex flex-column flex-sm-row justify-content-between gap-2">
                        <div>
                            <h5 class="fw-bold text-dark mb-1 fs-6">Invoice Email History</h5>
                            <p class="text-muted small mb-0">Complete delivery log for this invoice.</p>
                        </div>
                        <span class="badge bg-light text-dark border align-self-sm-start">
                            {{ $invoice->emailLogs->count() }} {{ \Illuminate\Support\Str::plural('email', $invoice->emailLogs->count()) }}
                        </span>
                    </div>
                </div>
                <div class="card-body p-3 p-md-4">
                    @forelse ($invoice->emailLogs as $emailLog)
                        @php
                            $emailBadge = match ($emailLog->status) {
                                'sent' => 'success',
                                'failed' => 'danger',
                                default => 'secondary',
                            };
                        @endphp
                        <div class="border rounded-3 p-3 {{ $loop->last ? '' : 'mb-3' }}">
                            <div class="d-flex justify-content-between gap-3 mb-2">
                                <div>
                                    <h6 class="fw-bold text-dark mb-1">{{ $emailLog->sent_at?->format('M j') ?? $emailLog->created_at?->format('M j') ?? '-' }}</h6>
                                    <p class="text-muted small mb-0">Invoice sent to: {{ $emailLog->recipient_email }}</p>
                                </div>
                                <span class="badge bg-{{ $emailBadge }} align-self-start">{{ ucfirst($emailLog->status) }}</span>
                            </div>
                            <p class="small mb-1"><strong>Subject:</strong> {{ $emailLog->subject }}</p>
                            <p class="small mb-1"><strong>Sent by:</strong> {{ $emailLog->sender?->name ?? 'System' }}</p>
                            <p class="small text-muted mb-0">
                                <strong>Sent date:</strong>
                                {{ $emailLog->sent_at?->format('Y-m-d H:i') ?? 'Queued' }}
                            </p>
                            @if ($emailLog->error_message)
                                <div class="alert alert-danger small mt-3 mb-0">{{ $emailLog->error_message }}</div>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-3">No invoice emails have been sent yet.</p>
                    @endforelse

                    <form action="{{ route('invoices.send', [$workspace, $invoice]) }}" method="POST" class="mt-3">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-send"></i>
                            {{ $invoice->emailLogs->where('status', 'sent')->isNotEmpty() ? 'Resend Invoice' : 'Send Invoice' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
