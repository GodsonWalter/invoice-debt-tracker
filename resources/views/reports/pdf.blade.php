<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 24px 35px; }
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 9px; }
        h1 { margin: 0 0 4px; font-size: 18px; }
        h2 { margin: 14px 0 6px; font-size: 12px; }
        .muted { color: #64748b; }
        .header { border-bottom: 1px solid #cbd5e1; padding-bottom: 8px; }
        .logo { max-width: 100px; max-height: 45px; margin-bottom: 5px; }
        .meta { margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { background: #e2e8f0; text-align: left; }
        th, td { border: 1px solid #cbd5e1; padding: 4px; }
        footer { position: fixed; bottom: -18px; left: 0; right: 0; text-align: center; color: #64748b; font-size: 8px; }
    </style>
</head>
<body>
    <div class="header">
        @if ($logoDataUri)<img class="logo" src="{{ $logoDataUri }}" alt="{{ $business?->display_name ?? $workspace->name }} logo">@endif
        <h1>{{ $report['title'] }}</h1>
        <div>{{ $business?->display_name ?? $workspace->name }}</div>
        @if ($business?->formatted_address)<div class="muted">{{ $business->formatted_address }}</div>@endif
        @if ($business?->email)<div class="muted">{{ $business->email }}</div>@endif
        <div class="meta muted">Generated {{ $report['generatedAt']->format('d M Y H:i') }} | Period: {{ $report['period']->label }} | Currency: {{ $report['currency']['code'] ?? 'Workspace currency' }}</div>
        <div class="meta muted">Filters: @foreach ($report['filters']->toQuery() as $key => $value){{ $key }}={{ $value }}@if (! $loop->last), @endif @endforeach</div>
    </div>
    <h2>Summary</h2>
    <table><tbody>
        @foreach ($report['summary'] as $key => $value)
            @if (is_scalar($value))
                <tr><th>{{ ucfirst(str_replace('_', ' ', $key)) }}</th><td>{{ in_array($key, ['total', 'paid', 'outstanding', 'average_payment', 'average_invoice', 'total_invoiced', 'total_paid', 'overdue_balance'], true) ? $workspace->formatMoney($value) : $value }}</td></tr>
            @endif
        @endforeach
    </tbody></table>
    <h2>Details</h2>
    <table>
        <thead><tr>@foreach ($report['columns'] as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>@foreach ($report['columns'] as $key => $label)<td>{{ in_array($key, ['amount', 'total_amount', 'amount_paid', 'outstanding_balance', 'total_invoiced', 'total_paid', 'overdue_balance'], true) ? $workspace->formatMoney($row[$key] ?? 0) : ($row[$key] ?? '') }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($report['columns']) }}">No records match the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
    <footer>{{ $workspace->name }} | {{ $report['title'] }} | Page <script type="text/php">if (isset($pdf)) { $pdf->page_text(520, 820, "{PAGE_NUM} / {PAGE_COUNT}", null, 8, array(100,100,100)); }</script></footer>
</body>
</html>
