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
            <button type="button" wire:click="delete"
                wire:confirm="{{ $study->uploader()->is(auth()->user())
                    ? 'Delete this study? Physicians you shared it with can choose to keep their own copy within '.config('studies.handover_days').' days; otherwise it is deleted with all of its images.'
                    : 'Delete this study and all of its images for everyone? This cannot be undone.' }}"
                class="rounded-md border border-red-800 px-4 py-2 text-sm text-red-300 hover:bg-red-950">
                Delete study
            </button>
        @endcan

        @can('removeFromList', $study)
            <button type="button" wire:click="removeFromList"
                wire:confirm="Remove this study from your list? You will no longer be able to open it, unless it is shared with you again. Physicians you forwarded it to keep their access, and your reports stay with the study."
                class="rounded-md border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-800">
                Remove from my list
            </button>
        @endcan
    </div>

    @if (session('status'))
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ session('status') }}</p>
    @endif

    @can('keep', $study)
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-md border border-amber-800 bg-amber-950/60 px-4 py-3 text-sm text-amber-200">
            <p>
                {{ $study->formerOwner?->name ?? 'The owner' }} no longer keeps this study.
                Keep it as your own by {{ $study->handover_ends_at->toFormattedDateString() }}, or remove it from your list.
                If nobody keeps it, it is deleted on that date.
            </p>
            <button type="button" wire:click="keep"
                wire:confirm="Keep this study as your own? It will count towards your package like a study you uploaded."
                class="rounded-md border border-sky-600 px-4 py-2 text-sky-300 hover:bg-sky-950">
                Keep study
            </button>
            @error('keep') <p class="w-full text-red-400">{{ $message }}</p> @enderror
        </div>
    @endcan

    <livewire:studies.study-reports :study="$study" />

    <livewire:studies.study-comments :study="$study" />

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
