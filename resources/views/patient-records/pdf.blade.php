<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Patient record · {{ $record->name }}</title>
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
        table.studies { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
        table.studies td { padding: 1mm 2mm 1mm 0; vertical-align: top; }
        .text { white-space: pre-line; }
        .note { margin-top: 3mm; border-left: 2pt solid #0369a1; padding-left: 3mm; }
        .footer { position: fixed; bottom: -14mm; left: 0; right: 0; font-size: 8pt; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="footer">
        {{ config('app.name') }} · Patient record #{{ $record->id }} · Printed {{ now()->toDayDateTimeString() }} · Confidential
    </div>

    <div class="header">
        <h1>{{ $record->name }}</h1>
        <div class="muted">
            Patient record kept by {{ $owner->name }}@if ($owner->isVerifiedPhysician() && filled($owner->qualifications)), {{ $owner->qualifications }}@endif
            @if ($owner->isVerifiedPhysician() && filled($owner->registration_number))
                · Registration no. {{ $owner->registration_number }}
            @endif
        </div>
    </div>

    <table class="patient">
        <tr>
            <td class="label">Record number</td><td>{{ $record->medical_record_number ?? '—' }}</td>
            <td class="label">Date of birth</td><td>{{ $record->date_of_birth?->format('Y-m-d') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Sex</td><td>{{ $record->sex?->label() ?? '—' }}</td>
            <td class="label">Phone</td><td>{{ $record->phone ?? '—' }}</td>
        </tr>
    </table>

    @if ($record->riskFactors() !== [])
        <h2>Risk factors</h2>
        <div>{{ collect($record->riskFactors())->map->label()->join(', ') }}</div>
    @endif

    @foreach (App\Models\PatientRecord::CLINICAL_SECTIONS as $section => $label)
        @if (filled($record->{$section}))
            <h2>{{ $label }}</h2>
            <div class="text">{{ $record->{$section} }}</div>
        @endif
    @endforeach

    @if ($linkedStudies->isNotEmpty())
        <h2>Studies</h2>
        <table class="studies">
            @foreach ($linkedStudies as $linkedStudy)
                <tr>
                    <td style="width: 25%;">{{ $linkedStudy->study_date?->format('Y-m-d') ?? 'No study date' }}</td>
                    <td>{{ $linkedStudy->study_description ?: 'Study' }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($notes->isNotEmpty())
        <h2>Notes</h2>
        @foreach ($notes as $note)
            <div class="note">
                <strong>{{ $note->noted_on->toFormattedDateString() }}</strong>
                <div class="text">{{ $note->body }}</div>
            </div>
        @endforeach
    @endif
</body>
</html>
