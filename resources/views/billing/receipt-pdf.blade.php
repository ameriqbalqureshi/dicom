<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment receipt {{ $subscription->receiptNumber() }}</title>
    <style>
        @page { margin: 22mm 18mm 24mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; color: #111827; line-height: 1.45; }
        h1 { font-size: 16pt; margin: 0 0 2mm; }
        h2 { font-size: 9pt; text-transform: uppercase; letter-spacing: 0.06em; color: #4b5563; margin: 7mm 0 1.5mm; }
        .muted { color: #6b7280; font-size: 9pt; }
        .header { border-bottom: 1.5pt solid #0f172a; padding-bottom: 3mm; margin-bottom: 4mm; }
        table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
        td { padding: 1mm 2mm 1mm 0; vertical-align: top; }
        td.label { color: #6b7280; width: 32%; }
        .total { margin-top: 8mm; border-top: 0.75pt solid #111827; padding-top: 2mm; font-size: 13pt; font-weight: bold; }
        .footer { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 8pt; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="footer">
        {{ config('app.name') }}@if (config('site.contact.email')) · {{ config('site.contact.email') }}@endif @if (config('site.contact.phone')) · {{ config('site.contact.phone') }}@endif
    </div>

    <div class="header">
        <h1>Payment receipt</h1>
        <div class="muted">{{ config('app.name') }}</div>
    </div>

    <table>
        <tr><td class="label">Receipt number</td><td>{{ $subscription->receiptNumber() }}</td></tr>
        <tr><td class="label">Payment confirmed</td><td>{{ $subscription->reviewed_at->toFormattedDateString() }}</td></tr>
    </table>

    <h2>Received from</h2>
    <table>
        <tr><td class="label">Name</td><td>{{ $subscription->user->name }}</td></tr>
        <tr><td class="label">Email</td><td>{{ $subscription->user->email }}</td></tr>
        <tr><td class="label">User ID</td><td>{{ $subscription->user->id }}</td></tr>
    </table>

    <h2>Payment</h2>
    <table>
        <tr><td class="label">Package</td><td>{{ $subscription->package->name }}</td></tr>
        <tr><td class="label">Period</td><td>{{ $subscription->starts_at->toFormattedDateString() }} to {{ $subscription->ends_at?->toFormattedDateString() ?? 'no end date' }}</td></tr>
        <tr><td class="label">Paid into</td><td>{{ $subscription->payment_method ?? '—' }}</td></tr>
        <tr><td class="label">Transaction reference</td><td>{{ $subscription->payment_reference ?? '—' }}</td></tr>
    </table>

    <div class="total">Amount received: {{ number_format($subscription->amount) }} {{ $currency }}</div>
</body>
</html>
