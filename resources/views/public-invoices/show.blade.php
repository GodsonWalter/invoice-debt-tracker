<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        body {
            background: #eef2f7;
            color: #172033;
        }

        .public-shell {
            max-width: 1040px;
        }

        .invoice-document {
            background: #fff;
            border: 1px solid #e2e8f0;
        }

        .brand-logo {
            width: 84px;
            height: 84px;
            object-fit: contain;
            background: #f8fafc;
        }

        .invoice-notes {
            white-space: pre-wrap;
        }

        .summary-panel {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        @media print {
            body {
                background: #fff;
            }

            .public-actions,
            .public-header-note {
                display: none !important;
            }

            .public-shell {
                max-width: 100%;
                padding: 0 !important;
            }

            .invoice-document {
                border: 0 !important;
                box-shadow: none !important;
            }
        }
    </style>
</head>
<body>
@php
    $businessProfile = $invoice->businessProfile();
    $businessName = $businessProfile?->display_name ?? $workspace->name;
    $businessContactLines = $businessProfile?->contact_lines ?? [];
    $businessLogoUrl = $businessProfile?->logo_url ?? asset('images/business-logo-placeholder.svg');
    $businessTaxLabel = $businessProfile?->tax_label;
    $invoiceCurrencyCode = $invoice->currency?->code ?? $workspace->currency?->code;
    $badge = match ($invoice->status) {
        'sent' => 'info',
        'partial' => 'warning',
        'paid' => 'success',
        'overdue' => 'danger',
        default => 'secondary'
    };
@endphp

<main class="public-shell container py-4 py-md-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3 mb-4 public-actions">
        <div>
            <h1 class="fs-3 fw-bold mb-1">Invoice {{ $invoice->invoice_number }}</h1>
            <p class="text-muted mb-0 public-header-note">Secure invoice from {{ $businessName }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('public.invoice.pdf', $invoice->public_token) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-file-earmark-pdf"></i> Download PDF
            </a>
            <a href="{{ route('public.invoice.print', $invoice->public_token) }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-printer"></i> Print Invoice
            </a>
        </div>
    </div>

    <section class="invoice-document rounded-3 shadow-sm overflow-hidden">
        <div class="p-3 p-md-5">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-4 pb-4 mb-4 border-bottom">
                <div class="d-flex flex-column flex-sm-row gap-3">
                    <div class="border rounded-3 p-2 d-flex align-items-center justify-content-center flex-shrink-0">
                        <img src="{{ $businessLogoUrl }}" alt="{{ $businessName }} logo" class="brand-logo">
                    </div>
                    <div>
                        <h2 class="fs-4 fw-bold mb-2">{{ $businessName }}</h2>
                        @forelse ($businessContactLines as $contactLine)
                            <p class="text-muted small mb-1">{{ $contactLine }}</p>
                        @empty
                            <p class="text-muted small mb-1">{{ $workspace->name }}</p>
                        @endforelse
                        @if ($businessTaxLabel)
                            <p class="text-muted small mb-0">{{ $businessTaxLabel }}</p>
                        @endif
                    </div>
                </div>

                <div class="text-md-end">
                    <p class="text-uppercase small fw-bold text-muted mb-1">Invoice</p>
                    <h3 class="fw-bold mb-3">{{ $invoice->invoice_number }}</h3>
                    <p class="mb-2">
                        <span class="text-muted small">Status</span>
                        <span class="badge bg-{{ $badge }} ms-2">{{ ucfirst($invoice->status) }}</span>
                    </p>
                    <p class="mb-2 small"><span class="text-muted">Issue date:</span> <strong>{{ $invoice->issue_date?->format('Y-m-d') ?? '-' }}</strong></p>
                    <p class="mb-2 small"><span class="text-muted">Due date:</span> <strong>{{ $invoice->due_date?->format('Y-m-d') ?? '-' }}</strong></p>
                    @if ($invoiceCurrencyCode)
                        <p class="mb-0 small"><span class="text-muted">Currency:</span> <strong>{{ $invoiceCurrencyCode }}</strong></p>
                    @endif
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <p class="text-uppercase small fw-bold text-muted mb-2">Bill to</p>
                    <h4 class="fs-5 fw-bold mb-1">{{ $invoice->client?->name ?? 'Client' }}</h4>
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
                    <div class="summary-panel rounded-3 p-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total due</span>
                            <strong class="text-danger">{{ $invoice->formatMoney($invoice->remaining_balance) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total paid</span>
                            <strong class="text-success">{{ $invoice->formatMoney($invoice->paid_amount) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between pt-2 border-top">
                            <span class="fw-bold">Invoice total</span>
                            <strong>{{ $invoice->formatMoney($invoice->total_amount) }}</strong>
                        </div>
                    </div>
                </div>
            </div>

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
                                <td>{{ $item->description ?: '-' }}</td>
                                <td class="text-end">{{ $item->quantity }}</td>
                                <td class="text-end">{{ $invoice->formatMoney($item->unit_price) }}</td>
                                <td class="text-end">{{ $invoice->formatMoney($item->total_price) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="row justify-content-end mt-4">
                <div class="col-md-5">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span>{{ $invoice->formatMoney($invoice->subtotal) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Tax</span>
                        <span>{{ $invoice->formatMoney($invoice->tax_amount) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Discount</span>
                        <span>{{ $invoice->formatMoney($invoice->discount_amount) }}</span>
                    </div>
                    <div class="d-flex justify-content-between border-top pt-2 fs-5">
                        <strong>Total</strong>
                        <strong>{{ $invoice->formatMoney($invoice->total_amount) }}</strong>
                    </div>
                </div>
            </div>

            @if ($invoice->notes)
                <div class="mt-4 pt-4 border-top">
                    <h5 class="fs-6 fw-bold">Notes</h5>
                    <div class="text-muted invoice-notes">{{ $invoice->notes }}</div>
                </div>
            @endif

            <div class="mt-4 pt-4 border-top">
                <h5 class="fs-6 fw-bold mb-3">Payment History</h5>
                @forelse ($invoice->payments as $payment)
                    <div class="d-flex flex-column flex-sm-row justify-content-between gap-2 border rounded-3 p-3 mb-2">
                        <div>
                            <strong>{{ $payment->payment_date?->format('Y-m-d') ?? $payment->created_at?->format('Y-m-d') }}</strong>
                            <p class="text-muted small mb-0">
                                {{ $payment->payment_method ?: 'Payment' }}
                                @if ($payment->reference)
                                    | Ref: {{ $payment->reference }}
                                @endif
                            </p>
                        </div>
                        <strong>{{ $invoice->formatMoney($payment->amount) }}</strong>
                    </div>
                @empty
                    <p class="text-muted mb-0">No payments have been recorded yet.</p>
                @endforelse
            </div>
        </div>
    </section>
</main>

@if ($printMode)
    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
@endif
</body>
</html>
