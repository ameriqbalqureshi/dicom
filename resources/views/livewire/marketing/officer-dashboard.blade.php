<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Marketing</h1>
        <p class="mt-1 text-sm text-slate-400">Your referral link, clients and earnings.</p>
    </div>

    @if ($status)
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ $status }}</p>
    @endif

    @unless ($profile->isActive())
        <p class="rounded-md border border-amber-800 bg-amber-950/60 px-4 py-2 text-sm text-amber-200">Your marketing account is not active. Your referral code no longer works and new payments do not earn commission. What you already earned will still be paid.</p>
    @endunless

    <div class="grid gap-4 sm:grid-cols-3">
        @foreach (['On hold' => $totals['onHold'], 'Payable' => $totals['payable'], 'Paid to you' => $totals['paid']] as $label => $amount)
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <div class="text-sm text-slate-400">{{ $label }}</div>
                <div class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($amount) }} <span class="text-sm font-normal text-slate-400">{{ $currency }}</span></div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
            <h2 class="font-semibold">Your referral link</h2>

            @if ($profile->isActive())
                <div x-data="{ copied: false }" class="flex flex-col gap-2 text-sm">
                    <div class="flex gap-2">
                        <input type="text" readonly value="{{ $profile->referralLink() }}" aria-label="Your referral link" class="min-w-0 flex-1 rounded-md border border-slate-700 bg-slate-950 px-3 py-2 font-mono text-xs">
                        <button type="button" x-on:click="navigator.clipboard.writeText(@js($profile->referralLink())); copied = true; setTimeout(() => copied = false, 2000)"
                            class="rounded-md bg-sky-600 px-3 py-2 font-medium text-white hover:bg-sky-500">
                            <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                        </button>
                    </div>
                    <p class="text-slate-400">Or ask them to enter your code when signing up: <span class="font-mono text-slate-200">{{ $profile->referral_code }}</span></p>
                </div>
            @else
                <p class="text-sm text-slate-400">Your referral code no longer works.</p>
            @endif

            <h3 class="mt-2 text-sm font-medium text-slate-300">What you earn</h3>
            <table class="text-sm">
                <thead>
                    <tr class="text-left text-slate-400">
                        <th class="py-1 pr-4 font-medium">Client</th>
                        <th class="py-1 pr-4 font-medium">First payment</th>
                        <th class="py-1 font-medium">Renewals</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rates as $rate)
                        <tr wire:key="rate-{{ $rate['label'] }}">
                            <td class="py-1 pr-4">{{ $rate['label'] }}</td>
                            <td class="py-1 pr-4 tabular-nums">{{ $rate['first'] }}%</td>
                            <td class="py-1 tabular-nums">{{ $rate['renewal'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="text-xs text-slate-400">Renewals earn for {{ $rules['renewal_months'] }} months after a client's first payment. Commission can be paid {{ $rules['hold_days'] }} days after we confirm the payment.</p>
        </section>

        <form wire:submit="savePayoutDetails" class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
            <h2 class="font-semibold">How we pay you</h2>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-slate-300">Phone</span>
                <input wire:model="phone" type="tel" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                @error('phone') <span class="text-red-400">{{ $message }}</span> @enderror
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-slate-300">Payment details <span class="text-slate-500">(bank account or IBAN, or JazzCash or Easypaisa number, and the account title)</span></span>
                <textarea wire:model="payoutDetails" rows="4" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2"></textarea>
                @error('payoutDetails') <span class="text-red-400">{{ $message }}</span> @enderror
            </label>

            <button type="submit" class="self-start rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">Save</button>
        </form>
    </div>

    <section class="flex flex-col gap-3">
        <h2 class="font-semibold">Your clients</h2>
        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900">
            <table class="w-full text-sm">
                <thead class="text-left text-slate-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Client</th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="px-4 py-3 font-medium">Signed up</th>
                        <th class="px-4 py-3 font-medium">Current package</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($clients as $client)
                        <tr wire:key="client-{{ $client->id }}">
                            <td class="px-4 py-3">{{ $client->nameForMarketing() }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $client->role->label() }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $client->created_at->toFormattedDateString() }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $client->subscriptions->first()?->package->name ?? 'None' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Nobody has signed up with your link yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $clients->links() }}
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-semibold">Your earnings</h2>
        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900">
            <table class="w-full text-sm">
                <thead class="text-left text-slate-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="px-4 py-3 font-medium">Client or reason</th>
                        <th class="px-4 py-3 text-right font-medium">Payment</th>
                        <th class="px-4 py-3 text-right font-medium">Rate</th>
                        <th class="px-4 py-3 text-right font-medium">You earn</th>
                        <th class="px-4 py-3 font-medium">Stage</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($earnings as $line)
                        <tr wire:key="line-{{ $line->id }}">
                            <td class="px-4 py-3 text-slate-300">{{ $line->created_at->toFormattedDateString() }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $line->type->label() }}</td>
                            <td class="px-4 py-3">{{ $line->client_name ?? $line->note }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-300">{{ $line->base_amount ? number_format($line->base_amount) : '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-300">{{ $line->rate_percent !== null ? $line->rate_percent.'%' : '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($line->amount) }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $line->stageLabel() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">You earn commission when your clients' payments are confirmed.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $earnings->links() }}
    </section>

    <section class="flex flex-col gap-3">
        <h2 class="font-semibold">Payments to you</h2>
        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900">
            <table class="w-full text-sm">
                <thead class="text-left text-slate-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium">Paid through</th>
                        <th class="px-4 py-3 font-medium">Reference</th>
                        <th class="px-4 py-3 text-right font-medium">Amount</th>
                        <th class="px-4 py-3 font-medium">Statement</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($payouts as $payout)
                        <tr wire:key="payout-{{ $payout->id }}">
                            <td class="px-4 py-3 text-slate-300">{{ $payout->paid_at->toFormattedDateString() }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $payout->payment_method }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $payout->payment_reference ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($payout->amount) }} {{ $currency }}</td>
                            <td class="px-4 py-3"><a href="{{ route('marketing.statement', $payout) }}" target="_blank" class="text-sky-400 hover:text-sky-300">{{ $payout->statementNumber() }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">No payments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
