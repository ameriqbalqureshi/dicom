<div class="flex flex-col gap-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Payments</h1>
        <p class="mt-1 text-sm text-slate-400">Check each payment against your account before confirming it. Confirming starts the package straight away.</p>
    </div>

    @if ($status)
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ $status }}</p>
    @endif

    <section class="flex flex-col gap-2">
        <h2 class="text-lg font-semibold">Awaiting confirmation <span class="text-slate-400">({{ $pending->count() }})</span></h2>

        @if ($pending->isEmpty())
            <p class="text-sm text-slate-400">No payments to review.</p>
        @else
            <ul class="divide-y divide-slate-800 rounded-xl border border-slate-800">
                @foreach ($pending as $subscription)
                    <li wire:key="pending-{{ $subscription->id }}" class="flex flex-col gap-3 px-4 py-3 text-sm">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="font-medium">{{ $subscription->user->name }} <span class="font-normal text-slate-400">· {{ $subscription->user->email }} · {{ $subscription->user->role->label() }}</span></div>
                                <div class="text-slate-300">
                                    {{ $subscription->package->name }} · <span class="font-medium text-white">{{ number_format($subscription->amount) }} {{ $currency }}</span>
                                    by {{ $paymentMethods[$subscription->payment_method] ?? $subscription->payment_method }}
                                </div>
                                <div class="text-slate-300">Reference: <span class="font-mono text-white">{{ $subscription->payment_reference }}</span></div>
                                @if ($subscription->notes)
                                    <div class="mt-1 whitespace-pre-line text-xs text-slate-400">{{ $subscription->notes }}</div>
                                @endif
                                <div class="text-xs text-slate-500">Submitted {{ $subscription->created_at->toDayDateTimeString() }}</div>
                            </div>

                            @if ($rejectingId !== $subscription->id)
                                <div class="flex items-center gap-3">
                                    <button type="button" wire:click="approve({{ $subscription->id }})"
                                        wire:confirm="Confirm you received {{ number_format($subscription->amount) }} {{ $currency }} with reference {{ $subscription->payment_reference }}?"
                                        class="rounded-md bg-emerald-700 px-3 py-1.5 text-white hover:bg-emerald-600">Confirm payment</button>
                                    <button type="button" wire:click="startRejecting({{ $subscription->id }})" class="text-red-400 hover:text-red-300">Reject</button>
                                </div>
                            @endif
                        </div>

                        @if ($rejectingId === $subscription->id)
                            <form wire:submit="reject" class="flex flex-wrap items-start gap-3">
                                <div class="flex min-w-64 flex-1 flex-col gap-1">
                                    <input wire:model="rejectionReason" type="text" placeholder="Reason, shown to the user (e.g. no payment found with this reference)"
                                        aria-label="Reason for rejecting" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-1.5">
                                    @error('rejectionReason') <span class="text-red-400">{{ $message }}</span> @enderror
                                </div>
                                <button type="submit" class="rounded-md border border-red-700 px-3 py-1.5 text-red-300 hover:bg-red-950">Reject payment</button>
                                <button type="button" wire:click="cancelRejecting" class="py-1.5 text-slate-400 hover:text-white">Cancel</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($reviewed->isNotEmpty())
        <section class="flex flex-col gap-2">
            <h2 class="text-lg font-semibold">Recently reviewed</h2>
            <ul class="divide-y divide-slate-800 rounded-xl border border-slate-800 text-sm">
                @foreach ($reviewed as $subscription)
                    <li wire:key="reviewed-{{ $subscription->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-2">
                        <div>
                            {{ $subscription->user->name }} · {{ $subscription->package->name }} · {{ number_format($subscription->amount) }} {{ $currency }}
                            <span class="font-mono text-xs text-slate-400">{{ $subscription->payment_reference }}</span>
                        </div>
                        <div class="text-xs text-slate-400">
                            {{ $subscription->status === App\Enums\SubscriptionStatus::Rejected ? 'Rejected' : 'Confirmed' }}
                            by {{ $subscription->reviewer?->name ?? 'a deleted user' }} · {{ $subscription->reviewed_at->toFormattedDateString() }}
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
