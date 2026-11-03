<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Requests</h1>
        <p class="mt-1 text-sm text-slate-400">Studies from people sharing with you for the first time, and studies their owners no longer keep. Accepting a study lets that person's later shares through straight away; you can change this on the <a href="{{ route('shares.senders') }}" class="text-sky-400 hover:text-sky-300">Senders</a> page.</p>
    </div>

    @if ($status)
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ $status }}</p>
    @endif

    @if ($handovers->isNotEmpty())
        <div class="flex flex-col gap-2">
            <h2 class="text-lg font-semibold">Keep or remove</h2>
            <p class="text-sm text-slate-400">
                The owners of these studies have deleted them, or they were patients' studies that have expired. Keep a study to make it your own:
                it then counts towards your package. Studies nobody keeps are deleted on the date shown.
            </p>
            <div class="overflow-x-auto rounded-xl border border-slate-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-900 text-xs uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-4 py-3">Patient</th>
                            <th class="px-4 py-3">Study date</th>
                            <th class="px-4 py-3">Description</th>
                            <th class="px-4 py-3">Given up by</th>
                            <th class="px-4 py-3">Deleted on</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach ($handovers as $study)
                            <tr wire:key="handover-{{ $study->id }}">
                                <td class="px-4 py-3">
                                    <a href="{{ route('studies.show', $study) }}" class="text-sky-400 hover:text-sky-300">{{ $study->patient_name ?? 'Unknown patient' }}</a>
                                </td>
                                <td class="px-4 py-3 text-slate-300">{{ $study->study_date?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $study->study_description ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $study->formerOwner?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-amber-300">{{ $study->handover_ends_at->toFormattedDateString() }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-3">
                                        <button type="button" wire:click="keep({{ $study->id }})"
                                            wire:confirm="Keep this study as your own? It will count towards your package like a study you uploaded."
                                            class="rounded-md border border-sky-600 px-3 py-1 text-sky-300 hover:bg-sky-950">Keep</button>
                                        <button type="button" wire:click="remove({{ $study->id }})"
                                            wire:confirm="Remove this study from your list? You will no longer be able to open it."
                                            class="text-red-400 hover:text-red-300">Remove</button>
                                    </div>
                                    @error("handover.{$study->id}") <p class="mt-1 text-red-400">{{ $message }}</p> @enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <h2 class="text-lg font-semibold">Waiting for you to accept</h2>
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
                                <button type="button" wire:click="blockSender({{ $share->id }})"
                                    wire:confirm="Block {{ $share->sharer->name }}? Their shares will no longer reach you, and every study they shared with you is removed from your list."
                                    class="text-red-400 hover:text-red-300">Block sender</button>
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
