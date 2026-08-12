<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 34px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #111827;
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.45;
            margin: 0;
        }

        h1,
        h2,
        h3,
        p {
            margin: 0;
        }

        .muted {
            color: #64748b;
        }

        .header-table,
        .section-table,
        .summary-table,
        .items-table {
            border-collapse: collapse;
            width: 100%;
        }

        .header-table td {
            vertical-align: top;
        }

        .brand {
            width: 62%;
        }

        .invoice-meta {
            text-align: right;
            width: 38%;
        }

        .logo {
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            height: 72px;
            object-fit: contain;
            padding: 8px;
            width: 72px;
        }

        .brand-name {
            font-size: 22px;
            font-weight: 700;
            margin-top: 10px;
        }

        .invoice-title {
            font-size: 28px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .status {
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            color: #334155;
            display: inline-block;
            font-size: 10px;
            font-weight: 700;
            margin-top: 8px;
            padding: 4px 10px;
            text-transform: uppercase;
        }

        .divider {
            border-top: 1px solid #e2e8f0;
            margin: 26px 0;
        }

        .section-title {
            color: #475569;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .8px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .section-table td {
            vertical-align: top;
            width: 50%;
        }

        .items-table th {
            background: #f1f5f9;
            border-bottom: 1px solid #cbd5e1;
            color: #334155;
            font-size: 10px;
            padding: 10px 8px;
            text-align: left;
            text-transform: uppercase;
        }

        .items-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 8px;
            vertical-align: top;
        }

        .text-right {
            text-align: right;
        }

        .summary-wrap {
            margin-left: auto;
            margin-top: 24px;
            width: 44%;
        }

        .summary-table td {
            padding: 6px 0;
        }

        .summary-table .total td {
            border-top: 1px solid #cbd5e1;
            font-size: 15px;
            font-weight: 700;
            padding-top: 10px;
        }

        .notes {
            border-top: 1px solid #e2e8f0;
            margin-top: 28px;
            padding-top: 14px;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
@php
    $businessProfile = $invoice->businessProfile();
    $platform = app(\App\Services\PlatformConfigurationService::class)->settings();
    $businessName = $businessProfile?->display_name ?? $workspace->name;
    $businessAddress = $businessProfile?->formatted_address;
    $businessTaxLabel = $businessProfile?->tax_label;
    $logoPath = null;

    if ($businessProfile?->logo && \Illuminate\Support\Facades\Storage::disk('public')->exists($businessProfile->logo)) {
        $logoPath = public_path('storage/'.$businessProfile->logo);
    }
@endphp

<table class="header-table">
    <tr>
        <td class="brand">
            @if ($logoPath)
                <img src="{{ $logoPath }}" alt="{{ $businessName }} logo" class="logo">
            @endif
            <h1 class="brand-name">{{ $businessName }}</h1>
            @if ($businessAddress)
                <p class="muted">{{ $businessAddress }}</p>
            @endif
            @if ($businessProfile?->email || $businessProfile?->phone)
                <p class="muted">
                    {{ collect([$businessProfile?->email, $businessProfile?->phone])->filter()->implode(' | ') }}
                </p>
            @endif
            @if ($businessTaxLabel)
                <p class="muted">{{ $businessTaxLabel }}</p>
            @endif
        </td>
        <td class="invoice-meta">
            <h2 class="invoice-title">INVOICE</h2>
            <p><strong>{{ $invoice->invoice_number }}</strong></p>
            <span class="status">{{ $invoice->status }}</span>
            <p style="margin-top: 14px;"><span class="muted">Issue date:</span> {{ $invoice->issue_date?->format('Y-m-d') ?? '-' }}</p>
            <p><span class="muted">Due date:</span> {{ $invoice->due_date?->format('Y-m-d') ?? '-' }}</p>
            @if ($invoice->currency?->code)
                <p><span class="muted">Currency:</span> {{ $invoice->currency->code }}</p>
            @endif
        </td>
    </tr>
</table>

<div class="divider"></div>

<table class="section-table">
    <tr>
        <td>
            <p class="section-title">Bill To</p>
            <h3>{{ $invoice->client?->name ?? 'Client' }}</h3>
            @if ($invoice->client?->email)
                <p class="muted">{{ $invoice->client->email }}</p>
            @endif
            @if ($invoice->client?->phone)
                <p class="muted">{{ $invoice->client->phone }}</p>
            @endif
            @if ($invoice->client?->address)
                <p class="muted">{{ $invoice->client->address }}</p>
            @endif
        </td>
        <td class="text-right">
            <p class="section-title">Balance</p>
            <h3>{{ $invoice->formatMoney($invoice->remaining_balance) }}</h3>
            <p class="muted">Total paid: {{ $invoice->formatMoney($invoice->total_paid) }}</p>
        </td>
    </tr>
</table>

<div class="divider"></div>

<table class="items-table">
    <thead>
        <tr>
            <th>Item</th>
            <th>Description</th>
            <th class="text-right">Qty</th>
            <th class="text-right">Unit Price</th>
            <th class="text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($invoice->items as $item)
            <tr>
                <td><strong>{{ $item->item_name }}</strong></td>
                <td class="muted">{{ $item->description ?: '-' }}</td>
                <td class="text-right">{{ $item->quantity }}</td>
                <td class="text-right">{{ $invoice->formatMoney($item->unit_price) }}</td>
                <td class="text-right">{{ $invoice->formatMoney($item->total_price) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5" class="muted">No line items found.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="summary-wrap">
    <table class="summary-table">
        <tr>
            <td class="muted">Subtotal</td>
            <td class="text-right">{{ $invoice->formatMoney($invoice->subtotal) }}</td>
        </tr>
        <tr>
            <td class="muted">Tax</td>
            <td class="text-right">{{ $invoice->formatMoney($invoice->tax_amount) }}</td>
        </tr>
        <tr>
            <td class="muted">Discount</td>
            <td class="text-right">{{ $invoice->formatMoney($invoice->discount_amount) }}</td>
        </tr>
        <tr class="total">
            <td>Total</td>
            <td class="text-right">{{ $invoice->formatMoney($invoice->total_amount) }}</td>
        </tr>
    </table>
</div>

@if ($invoice->notes)
    <div class="notes">
        <p class="section-title">Notes</p>
        <p class="muted">{{ $invoice->notes }}</p>
    </div>
@endif

<p class="muted" style="border-top:1px solid #e2e8f0;margin-top:32px;padding-top:12px;text-align:center;font-size:10px;">
    {{ $platform->product_name }}{{ $platform->support_url ? ' · '.$platform->support_url : '' }}
</p>
</body>
</html>
