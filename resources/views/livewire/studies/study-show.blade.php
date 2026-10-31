<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('studies.index') }}" class="text-sm text-slate-400 hover:text-white">&larr; Studies</a>
            <h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ $study->patient_name ?? 'Unknown patient' }}</h1>
            <dl class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-sm text-slate-400">
                <div><dt class="inline">Patient ID:</dt> <dd class="inline text-slate-200">{{ $study->patient_id ?? '—' }}</dd></div>
                <div><dt class="inline">Sex:</dt> <dd class="inline text-slate-200">{{ $study->patient_sex ?? '—' }}</dd></div>
                <div><dt class="inline">Born:</dt> <dd class="inline text-slate-200">{{ $study->patient_birth_date?->format('Y-m-d') ?? '—' }}</dd></div>
                <div><dt class="inline">Study date:</dt> <dd class="inline text-slate-200">{{ $study->study_date?->format('Y-m-d') ?? '—' }}</dd></div>
                <div><dt class="inline">Accession:</dt> <dd class="inline text-slate-200">{{ $study->accession_number ?? '—' }}</dd></div>
                <div><dt class="inline">Description:</dt> <dd class="inline text-slate-200">{{ $study->study_description ?? '—' }}</dd></div>
                @if ($study->expires_at)
                    <div><dt class="inline">Deleted on:</dt> <dd class="inline text-amber-300">{{ $study->expires_at->toDayDateTimeString() }} ({{ $study->expires_at->diffForHumans() }})</dd></div>
                @endif
                @unless ($study->uploader()->is(auth()->user()))
                    <div><dt class="inline">Uploaded by:</dt> <dd class="inline text-slate-200">{{ $study->uploader?->name ?? '—' }}</dd></div>
                @endunless
            </dl>
        </div>

        @can('delete', $study)
            <button type="button" wire:click="delete" wire:confirm="Delete this study and all of its images? This cannot be undone."
                class="rounded-md border border-red-800 px-4 py-2 text-sm text-red-300 hover:bg-red-950">
                Delete study
            </button>
        @endcan
    </div>

    <livewire:studies.study-reports :study="$study" />

    @can('share', $study)
        <livewire:studies.share-study :study="$study" />
    @endcan

    @can('manageGuestLinks', $study)
        <livewire:studies.guest-links :study="$study" />
    @endcan

    @if ($viewerSeries === [])
        <p class="rounded-xl border border-slate-800 px-4 py-10 text-center text-slate-400">This study has no images.</p>
    @else
        <x-dicom-viewer :series="$viewerSeries" />
    @endif
</div>
