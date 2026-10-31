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
                            {{ $search !== '' || $modality !== '' ? 'No studies match your filters.' : 'No studies have been uploaded yet.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $studies->links() }}
</div>
