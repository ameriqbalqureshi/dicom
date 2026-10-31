<details class="group rounded-xl border border-slate-800 bg-slate-900" @if ($links->isNotEmpty() || $issued || $status) open @endif>
    <summary class="cursor-pointer select-none px-4 py-3 text-sm font-medium">
        Guest links
        <span class="ml-1 text-slate-400">({{ $links->count() }} active)</span>
    </summary>

    <div class="flex flex-col gap-4 border-t border-slate-800 p-4">
        <p class="text-xs text-slate-400">
            A guest link lets someone without an account view this study for {{ $lifetimeHours }} hours. They can read its final reports, but cannot download the images, share or change it.
        </p>

        @if ($status)
            <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ $status }}</p>
        @endif

        @if ($issued)
            <div class="flex flex-col gap-3 rounded-md border border-sky-800 bg-sky-950/40 p-4 text-sm" x-data="{ copied: null }">
                <p class="font-medium text-sky-200">Guest link created. Copy it now: it will not be shown again.</p>

                <div class="flex items-center gap-2">
                    <input type="text" readonly value="{{ $issued['url'] }}" aria-label="Guest link"
                        class="min-w-0 flex-1 rounded-md border border-slate-700 bg-slate-950 px-3 py-2 font-mono text-xs" @focus="$event.target.select()">
                    <button type="button" @click="navigator.clipboard.writeText(@js($issued['url'])); copied = 'link'"
                        class="rounded-md border border-sky-600 px-3 py-2 text-sky-300 hover:bg-sky-950">
                        <span x-text="copied === 'link' ? 'Copied' : 'Copy link'"></span>
                    </button>
                </div>

                @if ($issued['pin'])
                    <div class="flex items-center gap-3">
                        <span class="text-slate-300">PIN:</span>
                        <span class="font-mono text-lg tracking-widest text-white">{{ $issued['pin'] }}</span>
                        <button type="button" @click="navigator.clipboard.writeText(@js($issued['pin'])); copied = 'pin'"
                            class="rounded-md border border-slate-600 px-2 py-1 text-xs text-slate-300 hover:bg-slate-800">
                            <span x-text="copied === 'pin' ? 'Copied' : 'Copy PIN'"></span>
                        </button>
                    </div>
                    <p class="text-xs text-amber-300">Send the PIN separately from the link, e.g. by text message or phone.</p>
                @endif

                <p class="text-xs text-slate-400">Expires {{ $issued['expiresAt'] }}.</p>

                <div class="flex items-center gap-4">
                    <a href="mailto:?subject={{ rawurlencode('Study shared with you') }}&body={{ rawurlencode("You can view the study at this link until {$issued['expiresAt']}:\n\n{$issued['url']}".($issued['pin'] ? "\n\nThe PIN will be sent to you separately." : '')) }}"
                        class="text-sky-400 hover:text-sky-300">Email the link</a>
                    <button type="button" wire:click="dismissIssued" class="text-slate-400 hover:text-white">Done</button>
                </div>
            </div>
        @elseif ($canCreate)
            <form wire:submit="create" class="flex flex-wrap items-center gap-4">
                <label class="flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" wire:model="requirePin" class="rounded border-slate-600 bg-slate-950 accent-sky-500">
                    Require a PIN
                </label>
                <button type="submit" class="rounded-md border border-sky-600 px-3 py-1.5 text-sm text-sky-300 hover:bg-sky-950">Create guest link</button>
            </form>
        @endif

        @if ($links->isNotEmpty())
            <ul class="divide-y divide-slate-800 rounded-md border border-slate-800">
                @foreach ($links as $link)
                    <li wire:key="guest-link-{{ $link->id }}" class="flex items-center justify-between gap-4 px-3 py-2 text-sm">
                        <div>
                            <div>
                                Created {{ $link->created_at->diffForHumans() }}
                                @unless ($link->created_by === auth()->id())
                                    by {{ $link->creator->name }}
                                @endunless
                                @if ($link->requiresPin())
                                    <span class="ml-1 rounded bg-slate-800 px-1.5 py-0.5 text-xs text-slate-300">PIN</span>
                                @endif
                            </div>
                            <div class="text-xs text-slate-400">
                                Expires {{ $link->expires_at->diffForHumans() }} ·
                                {{ $link->view_count }} {{ Str::plural('view', $link->view_count) }}@if ($link->last_viewed_at), last {{ $link->last_viewed_at->diffForHumans() }}@endif
                            </div>
                        </div>

                        <button type="button" wire:click="revoke({{ $link->id }})" wire:confirm="Revoke this guest link? It will stop working immediately."
                            class="text-red-400 hover:text-red-300">Revoke</button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</details>
