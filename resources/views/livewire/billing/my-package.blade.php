<div class="flex max-w-5xl flex-col gap-6">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h1 class="text-2xl font-semibold tracking-tight">Package</h1>
        <span class="text-sm text-slate-400">Your user ID: <span class="select-all font-mono text-slate-200">{{ auth()->id() }}</span></span>
    </div>

    @if ($status)
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ $status }}</p>
    @endif

    <section class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
        @if ($current)
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-lg font-semibold">{{ $current->package->name }}</h2>
                <span class="text-sm text-slate-400">
                    @if ($current->ends_at)
                        Ends {{ $current->ends_at->toFormattedDateString() }} ({{ $current->ends_at->diffForHumans() }})
                    @else
                        Does not expire
                    @endif
                </span>
            </div>

            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($quotas as $quota)
                    @php($limit = $current->limit($quota))
                    @php($used = $current->used($quota))
                    <div wire:key="usage-{{ $quota->value }}" class="flex flex-col gap-1.5">
                        <dt class="text-sm text-slate-300">{{ $quota->label() }}</dt>
                        <dd class="text-sm">
                            @if ($limit === null)
                                {{ number_format($used) }} used · <span class="text-slate-400">unlimited</span>
                            @elseif ($limit === 0)
                                <span class="text-slate-500">Not included</span>
                            @else
                                {{ number_format($used) }} of {{ number_format($limit) }}
                                <div class="mt-1 h-1.5 overflow-hidden rounded bg-slate-800">
                                    <div class="h-full {{ $used >= $limit ? 'bg-red-500' : 'bg-sky-500' }}" style="width: {{ min(100, round($used / $limit * 100)) }}%"></div>
                                </div>
                            @endif
                        </dd>
                    </div>
                @endforeach

                <div class="flex flex-col gap-1.5">
                    <dt class="text-sm text-slate-300">Storage</dt>
                    <dd class="text-sm">
                        @if ($current->max_storage_mb === null)
                            {{ $storageUsedMb }} MB used · <span class="text-slate-400">unlimited</span>
                        @else
                            {{ $storageUsedMb }} of {{ number_format($current->max_storage_mb) }} MB
                            <div class="mt-1 h-1.5 overflow-hidden rounded bg-slate-800">
                                <div class="h-full {{ $storageUsedMb >= $current->max_storage_mb ? 'bg-red-500' : 'bg-sky-500' }}" style="width: {{ $current->max_storage_mb > 0 ? min(100, round($storageUsedMb / $current->max_storage_mb * 100)) : 100 }}%"></div>
                            </div>
                        @endif
                    </dd>
                </div>
            </dl>
        @else
            <p class="text-sm text-amber-200">You do not have an active package. You can still open your studies and those shared with you, but you need a package to upload, share or report.</p>
        @endif
    </section>

    @if ($pending)
        <section class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-sky-800 bg-sky-950/40 p-4 text-sm">
            <div>
                <div class="font-medium text-sky-200">{{ $pending->package->name }} · awaiting payment confirmation</div>
                <div class="text-xs text-slate-400">
                    {{ number_format($pending->amount) }} {{ $currency }} into {{ $pending->payment_method }}
                    @if ($pending->payment_reference)
                        · reference {{ $pending->payment_reference }}
                    @endif
                    @if ($pending->receipt_path)
                        · receipt attached
                    @endif
                    · submitted {{ $pending->created_at->diffForHumans() }}
                </div>
            </div>
            <button type="button" wire:click="cancelPending({{ $pending->id }})" wire:confirm="Cancel this purchase?" class="text-red-400 hover:text-red-300">Cancel</button>
        </section>
    @endif

    @if ($packages->isNotEmpty())
        <section class="flex flex-col gap-4">
            <h2 class="text-lg font-semibold">Packages</h2>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($packages as $package)
                    <div wire:key="package-{{ $package->id }}"
                        class="flex flex-col gap-3 rounded-xl border p-5 {{ $selectedPackageId === $package->id ? 'border-sky-500 bg-sky-950/30' : 'border-slate-800 bg-slate-900' }}">
                        <div>
                            <h3 class="font-semibold">{{ $package->name }}</h3>
                            <div class="mt-1 text-xl font-semibold">
                                {{ $package->isFree() ? 'Free' : number_format($package->price).' '.$currency }}
                                <span class="text-sm font-normal text-slate-400">{{ $package->duration_days ? "/ {$package->duration_days} days" : 'one-time' }}</span>
                            </div>
                            @if ($package->description)
                                <p class="mt-1 text-sm text-slate-400">{{ $package->description }}</p>
                            @endif
                        </div>

                        @include('billing.partials.limits', ['limits' => $package])

                        @unless ($pending || $selectedPackageId === $package->id)
                            <button type="button" wire:click="choose({{ $package->id }})"
                                class="mt-auto rounded-md border border-sky-600 px-3 py-1.5 text-sm text-sky-300 hover:bg-sky-950">Choose</button>
                        @endunless
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($selectedPackage)
        <form wire:submit="purchase" class="flex max-w-xl flex-col gap-4 rounded-xl border border-sky-800 bg-slate-900 p-6">
            <h2 class="font-semibold">Buy {{ $selectedPackage->name }}</h2>

            @error('purchase')
                <p class="rounded-md border border-red-800 bg-red-950 px-4 py-2 text-sm text-red-300">{{ $message }}</p>
            @enderror

            @unless ($selectedPackage->isFree())
                <p class="whitespace-pre-line rounded-md border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-slate-300">Amount: {{ number_format($selectedPackage->price) }} {{ $currency }}

