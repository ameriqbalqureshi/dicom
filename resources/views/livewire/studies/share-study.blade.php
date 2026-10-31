<details class="group rounded-xl border border-slate-800 bg-slate-900" @if ($shares->isNotEmpty() || $search !== '' || $status) open @endif>
    <summary class="cursor-pointer select-none px-4 py-3 text-sm font-medium">
        {{ $isOwner ? 'Share this study' : 'Forward this study' }}
        <span class="ml-1 text-slate-400">({{ $shares->count() }} {{ Str::plural('physician', $shares->count()) }})</span>
    </summary>

    <div class="flex flex-col gap-4 border-t border-slate-800 p-4">
        @if ($status)
            <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ $status }}</p>
        @endif

        <div class="flex flex-col gap-2">
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Find a physician by name or email…"
                aria-label="Find a physician by name or email"
                class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">

            @error('search')
                <p class="text-sm text-red-400">{{ $message }}</p>
            @enderror

            @if (mb_strlen(trim($search)) >= App\Livewire\Studies\ShareStudy::MIN_SEARCH_LENGTH)
                <ul class="divide-y divide-slate-800 rounded-md border border-slate-800">
                    @forelse ($matches as $person)
                        <li wire:key="match-{{ $person->id }}" class="flex items-center justify-between gap-4 px-3 py-2 text-sm">
                            <div>
                                <div>{{ $person->name }}</div>
                                <div class="text-xs text-slate-400">{{ $person->email }}</div>
                            </div>

                            @if (in_array($person->id, $idsWithAccess, true))
                                <span class="text-xs text-slate-400">Already has access</span>
                            @else
                                <button type="button" wire:click="share({{ $person->id }})"
                                    class="rounded-md border border-sky-600 px-3 py-1 text-sky-300 hover:bg-sky-950">
                                    Share
                                </button>
                            @endif
                        </li>
                    @empty
                        <li class="px-3 py-2 text-sm text-slate-400">No physician matches “{{ trim($search) }}”.</li>
                    @endforelse
                </ul>
            @elseif ($search !== '')
                <p class="text-xs text-slate-500">Type at least {{ App\Livewire\Studies\ShareStudy::MIN_SEARCH_LENGTH }} characters.</p>
            @endif
        </div>

        @if ($shares->isNotEmpty())
            <div class="flex flex-col gap-2">
                <h3 class="text-xs uppercase tracking-wide text-slate-400">{{ $isOwner ? 'Shared with' : 'You forwarded it to' }}</h3>
                <ul class="divide-y divide-slate-800 rounded-md border border-slate-800">
                    @foreach ($shares as $share)
                        <li wire:key="share-{{ $share->id }}" class="flex items-center justify-between gap-4 px-3 py-2 text-sm">
                            <div>
                                <div>{{ $share->recipient->name }}</div>
                                <div class="text-xs text-slate-400">
                                    {{ $share->recipient->email }}
                                    @if ($share->shared_by !== auth()->id())
                                        · forwarded by {{ $share->sharer->name }}
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                @unless ($share->isAccepted())
                                    <span class="rounded bg-amber-950 px-1.5 py-0.5 text-xs text-amber-300">Waiting for them to accept</span>
                                @endunless
                                <button type="button" wire:click="revoke({{ $share->id }})"
                                    wire:confirm="Remove {{ $share->recipient->name }}'s access? Anyone they forwarded this study to will lose access too."
                                    class="text-red-400 hover:text-red-300">Remove</button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</details>
