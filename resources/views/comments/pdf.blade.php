<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Comments · {{ $study->patient_name ?? 'Unknown patient' }}</title>
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
        .comment { border-left: 2pt solid #0369a1; padding-left: 3mm; margin-top: 4mm; page-break-inside: avoid; }
        .footer { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 8pt; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="footer">
        {{ config('app.name') }} · Comments on study #{{ $study->id }} · Printed {{ now()->toDayDateTimeString() }}
    </div>

    <div class="header">
        <h1>Comments · {{ $study->study_description ?: 'Study' }}</h1>
        <div class="muted">{{ config('app.name') }} · These comments are not part of any signed report.</div>
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

    <h2>Comments ({{ $comments->count() }})</h2>

    @forelse ($comments as $comment)
        <div class="comment">
            <div><strong>{{ $comment->authorName() }}</strong> <span class="muted">· {{ $comment->created_at->toDayDateTimeString() }}@if ($comment->isEdited()) · edited {{ $comment->updated_at->toDayDateTimeString() }}@endif</span></div>
            <div class="text">{{ $comment->body }}</div>
        </div>
    @empty
        <p class="muted">No comments.</p>
    @endforelse
</body>
</html>