{{ $paymentInstructions }}</p>

                @include('billing.partials.payment-accounts')

                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-slate-300">Paid into</span>
                    <select wire:model="paymentAccountId" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                        <option value="">Choose…</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                    @error('paymentAccountId') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-slate-300">Receipt <span class="text-slate-500">(photo or PDF, up to 5 MB)</span></span>
                    <input wire:model="receipt" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf"
                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 file:mr-3 file:rounded file:border-0 file:bg-slate-800 file:px-3 file:py-1 file:text-slate-200">
                    <span wire:loading wire:target="receipt" class="text-slate-400">Uploading…</span>
                    @error('receipt') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-slate-300">Transaction reference <span class="text-slate-500">(if you have no receipt to attach)</span></span>
                    <input wire:model="paymentReference" type="text" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 focus:border-sky-500 focus:outline-none">
                    @error('paymentReference') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                @if ($receiptEmail)
                    <p class="text-xs text-slate-400">
                        Prefer email? Skip this form and send the receipt to
                        <a href="mailto:{{ $receiptEmail }}" class="text-sky-400 hover:text-sky-300">{{ $receiptEmail }}</a>
                        with your user ID ({{ auth()->id() }}) and the package you paid for.
                    </p>
                @endif
            @endunless

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-slate-300">Note <span class="text-slate-500">(optional)</span></span>
                <textarea wire:model="notes" rows="2" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 focus:border-sky-500 focus:outline-none"></textarea>
                @error('notes') <span class="text-red-400">{{ $message }}</span> @enderror
            </label>

            <p class="text-xs text-slate-400">Your new package replaces your current one when it starts, and its limits start from zero.</p>

            <div class="flex items-center gap-3">
                <button type="submit" wire:loading.attr="disabled" wire:target="receipt,purchase" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-60">
                    {{ $selectedPackage->isFree() ? 'Start package' : 'Submit payment' }}
                </button>
                <button type="button" wire:click="cancelChoice" class="text-sm text-slate-400 hover:text-white">Cancel</button>
            </div>
        </form>
    @endif

    @if ($history->isNotEmpty())
        <section class="flex flex-col gap-2">
            <h2 class="text-lg font-semibold">History</h2>
            <ul class="divide-y divide-slate-800 rounded-xl border border-slate-800 text-sm">
                @foreach ($history as $subscription)
                    <li wire:key="history-{{ $subscription->id }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-2">
                        <div>
                            {{ $subscription->package->name }}
                            @if ($subscription->amount > 0)
                                <span class="text-slate-400">· {{ number_format($subscription->amount) }} {{ $currency }}</span>
                            @endif
                            @if ($subscription->rejection_reason)
                                <div class="text-xs text-red-300">{{ $subscription->rejection_reason }}</div>
                            @endif
                        </div>
                        <div class="text-xs text-slate-400">
                            {{ $subscription->statusLabel() }} · {{ $subscription->created_at->toFormattedDateString() }}
                            @if ($subscription->isPaymentConfirmed())
                                · <a href="{{ route('package.receipt', $subscription) }}" target="_blank" class="text-sky-400 hover:text-sky-300">Receipt</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
