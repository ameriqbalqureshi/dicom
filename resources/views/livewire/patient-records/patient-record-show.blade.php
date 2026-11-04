<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-col gap-1">
            <a href="{{ route('patient-records.index') }}" class="text-sm text-slate-400 hover:text-white">&larr; Patients</a>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $patientRecord->name }}</h1>
        </div>

        <div class="flex flex-wrap items-center gap-3 text-sm">
            <a href="{{ route('patient-records.pdf', $patientRecord) }}" target="_blank" class="rounded-md border border-sky-600 px-3 py-1.5 text-sky-300 hover:bg-sky-950">Print PDF</a>
            <a href="{{ route('patient-records.pdf', ['patientRecord' => $patientRecord, 'notes' => 0]) }}" target="_blank" class="rounded-md border border-slate-700 px-3 py-1.5 text-slate-300 hover:border-slate-500">Print without notes</a>
            <a href="{{ route('patient-records.edit', $patientRecord) }}" class="rounded-md border border-slate-700 px-3 py-1.5 text-slate-300 hover:border-slate-500">Edit</a>
            <button type="button" wire:click="delete"
                wire:confirm="Delete the record of {{ $patientRecord->name }} and all its notes? The studies linked to it are not deleted. This cannot be undone."
                class="rounded-md border border-red-800 px-3 py-1.5 text-red-300 hover:bg-red-950">Delete</button>
        </div>
    </div>

    @if (session('status'))
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ session('status') }}</p>
    @endif

    <section class="rounded-xl border border-slate-800 bg-slate-900 p-6">
        <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
            <div class="flex flex-col gap-1">
                <dt class="text-slate-400">Medical record number</dt>
                <dd>{{ $patientRecord->medical_record_number ?? '—' }}</dd>
            </div>
            <div class="flex flex-col gap-1">
                <dt class="text-slate-400">Date of birth</dt>
                <dd>
                    @if ($patientRecord->date_of_birth)
                        {{ $patientRecord->date_of_birth->format('Y-m-d') }} <span class="text-slate-400">({{ $patientRecord->date_of_birth->age }} years)</span>
                    @else
                        —
                    @endif
                </dd>
            </div>
            <div class="flex flex-col gap-1">
                <dt class="text-slate-400">Sex</dt>
                <dd>{{ $patientRecord->sex?->label() ?? '—' }}</dd>
            </div>
            <div class="flex flex-col gap-1">
                <dt class="text-slate-400">Phone</dt>
                <dd>{{ $patientRecord->phone ?? '—' }}</dd>
            </div>
        </dl>

        @if ($patientRecord->riskFactors() !== [])
            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-800 pt-4 text-sm">
                <span class="text-slate-400">Risk factors</span>
                @foreach ($patientRecord->riskFactors() as $riskFactor)
                    <span class="rounded bg-slate-800 px-2 py-0.5 text-xs text-slate-200">{{ $riskFactor->label() }}</span>
                @endforeach
            </div>
        @endif
    </section>

    <section class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
        <h2 class="font-semibold">Clinical details</h2>

        @php($filledSections = array_filter($sections, fn (string $label, string $section): bool => filled($patientRecord->{$section}), ARRAY_FILTER_USE_BOTH))

        @forelse ($filledSections as $section => $label)
            <div wire:key="section-{{ $section }}" class="flex flex-col gap-1 text-sm">
                <h3 class="text-slate-400">{{ $label }}</h3>
                <p class="whitespace-pre-line text-slate-200">{{ $patientRecord->{$section} }}</p>
            </div>
        @empty
            <p class="text-sm text-slate-400">
                No clinical details yet.
                <a href="{{ route('patient-records.edit', $patientRecord) }}" class="text-sky-400 hover:text-sky-300">Add them</a>
            </p>
        @endforelse
    </section>

    <livewire:patient-records.patient-studies :patient-record="$patientRecord" />

    <livewire:patient-records.patient-notes :patient-record="$patientRecord" />
</div>
