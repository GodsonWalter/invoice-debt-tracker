<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $renderedSubject }}</title>
</head>
<body style="margin:0;padding:0;background:#f3f6fb;color:#1f2937;font-family:Arial,Helvetica,sans-serif;">
@php
    $platform = $platformSettings['settings'];
    $businessName = $businessProfile?->display_name ?? $workspace?->name ?? $platform->product_name;
    $invoiceUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
        'public.invoice.show',
        now()->addDays(30),
        ['token' => $invoice->public_token],
    );
    $reminderType = str($reminderSchedule->direction)->replace('_', ' ')->title();
@endphp

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f6fb;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="padding:28px;border-bottom:1px solid #e5e7eb;">
                        <div style="font-size:20px;font-weight:700;color:#111827;">{{ $businessName }}</div>
                        <div style="font-size:13px;color:#64748b;margin-top:4px;">Invoice payment reminder</div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:28px;">
                        <div style="font-size:15px;line-height:1.7;margin:0 0 24px;color:#475569;">
                            {!! nl2br(e($renderedBody)) !!}
                        </div>

                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;margin-bottom:26px;">
                            <tr>
                                <td style="padding:14px 16px;background:#f8fafc;color:#64748b;font-size:13px;">Invoice number</td>
                                <td align="right" style="padding:14px 16px;background:#f8fafc;font-weight:700;font-size:13px;">{{ $invoice->invoice_number }}</td>
                            </tr>
                            <tr>
                                <td style="padding:14px 16px;border-top:1px solid #e5e7eb;color:#64748b;font-size:13px;">Due date</td>
                                <td align="right" style="padding:14px 16px;border-top:1px solid #e5e7eb;font-size:13px;">{{ $invoice->due_date?->format('M j, Y') ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td style="padding:14px 16px;border-top:1px solid #e5e7eb;color:#64748b;font-size:13px;">Outstanding balance</td>
                                <td align="right" style="padding:14px 16px;border-top:1px solid #e5e7eb;font-size:15px;font-weight:700;">{{ $invoice->formatMoney($outstandingBalance) }}</td>
                            </tr>
                            <tr>
                                <td style="padding:14px 16px;border-top:1px solid #e5e7eb;color:#64748b;font-size:13px;">Reminder type</td>
                                <td align="right" style="padding:14px 16px;border-top:1px solid #e5e7eb;font-size:13px;">{{ $reminderSchedule->name }} - {{ $reminderType }}</td>
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
                            If payment has already been made, please disregard this reminder.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:22px 28px;background:#f8fafc;border-top:1px solid #e5e7eb;color:#64748b;font-size:12px;line-height:1.7;">
                        <strong style="color:#334155;">{{ $businessName }}</strong>
                        @if ($businessProfile?->email)
                            <br>{{ $businessProfile->email }}
                        @endif
                        @if ($businessProfile?->phone)
                            <br>{{ $businessProfile->phone }}
                        @endif
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
