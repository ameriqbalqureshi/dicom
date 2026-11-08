<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Commission statement {{ $payout->statementNumber() }}</title>
    <style>
        @page { margin: 22mm 18mm 24mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; color: #111827; line-height: 1.45; }
        h1 { font-size: 16pt; margin: 0 0 2mm; }
        h2 { font-size: 9pt; text-transform: uppercase; letter-spacing: 0.06em; color: #4b5563; margin: 7mm 0 1.5mm; }
        .muted { color: #6b7280; font-size: 9pt; }
        .header { border-bottom: 1.5pt solid #0f172a; padding-bottom: 3mm; margin-bottom: 4mm; }
        table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
        td, th { padding: 1mm 2mm 1mm 0; vertical-align: top; text-align: left; }
        td.label { color: #6b7280; width: 32%; }
        .lines th { border-bottom: 0.75pt solid #111827; font-size: 8.5pt; color: #4b5563; }
        .lines td { border-bottom: 0.25pt solid #e5e7eb; }
        .right { text-align: right; padding-right: 0; }
        .total { margin-top: 8mm; border-top: 0.75pt solid #111827; padding-top: 2mm; font-size: 13pt; font-weight: bold; }
        .footer { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 8pt; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="footer">
        {{ config('app.name') }}@if (config('site.contact.email')) · {{ config('site.contact.email') }}@endif @if (config('site.contact.phone')) · {{ config('site.contact.phone') }}@endif
    </div>

    <div class="header">
        <h1>Commission statement</h1>
        <div class="muted">{{ config('app.name') }}</div>
    </div>

    <table>
        <tr><td class="label">Statement number</td><td>{{ $payout->statementNumber() }}</td></tr>
        <tr><td class="label">Paid on</td><td>{{ $payout->paid_at->toFormattedDateString() }}</td></tr>
        <tr><td class="label">Paid to</td><td>{{ $payout->officer_name }}</td></tr>
        <tr><td class="label">Paid through</td><td>{{ $payout->payment_method }}</td></tr>
        <tr><td class="label">Transaction reference</td><td>{{ $payout->payment_reference ?? '—' }}</td></tr>
    </table>

    <h2>What this covers</h2>
    <table class="lines">
        <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Client or reason</th>
            <th class="right">Payment</th>
            <th class="right">Rate</th>
            <th class="right">Amount</th>
        </tr>
        @foreach ($lines as $line)
            <tr>
                <td>{{ $line->created_at->toFormattedDateString() }}</td>
                <td>{{ $line->type->label() }}</td>
                <td>{{ $line->client_name ?? $line->note }}</td>
                <td class="right">{{ $line->base_amount ? number_format($line->base_amount) : '—' }}</td>
                <td class="right">{{ $line->rate_percent !== null ? rtrim(rtrim(number_format($line->rate_percent, 2), '0'), '.').'%' : '—' }}</td>
                <td class="right">{{ number_format($line->amount) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="total">Total paid: {{ number_format($payout->amount) }} {{ $currency }}</div>
</body>
</html>
