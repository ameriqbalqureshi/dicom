<section class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h2 class="font-semibold">
            Studies
            <span class="ml-1 font-normal text-slate-400">({{ $linkedStudies->count() }})</span>
        </h2>

        @unless ($linking)
            <button type="button" wire:click="startLinking" class="rounded-md border border-sky-600 px-3 py-1.5 text-sm text-sky-300 hover:bg-sky-950">Attach study</button>
        @endunless
    </div>

    @if ($linkedStudies->isEmpty())
        <p class="text-sm text-slate-400">No studies attached.</p>
    @endif

    @foreach ($linkedStudies as $linkedStudy)
        @php($canOpen = $linkedStudy->study !== null && auth()->user()->can('view', $linkedStudy->study))
        <div wire:key="linked-study-{{ $linkedStudy->id }}" class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-slate-800 p-3 text-sm">
            <div>
                @if ($canOpen)
                    <a href="{{ route('studies.show', $linkedStudy->study) }}" class="font-medium text-sky-400 hover:text-sky-300">{{ $linkedStudy->study_description ?: 'Study' }}</a>
                @else
                    <span class="font-medium text-slate-300">{{ $linkedStudy->study_description ?: 'Study' }}</span>
                    <span class="ml-1 text-xs text-amber-300">No longer available</span>
                @endif
                <div class="text-xs text-slate-400">
                    {{ $linkedStudy->study_date?->format('Y-m-d') ?? 'No study date' }}
                    @if ($canOpen && filled($linkedStudy->study->patient_name))
                        · {{ $linkedStudy->study->patient_name }}
                    @endif
                </div>
            </div>
            <button type="button" wire:click="unlink({{ $linkedStudy->id }})" wire:confirm="Remove this study from the record? The study itself is not deleted."
                class="text-red-400 hover:text-red-300">Remove</button>
        </div>
    @endforeach

    @if ($linking)
        <div class="flex flex-col gap-3 rounded-md border border-sky-800 p-4 text-sm">
            <div class="flex flex-wrap items-center gap-3">
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search your studies by patient, ID, accession or description…"
                    aria-label="Search studies to attach"
                    class="min-w-72 flex-1 rounded-md border border-slate-700 bg-slate-950 px-3 py-2 focus:border-sky-500 focus:outline-none">
                <button type="button" wire:click="stopLinking" class="text-slate-400 hover:text-white">Done</button>
            </div>

            @forelse ($linkableStudies as $study)
                <div wire:key="linkable-study-{{ $study->id }}" class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 pt-3">
                    <div>
                        <span class="font-medium">{{ $study->patient_name ?? 'Unknown patient' }}</span>
                        <div class="text-xs text-slate-400">
                            {{ $study->study_date?->format('Y-m-d') ?? 'No study date' }}
                            @if (filled($study->study_description)) · {{ $study->study_description }} @endif
                            @if (filled($study->patient_id)) · ID {{ $study->patient_id }} @endif
                        </div>
                    </div>
                    <button type="button" wire:click="link({{ $study->id }})" class="rounded-md border border-sky-600 px-3 py-1.5 text-sky-300 hover:bg-sky-950">Attach</button>
                </div>
            @empty
                <p class="text-slate-400">
                    No studies to attach{{ $search !== '' ? ' match your search' : '' }}.
                    <a href="{{ route('studies.upload') }}" class="text-sky-400 hover:text-sky-300">Upload one</a>
                </p>
            @endforelse
        </div>
    @endif
</section>
