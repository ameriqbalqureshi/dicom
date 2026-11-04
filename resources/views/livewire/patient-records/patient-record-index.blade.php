<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Patients</h1>
            <p class="mt-1 text-sm text-slate-400">Your own records of your patients. Only you can open them.</p>
        </div>

        @if ($limitMessage === null)
            <a href="{{ route('patient-records.create') }}" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">
                New patient
            </a>
        @endif
    </div>

    @if (session('status'))
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ session('status') }}</p>
    @endif

    @if ($limitMessage !== null)
        <p class="rounded-md border border-amber-800 bg-amber-950/60 px-4 py-2 text-sm text-amber-200">{{ $limitMessage }}</p>
    @endif

    <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search name, record number or phone…"
        aria-label="Search patients"
        class="rounded-md border border-slate-700 bg-slate-900 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">

    <div class="overflow-x-auto rounded-xl border border-slate-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-900 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3">Record no.</th>
                    <th class="px-4 py-3">Date of birth</th>
                    <th class="px-4 py-3">Sex</th>
                    <th class="px-4 py-3 text-right">Studies</th>
                    <th class="px-4 py-3 text-right">Notes</th>
                    <th class="px-4 py-3">Last changed</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse ($records as $record)
                    <tr wire:key="record-{{ $record->id }}" class="hover:bg-slate-900/60">
                        <td class="px-4 py-3">
                            <a href="{{ route('patient-records.show', $record) }}" class="font-medium text-sky-400 hover:text-sky-300">{{ $record->name }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-300">{{ $record->medical_record_number ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-300">{{ $record->date_of_birth?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-300">{{ $record->sex?->label() ?? '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-300">{{ $record->linked_studies_count }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-300">{{ $record->notes_count }}</td>
                        <td class="px-4 py-3 text-slate-300">{{ $record->updated_at->toFormattedDateString() }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-slate-400">
                            {{ $search !== '' ? 'No patients match your search.' : 'You have not added any patients yet.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $records->links() }}
</div>
