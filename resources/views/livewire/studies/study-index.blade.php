<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-semibold tracking-tight">Studies</h1>

        @can('create', App\Models\Study::class)
            <a href="{{ route('studies.upload') }}" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">
                Upload DICOM
            </a>
        @endcan
    </div>

    @if (session('status'))
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ session('status') }}</p>
    @endif

    @if ($pendingShareCount > 0)
        <a href="{{ route('shares.pending') }}" class="rounded-md border border-sky-800 bg-sky-950 px-4 py-2 text-sm text-sky-300 hover:bg-sky-900">
            {{ $pendingShareCount }} {{ Str::plural('study', $pendingShareCount) }} from patients {{ $pendingShareCount === 1 ? 'is' : 'are' }} waiting for you to accept. Review &rarr;
        </a>
    @endif

    @if ($sharers->isNotEmpty() || $from !== null)
        <nav class="flex flex-wrap items-center gap-2 text-sm" aria-label="Study owners">
            <a href="{{ route('studies.index') }}"
                @class(['rounded-full border px-3 py-1', 'border-sky-600 bg-sky-950/60 text-white' => $from === null, 'border-slate-700 text-slate-300 hover:border-slate-500' => $from !== null])>
                {{ auth()->user()->isAdmin() ? 'All studies' : 'My studies' }}
            </a>
            <span class="ml-2 text-xs uppercase tracking-wide text-slate-500">Shared with you by</span>
            @foreach ($sharers as $person)
                <a href="{{ route('studies.index', ['from' => $person->id]) }}" wire:key="sharer-{{ $person->id }}"
                    @class(['rounded-full border px-3 py-1', 'border-sky-600 bg-sky-950/60 text-white' => $from === $person->id, 'border-slate-700 text-slate-300 hover:border-slate-500' => $from !== $person->id])>
                    {{ $person->name }} <span class="text-slate-400">({{ $person->shared_studies_count }})</span>
                </a>
            @endforeach
        </nav>
    @endif

    <div class="flex flex-wrap gap-3">
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search patient, ID, accession or description…"
            aria-label="Search studies"
            class="min-w-72 flex-1 rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">

        <select wire:model.live="modality" aria-label="Filter by modality"
            class="rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
            <option value="">All modalities</option>
            @foreach ($modalities as $option)
                <option value="{{ $option }}" wire:key="modality-{{ $option }}">{{ $option }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-900 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3">Patient ID</th>
                    <th class="px-4 py-3">Study date</th>
                    <th class="px-4 py-3">Description</th>
                    <th class="px-4 py-3">Modality</th>
                    <th class="px-4 py-3 text-right">Series / Images</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse ($studies as $study)
                    <tr wire:key="study-{{ $study->id }}" class="hover:bg-slate-900/60">
                        <td class="px-4 py-3">
                            <a href="{{ route('studies.show', $study) }}" class="font-medium text-sky-400 hover:text-sky-300">
                                {{ $study->patient_name ?? 'Unknown patient' }}
                            </a>
                            @if ($study->expires_at)
                                <div class="text-xs text-amber-300">Deleted {{ $study->expires_at->diffForHumans() }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-300">{{ $study->patient_id ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-300">{{ $study->study_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-300">{{ $study->study_description ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex gap-1">
                                @foreach ($study->series->pluck('modality')->filter()->unique() as $studyModality)
                                    <span class="rounded bg-slate-800 px-1.5 py-0.5 text-xs text-slate-300">{{ $studyModality }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-300">{{ $study->series_count }} / {{ $study->instances_count }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400">
                            @if ($search !== '' || $modality !== '')
                                No studies match your filters.
                            @elseif ($from !== null)
                                No studies are shared with you by this person.
                            @else
                                No studies have been uploaded yet.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $studies->links() }}
</div>
