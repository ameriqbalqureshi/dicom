<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Senders</h1>
        <p class="mt-1 text-sm text-slate-400">
            People who share studies with you. A new sender's first study waits on <a href="{{ route('shares.pending') }}" class="text-sky-400 hover:text-sky-300">Requests</a>;
            once you accept it, their shares reach you straight away. Senders are not told when you suspend or block them: their shares are simply not delivered.
        </p>
    </div>

    @if ($status)
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ $status }}</p>
    @endif

    <div class="overflow-x-auto rounded-xl border border-slate-800">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-900 text-xs uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-3">Sender</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Studies shared with you</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @forelse ($rows as $row)
                    @php($sender = $row['sender'])
                    <tr wire:key="sender-{{ $sender->id }}">
                        <td class="px-4 py-3">
                            <div>{{ $sender->name }}</div>
                            <div class="text-xs text-slate-400">{{ $sender->email }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-300">{{ ucfirst($sender->role->value) }}</td>
                        <td class="px-4 py-3 text-slate-300">
                            {{ $row['studies'] }}
                            @if ($row['pending'] > 0)
                                <span class="text-xs text-amber-300">({{ $row['pending'] }} waiting)</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @switch($row['status'])
                                @case(App\Enums\SenderStatus::Allowed)
                                    <span class="rounded bg-emerald-900 px-1.5 py-0.5 text-xs text-emerald-200">Allowed</span>
                                    @break
                                @case(App\Enums\SenderStatus::Suspended)
                                    <span class="rounded bg-amber-900 px-1.5 py-0.5 text-xs text-amber-200">Suspended</span>
                                    @break
                                @case(App\Enums\SenderStatus::Blocked)
                                    <span class="rounded bg-red-900 px-1.5 py-0.5 text-xs text-red-200">Blocked</span>
                                    @break
                                @default
                                    <span class="rounded bg-slate-800 px-1.5 py-0.5 text-xs text-slate-300">New · needs acceptance</span>
                            @endswitch
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-3">
                                @if ($row['status'] === App\Enums\SenderStatus::Allowed)
                                    <button type="button" wire:click="suspend({{ $sender->id }})"
                                        wire:confirm="Suspend {{ $sender->name }}? New shares from them will not reach you. Studies they already shared stay in your list."
                                        class="text-amber-300 hover:text-amber-200">Suspend</button>
                                @elseif ($row['status'] === App\Enums\SenderStatus::Suspended)
                                    <button type="button" wire:click="resume({{ $sender->id }})" class="text-sky-400 hover:text-sky-300">Resume</button>
                                @endif

                                @if ($row['status'] === App\Enums\SenderStatus::Blocked)
                                    <button type="button" wire:click="unblock({{ $sender->id }})"
                                        wire:confirm="Unblock {{ $sender->name }}? Their next share will wait for you to accept it."
                                        class="text-sky-400 hover:text-sky-300">Unblock</button>
                                @else
                                    <button type="button" wire:click="block({{ $sender->id }})"
                                        wire:confirm="Block {{ $sender->name }}? Their shares will no longer reach you, and every study they shared with you is removed from your list."
                                        class="text-red-400 hover:text-red-300">Block</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-slate-400">Nobody has shared a study with you yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
