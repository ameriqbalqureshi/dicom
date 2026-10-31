<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Report · {{ $study->patient_name ?? 'Unknown patient' }}</title>
    <style>
        @page { margin: 22mm 18mm 24mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5pt; color: #111827; line-height: 1.45; }
        h1 { font-size: 16pt; margin: 0 0 2mm; }
        h2 { font-size: 9pt; text-transform: uppercase; letter-spacing: 0.06em; color: #4b5563; margin: 6mm 0 1.5mm; }
        .muted { color: #6b7280; font-size: 9pt; }
        .header { border-bottom: 1.5pt solid #0f172a; padding-bottom: 3mm; margin-bottom: 4mm; }
        table.patient { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
        table.patient td { padding: 1mm 2mm 1mm 0; vertical-align: top; }
        table.patient td.label { color: #6b7280; width: 22%; }
        .text { white-space: pre-line; }
        .impression { font-weight: bold; }
        .addendum { border-left: 2pt solid #0369a1; padding-left: 3mm; margin-top: 4mm; }
        .signature { margin-top: 12mm; page-break-inside: avoid; }
        .signature img { max-height: 22mm; max-width: 60mm; }
        .signature .line { border-top: 0.75pt solid #111827; width: 90mm; padding-top: 1.5mm; }
        .draft { position: fixed; top: 90mm; left: 0; right: 0; text-align: center; font-size: 72pt; color: #fca5a5; opacity: 0.35; transform: rotate(-30deg); }
        .footer { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 8pt; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    @unless ($report->isFinal())
        <div class="draft">DRAFT</div>
    @endunless

    <div class="footer">
        {{ config('app.name') }} · Report #{{ $report->id }} · {{ $report->isFinal() ? 'Signed '.$report->finalized_at->toDayDateTimeString() : 'Draft, not signed' }}
    </div>

    <div class="header">
        <h1>{{ $study->study_description ?: 'Study report' }}</h1>
        <div class="muted">{{ config('app.name') }}</div>
    </div>

    <table class="patient">
        <tr>
            <td class="label">Patient</td><td>{{ $study->patient_name ?? '—' }}</td>
            <td class="label">Patient ID</td><td>{{ $study->patient_id ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Date of birth</td><td>{{ $study->patient_birth_date?->format('Y-m-d') ?? '—' }}</td>
            <td class="label">Sex</td><td>{{ $study->patient_sex ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Study date</td><td>{{ $study->study_date?->format('Y-m-d') ?? '—' }}</td>
            <td class="label">Accession</td><td>{{ $study->accession_number ?? '—' }}</td>
        </tr>
    </table>

    @if (filled($report->indication))
        <h2>Clinical indication</h2>
        <div class="text">{{ $report->indication }}</div>
    @endif

    <h2>Findings</h2>
    <div class="text">{{ $report->findings }}</div>

    <h2>Impression</h2>
    <div class="text impression">{{ $report->impression }}</div>

    <div class="signature">
        @if ($signature)
            <img src="{{ $signature }}" alt="Signature">
        @endif
        <div class="line">
            <strong>{{ $report->authorName() }}</strong><br>
            @if (filled($qualifications))
                {{ $qualifications }}<br>
            @endif
            @if (filled($registrationNumber))
                Registration no. {{ $registrationNumber }}<br>
            @endif
            <span class="muted">{{ $report->isFinal() ? 'Electronically signed '.$report->finalized_at->toDayDateTimeString() : 'Not signed' }}</span>
        </div>
    </div>

    @foreach ($report->addenda as $addendum)
        <div class="addendum">
            <h2 style="margin-top: 0;">Addendum · {{ $addendum->created_at->toDayDateTimeString() }}</h2>
            <div class="text">{{ $addendum->body }}</div>
            <div class="muted">{{ $report->authorName() }}</div>
        </div>
    @endforeach
</body>
</html>
