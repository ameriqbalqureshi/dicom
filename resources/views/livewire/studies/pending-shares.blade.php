<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Requests</h1>
        <p class="mt-1 text-sm text-slate-400">Studies patients have shared with you. Accept a study to open it.</p>
    </div>

    @if ($status)
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ $status }}</p>
    @endif

    <div class="overflow-x-auto rounded-xl border border-slate-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-900 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-3">From</th>
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3">Study date</th>
                    <th class="px-4 py-3">Description</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse ($shares as $share)
                    <tr wire:key="share-{{ $share->id }}">
                        <td class="px-4 py-3">
                            <div>{{ $share->sharer->name }}</div>
                            <div class="text-xs text-slate-400">{{ $share->sharer->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-300">{{ $share->study->patient_name ?? 'Unknown patient' }}</td>
                        <td class="px-4 py-3 text-slate-300">{{ $share->study->study_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-300">{{ $share->study->study_description ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-3">
                                <button type="button" wire:click="accept({{ $share->id }})"
                                    class="rounded-md border border-sky-600 px-3 py-1 text-sky-300 hover:bg-sky-950">Accept</button>
                                <button type="button" wire:click="decline({{ $share->id }})"
                                    wire:confirm="Decline this study from {{ $share->sharer->name }}?"
                                    class="text-red-400 hover:text-red-300">Decline</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">No studies are waiting for you.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
