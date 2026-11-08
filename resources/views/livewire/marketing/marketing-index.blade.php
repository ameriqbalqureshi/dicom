<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Marketing</h1>
            <p class="mt-1 text-sm text-slate-400">Applications, marketing officers and commission rules. <a href="{{ route('marketing.apply') }}" target="_blank" class="text-sky-400 hover:text-sky-300">Public application page</a></p>
        </div>
        <button type="button" wire:click="export" class="rounded-md border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:text-white">Download CSV</button>
    </div>

    @if ($status)
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ $status }}</p>
    @endif

    @error('applications')
        <p class="rounded-md border border-red-800 bg-red-950 px-4 py-2 text-sm text-red-300">{{ $message }}</p>
    @enderror

    @if ($applications->isNotEmpty())
        <section class="flex flex-col gap-3">
            <h2 class="font-semibold">Applications waiting for review</h2>
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($applications as $application)
                    <div wire:key="application-{{ $application->id }}" class="flex flex-col gap-3 rounded-xl border border-sky-800 bg-slate-900 p-5 text-sm">
                        <div>
                            <div class="font-semibold">{{ $application->name }}</div>
                            <div class="text-slate-400">
                                <a href="mailto:{{ $application->email }}" class="hover:text-white">{{ $application->email }}</a>
                                · {{ $application->phone }}
                                @if ($application->city) · {{ $application->city }} @endif
                            </div>
                            <div class="text-xs text-slate-500">Applied {{ $application->created_at->diffForHumans() }}</div>
                        </div>

                        @if ($application->experience)
                            <p class="whitespace-pre-line text-slate-300">{{ $application->experience }}</p>
                        @endif

                        @if ($rejectingApplicationId === $application->id)
                            <form wire:submit="reject" class="flex flex-col gap-2">
                                <label class="flex flex-col gap-1">
                                    <span class="text-slate-300">Reason <span class="text-slate-500">(optional, included in the email to them)</span></span>
                                    <textarea wire:model="rejectionReason" rows="2" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2"></textarea>
                                    @error('rejectionReason') <span class="text-red-400">{{ $message }}</span> @enderror
                                </label>
                                <div class="flex items-center gap-3">
                                    <button type="submit" class="rounded-md bg-red-700 px-3 py-1.5 font-medium text-white hover:bg-red-600">Reject application</button>
                                    <button type="button" wire:click="cancelRejecting" class="text-slate-400 hover:text-white">Cancel</button>
                                </div>
                            </form>
                        @else
                            <div class="flex items-center gap-4">
                                <button type="button" wire:click="approve({{ $application->id }})" wire:confirm="Approve {{ $application->name }} as a marketing officer? They will get an account and a referral code." class="rounded-md bg-sky-600 px-3 py-1.5 font-medium text-white hover:bg-sky-500">Approve</button>
                                <button type="button" wire:click="startRejecting({{ $application->id }})" class="text-red-400 hover:text-red-300">Reject</button>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="flex flex-col gap-3">
        <h2 class="font-semibold">Marketing officers</h2>
        <div class="overflow-x-auto rounded-xl border border-slate-800 bg-slate-900">
            <table class="w-full text-sm">
                <thead class="text-left text-slate-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Officer</th>
                        <th class="px-4 py-3 font-medium">Code</th>
                        <th class="px-4 py-3 text-right font-medium">Clients</th>
                        <th class="px-4 py-3 text-right font-medium">Paying</th>
                        <th class="px-4 py-3 text-right font-medium">On hold</th>
                        <th class="px-4 py-3 text-right font-medium">Payable</th>
                        <th class="px-4 py-3 text-right font-medium">Paid</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($officers as $officer)
                        <tr wire:key="officer-{{ $officer->id }}">
                            <td class="px-4 py-3">
                                <a href="{{ route('marketing.officers.show', $officer) }}" class="text-sky-400 hover:text-sky-300">{{ $officer->name }}</a>
                                @unless ($officer->marketingProfile?->isActive())
                                    <span class="ml-1 rounded bg-slate-800 px-1.5 py-0.5 text-xs text-slate-300">Inactive</span>
                                @endunless
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-300">{{ $officer->marketingProfile?->referral_code }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($officer->referred_clients_count) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format($officer->paying_clients_count) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((int) $officer->on_hold_total) }}</td>
                            <td @class(['px-4 py-3 text-right tabular-nums', 'font-semibold text-emerald-300' => (int) $officer->payable_total > 0])>{{ number_format((int) $officer->payable_total) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ number_format((int) $officer->paid_total) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">No marketing officers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-slate-400">Amounts are in {{ $currency }}.</p>
        {{ $officers->links() }}
    </section>

    <form wire:submit="saveRules" class="flex max-w-3xl flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
        <div>
            <h2 class="font-semibold">Commission rules</h2>
            <p class="mt-1 text-sm text-slate-400">Changes apply to payments confirmed from now on. Past commissions keep the rate they were earned at.</p>
        </div>

        <table class="text-sm">
            <thead>
                <tr class="text-left text-slate-400">
                    <th class="py-1 pr-4 font-medium">Client</th>
                    <th class="py-1 pr-4 font-medium">First payment (%)</th>
                    <th class="py-1 font-medium">Renewals (%)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($clientRoles as $clientRole)
                    <tr wire:key="rule-{{ $clientRole->value }}">
                        <td class="py-1 pr-4">{{ $clientRole->label() }}</td>
                        <td class="py-1 pr-4">
                            <input wire:model="rates.{{ $clientRole->value }}.first" type="number" step="0.01" min="0" max="100" aria-label="First payment rate for {{ $clientRole->label() }}" class="w-28 rounded-md border border-slate-700 bg-slate-950 px-3 py-1.5">
                            @error("rates.{$clientRole->value}.first") <div class="text-red-400">{{ $message }}</div> @enderror
                        </td>
                        <td class="py-1">
                            <input wire:model="rates.{{ $clientRole->value }}.renewal" type="number" step="0.01" min="0" max="100" aria-label="Renewal rate for {{ $clientRole->label() }}" class="w-28 rounded-md border border-slate-700 bg-slate-950 px-3 py-1.5">
                            @error("rates.{$clientRole->value}.renewal") <div class="text-red-400">{{ $message }}</div> @enderror
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="flex flex-col gap-1 text-sm">
                <span class="text-slate-300">Renewals earn for this many months after the first payment</span>
                <input wire:model="renewalMonths" type="number" min="0" max="120" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                @error('renewalMonths') <span class="text-red-400">{{ $message }}</span> @enderror
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-slate-300">Hold commission for this many days before it can be paid</span>
                <input wire:model="holdDays" type="number" min="0" max="365" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                @error('holdDays') <span class="text-red-400">{{ $message }}</span> @enderror
            </label>
        </div>

        <button type="submit" class="self-start rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">Save rules</button>
    </form>
</div>
