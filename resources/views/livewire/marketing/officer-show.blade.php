<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="flex flex-col gap-1">
            <a href="{{ route('marketing.index') }}" class="text-sm text-slate-400 hover:text-white">&larr; Marketing</a>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $officer->name }}</h1>
            <p class="text-sm text-slate-400">
                <a href="mailto:{{ $officer->email }}" class="hover:text-white">{{ $officer->email }}</a>
                @if ($profile->phone) · {{ $profile->phone }} @endif
                · Code <span class="font-mono text-slate-200">{{ $profile->referral_code }}</span>
                · <a href="{{ route('users.show', $officer) }}" class="text-sky-400 hover:text-sky-300">User page</a>
                @unless ($profile->isActive())
                    · <span class="rounded bg-slate-800 px-1.5 py-0.5 text-xs text-slate-300">Inactive</span>
                @endunless
            </p>
        </div>

        <button type="button" wire:click="toggleActive"
            wire:confirm="{{ $profile->isActive() ? 'Make this officer inactive? Their code will stop working and new payments will earn them nothing.' : 'Make this officer active again?' }}"
            class="rounded-md border border-slate-700 px-3 py-1.5 text-sm text-slate-300 hover:text-white">
            {{ $profile->isActive() ? 'Make inactive' : 'Make active' }}
        </button>
    </div>

    @if ($status)
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ $status }}</p>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        @foreach (['On hold' => $totals['onHold'], 'Payable' => $totals['payable'], 'Paid' => $totals['paid']] as $label => $amount)
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <div class="text-sm text-slate-400">{{ $label }}</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($amount) }} <span class="text-sm font-normal text-slate-400">{{ $currency }}</span></div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <form wire:submit="pay" wire:confirm="Record a payment of {{ number_format($totals['payable']) }} {{ $currency }} to {{ $officer->name }}?" class="flex flex-col gap-3 rounded-xl border border-slate-800 bg-slate-900 p-6">
            <h2 class="font-semibold">Pay this officer</h2>
            <p class="text-sm text-slate-400">Send the money first, then record it here. This covers everything payable now: <span class="font-semibold text-slate-200">{{ number_format($totals['payable']) }} {{ $currency }}</span>.</p>
            <div class="rounded-md border border-slate-800 bg-slate-950 p-3 text-sm">
                <div class="text-xs text-slate-400">Their payment details</div>
                <div class="mt-1 whitespace-pre-line">{{ $profile->payout_details ?? 'Not given yet' }}</div>
            </div>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-slate-300">Paid through <span class="text-slate-500">(e.g. Meezan Bank, JazzCash)</span></span>
                <input wire:model="paymentMethod" type="text" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                @error('paymentMethod') <span class="text-red-400">{{ $message }}</span> @enderror
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-slate-300">Transaction reference <span class="text-slate-500">(optional)</span></span>
                <input wire:model="paymentReference" type="text" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                @error('paymentReference') <span class="text-red-400">{{ $message }}</span> @enderror
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-slate-300">Notes <span class="text-slate-500">(optional)</span></span>
                <textarea wire:model="paymentNotes" rows="2" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2"></textarea>
                @error('paymentNotes') <span class="text-red-400">{{ $message }}</span> @enderror
            </label>

            <button type="submit" @disabled($totals['payable'] <= 0) class="self-start rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-50">Record payment</button>
        </form>

        <form wire:submit="addBonus" class="flex flex-col gap-3 rounded-xl border border-slate-800 bg-slate-900 p-6">
            <h2 class="font-semibold">Add a bonus</h2>
            <p class="text-sm text-slate-400">The officer sees the reason on their page and statement.</p>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-slate-300">Amount ({{ $currency }})</span>
                <input wire:model="bonusAmount" type="number" min="1" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                @error('bonusAmount') <span class="text-red-400">{{ $message }}</span> @enderror
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-slate-300">Reason</span>
                <input wire:model="bonusReason" type="text" placeholder="e.g. 10 new paying clients in November" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                @error('bonusReason') <span class="text-red-400">{{ $message }}</span> @enderror
            </label>

            <button type="submit" class="self-start rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">Add bonus</button>
        </form>

        <form wire:submit="saveProfile" class="flex flex-col gap-3 rounded-xl border border-slate-800 bg-slate-900 p-6">
            <h2 class="font-semibold">Terms for this officer</h2>
            <p class="text-sm text-slate-400">Custom rates replace the standard rates for physicians and technicians. Leave them empty to use the standard rates. Patients always use the standard rate.</p>

            <div class="grid grid-cols-2 gap-3">
                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-slate-300">First payment (%)</span>
                    <input wire:model="firstPaymentRate" type="number" step="0.01" min="0" max="100" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                    @error('firstPaymentRate') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-slate-300">Renewals (%)</span>
                    <input wire:model="renewalRate" type="number" step="0.01" min="0" max="100" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                    @error('renewalRate') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>
            </div>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-slate-300">Private notes <span class="text-slate-500">(administrators only)</span></span>
                <textarea wire:model="notes" rows="3" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2"></textarea>
                @error('notes') <span class="text-red-400">{{ $message }}</span> @enderror
            </label>

            <button type="submit" class="self-start rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">Save</button>
        </form>
    </div>

    <section class="flex flex-col gap-3">
        <h2 class="font-semibold">Earnings</h2>
        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900">
            <table class="w-full text-sm">
                <thead class="text-left text-slate-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="px-4 py-3 font-medium">Client or reason</th>
                        <th class="px-4 py-3 text-right font-medium">Payment</th>
                        <th class="px-4 py-3 text-right font-medium">Rate</th>
                        <th class="px-4 py-3 text-right font-medium">Amount</th>
                        <th class="px-4 py-3 font-medium">Stage</th>
                        <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($earnings as $line)
                        <tr wire:key="line-{{ $line->id }}">
                            <td class="px-4 py-3 text-slate-300">{{ $line->created_at->toFormattedDateString() }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $line->type->label() }}</td>
                            <td class="px-4 py-3">
                                @if ($line->client_id)
                                    <a href="{{ route('users.show', $line->client_id) }}" class="text-sky-400 hover:text-sky-300">{{ $line->client_name }}</a>
                                @else
                                    {{ $line->client_name ?? $line->note }}
                                @endif
                                @if ($line->reversal_reason)
                                    <div class="text-xs text-slate-400">Reversed: {{ $line->reversal_reason }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-300">{{ $line->base_amount ? number_format($line->base_amount) : '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-300">{{ $line->rate_percent !== null ? $line->rate_percent.'%' : '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($line->amount) }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $line->stageLabel() }}</td>
                            <td class="px-4 py-3 text-right">
                                @if ($line->isReversible() && $reversingCommissionId !== $line->id)
                                    <button type="button" wire:click="startReversing({{ $line->id }})" class="text-red-400 hover:text-red-300">Reverse</button>
                                @endif
                            </td>
                        </tr>
                        @if ($reversingCommissionId === $line->id)
                            <tr wire:key="line-{{ $line->id }}-reversal">
                                <td colspan="8" class="bg-slate-950/60 px-4 py-3">
                                    <form wire:submit="reverse" class="flex flex-col gap-2">
                                        <p class="text-slate-300">
                                            @if ($line->status === App\Enums\CommissionStatus::Paid)
                                                This was already paid, so {{ number_format($line->amount) }} {{ $currency }} will come off this officer's next payment.
                                            @else
                                                This will cancel {{ number_format($line->amount) }} {{ $currency }} before it is paid.
                                            @endif
                                        </p>
                                        <input wire:model="reversalReason" type="text" placeholder="Reason, shown to the officer" aria-label="Reason for the reversal" class="max-w-xl rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                                        @error('reversalReason') <span class="text-red-400">{{ $message }}</span> @enderror
                                        <div class="flex items-center gap-3">
                                            <button type="submit" class="rounded-md bg-red-700 px-3 py-1.5 font-medium text-white hover:bg-red-600">Reverse commission</button>
                                            <button type="button" wire:click="cancelReversing" class="text-slate-400 hover:text-white">Cancel</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="8" class="px-4 py-6 text-center text-slate-400">Nothing earned yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $earnings->links() }}
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="flex flex-col gap-3">
            <h2 class="font-semibold">Clients</h2>
            <div class="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900">
                <table class="w-full text-sm">
                    <thead class="text-left text-slate-400">
                        <tr>
                            <th class="px-4 py-3 font-medium">Client</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">Signed up</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($clients as $client)
                            <tr wire:key="client-{{ $client->id }}">
                                <td class="px-4 py-3"><a href="{{ route('users.show', $client) }}" class="text-sky-400 hover:text-sky-300">{{ $client->name }}</a></td>
                                <td class="px-4 py-3 text-slate-300">{{ $client->role->label() }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $client->created_at->toFormattedDateString() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-6 text-center text-slate-400">No clients yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $clients->links() }}
        </section>

        <section class="flex flex-col gap-3">
            <h2 class="font-semibold">Payments to this officer</h2>
            <div class="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900">
                <table class="w-full text-sm">
                    <thead class="text-left text-slate-400">
                        <tr>
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">Paid through</th>
                            <th class="px-4 py-3 text-right font-medium">Amount</th>
                            <th class="px-4 py-3 font-medium">Statement</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($payouts as $payout)
                            <tr wire:key="payout-{{ $payout->id }}">
                                <td class="px-4 py-3 text-slate-300">{{ $payout->paid_at->toFormattedDateString() }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ $payout->payment_method }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ number_format($payout->amount) }}</td>
                                <td class="px-4 py-3"><a href="{{ route('marketing.statement', $payout) }}" target="_blank" class="text-sky-400 hover:text-sky-300">{{ $payout->statementNumber() }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">No payments yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
