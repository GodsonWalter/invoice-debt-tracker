<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'Invoice '.$invoice->invoice_number }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f6fb;color:#1f2937;font-family:Arial,Helvetica,sans-serif;">
@php
    $businessName = $businessProfile?->display_name ?? $workspace->name ?? config('app.name');
    $businessLogoUrl = $businessProfile?->logo_url ?? asset('images/business-logo-placeholder.svg');
    $businessAddress = $businessProfile?->formatted_address;
    $businessTaxLabel = $businessProfile?->tax_label;
    $clientName = $invoice->client?->name ?? 'there';
    $invoiceUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
        'public.invoice.show',
        now()->addDays(30),
        ['token' => $invoice->public_token],
    );
    $statusColors = [
        'draft' => '#64748b',
        'sent' => '#0284c7',
        'partial' => '#d97706',
        'paid' => '#16a34a',
        'overdue' => '#dc2626',
    ];
    $statusColor = $statusColors[$invoice->status] ?? '#64748b';
@endphp

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f6fb;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="padding:28px 28px 22px;border-bottom:1px solid #e5e7eb;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                            <tr>
                                <td style="vertical-align:middle;width:72px;">
                                    <img src="{{ $businessLogoUrl }}" alt="{{ $businessName }} logo" width="56" height="56" style="display:block;width:56px;height:56px;object-fit:contain;border:1px solid #e5e7eb;border-radius:8px;padding:6px;background:#f8fafc;">
                                </td>
                                <td style="vertical-align:middle;">
                                    <div style="font-size:20px;font-weight:700;color:#111827;">{{ $businessName }}</div>
                                    <div style="font-size:13px;color:#64748b;margin-top:4px;">Invoice {{ $invoice->invoice_number }}</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:28px;">
                        <p style="font-size:16px;line-height:1.6;margin:0 0 16px;">Hello {{ $clientName }},</p>
                        <p style="font-size:15px;line-height:1.7;margin:0 0 24px;color:#475569;">
                            Your invoice from {{ $businessName }} is ready. A PDF copy is attached for your records.
                        </p>

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin-bottom:26px;">
                            <tr>
                                <td style="padding:14px 16px;background:#f8fafc;color:#64748b;font-size:13px;">Invoice number</td>
                                <td align="right" style="padding:14px 16px;background:#f8fafc;font-weight:700;font-size:13px;">{{ $invoice->invoice_number }}</td>
                            </tr>
                            <tr>
                                <td style="padding:14px 16px;border-top:1px solid #e5e7eb;color:#64748b;font-size:13px;">Issue date</td>
                                <td align="right" style="padding:14px 16px;border-top:1px solid #e5e7eb;font-size:13px;">{{ $invoice->issue_date?->format('M j, Y') ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td style="padding:14px 16px;border-top:1px solid #e5e7eb;color:#64748b;font-size:13px;">Due date</td>
                                <td align="right" style="padding:14px 16px;border-top:1px solid #e5e7eb;font-size:13px;">{{ $invoice->due_date?->format('M j, Y') ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td style="padding:14px 16px;border-top:1px solid #e5e7eb;color:#64748b;font-size:13px;">Status</td>
                                <td align="right" style="padding:14px 16px;border-top:1px solid #e5e7eb;font-size:13px;">
                                    <span style="display:inline-block;background:{{ $statusColor }};color:#ffffff;border-radius:999px;padding:4px 10px;font-size:12px;font-weight:700;text-transform:uppercase;">{{ $invoice->status }}</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:16px;border-top:1px solid #e5e7eb;color:#111827;font-size:15px;font-weight:700;">Total amount</td>
                                <td align="right" style="padding:16px;border-top:1px solid #e5e7eb;color:#111827;font-size:18px;font-weight:700;">{{ $invoice->formatMoney($invoice->total_amount) }}</td>
                            </tr>
                        </table>

                        <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 26px;">
                            <tr>
                                <td align="center" style="border-radius:8px;background:#2563eb;">
                                    <a href="{{ $invoiceUrl }}" style="display:inline-block;padding:13px 22px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;border-radius:8px;">View Invoice</a>
                                </td>
                            </tr>
                        </table>

                        <p style="font-size:13px;line-height:1.6;color:#64748b;margin:0;">
                            If the button does not work, open this link in your browser:<br>
                            <a href="{{ $invoiceUrl }}" style="color:#2563eb;text-decoration:underline;">{{ $invoiceUrl }}</a>
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:22px 28px;background:#f8fafc;border-top:1px solid #e5e7eb;color:#64748b;font-size:12px;line-height:1.7;">
                        <strong style="color:#334155;">{{ $businessName }}</strong><br>
                        @if ($businessAddress)
                            {{ $businessAddress }}<br>
                        @endif
                        @if ($businessProfile?->email)
                            {{ $businessProfile->email }}<br>
                        @endif
                        @if ($businessProfile?->phone)
                            {{ $businessProfile->phone }}<br>
                        @endif
                        @if ($businessTaxLabel)
                            {{ $businessTaxLabel }}
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
