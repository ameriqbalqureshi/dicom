<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Payments</h1>
            <p class="mt-1 text-sm text-slate-400">Check each payment against your account before confirming it. Confirming starts the package straight away and emails the buyer a receipt.</p>
        </div>
        <div class="flex items-center gap-4 text-sm">
            <a href="{{ route('payment-accounts.index') }}" class="text-slate-300 hover:text-white">Payment accounts</a>
            @unless ($recording)
                <button type="button" wire:click="startRecording" class="rounded-md bg-sky-600 px-4 py-2 font-medium text-white hover:bg-sky-500">Record payment</button>
            @endunless
        </div>
    </div>

    @if ($status)
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ $status }}</p>
    @endif

    @if ($recording)
        <form wire:submit="recordPayment" wire:confirm="Record this payment and start the package now?" class="flex max-w-3xl flex-col gap-4 rounded-xl border border-sky-800 bg-slate-900 p-6 text-sm">
            <div>
                <h2 class="font-semibold">Record a payment</h2>
                <p class="mt-1 text-slate-400">For a payment you have already checked, such as a receipt sent by email. The package starts as soon as you save.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="flex flex-col gap-1">
                    <span class="text-slate-300">User ID or email</span>
                    <input wire:model.live.blur="recordIdentifier" type="text" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                    @if ($recordUser)
                        <span class="text-emerald-300">{{ $recordUser->name }} · {{ $recordUser->email }} · {{ $recordUser->role->label() }}</span>
                    @endif
                    @error('recordIdentifier') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1">
                    <span class="text-slate-300">Package</span>
                    <select wire:model.live="recordPackageId" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                        <option value="">Choose…</option>
                        @foreach ($recordPackages as $package)
                            <option value="{{ $package->id }}">{{ $package->name }}, {{ number_format($package->price) }} {{ $currency }}</option>
                        @endforeach
                    </select>
                    @error('recordPackageId') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1">
                    <span class="text-slate-300">Received in</span>
                    <select wire:model="recordAccountId" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                        <option value="">Choose…</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                    @error('recordAccountId') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1">
                    <span class="text-slate-300">Amount received ({{ $currency }})</span>
                    <input wire:model="recordAmount" type="number" min="1" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                    @error('recordAmount') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1">
                    <span class="text-slate-300">Transaction reference <span class="text-slate-500">(optional)</span></span>
                    <input wire:model="recordReference" type="text" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                    @error('recordReference') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1">
                    <span class="text-slate-300">Receipt <span class="text-slate-500">(optional; photo or PDF, up to 5 MB)</span></span>
                    <input wire:model="recordReceipt" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf"
                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 file:mr-3 file:rounded file:border-0 file:bg-slate-800 file:px-3 file:py-1 file:text-slate-200">
                    <span wire:loading wire:target="recordReceipt" class="text-slate-400">Uploading…</span>
                    @error('recordReceipt') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 sm:col-span-2">
                    <span class="text-slate-300">Note <span class="text-slate-500">(optional)</span></span>
                    <textarea wire:model="recordNotes" rows="2" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2"></textarea>
                    @error('recordNotes') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" wire:loading.attr="disabled" wire:target="recordReceipt,recordPayment"
                    class="rounded-md bg-emerald-700 px-4 py-2 font-medium text-white hover:bg-emerald-600 disabled:opacity-60">Record and start package</button>
                <button type="button" wire:click="cancelRecording" class="text-slate-400 hover:text-white">Cancel</button>
            </div>
        </form>
    @endif

    @if ($submissions->isNotEmpty())
        <section class="flex flex-col gap-2">
            <div>
                <h2 class="text-lg font-semibold">Receipts from the website <span class="text-slate-400">({{ $submissions->count() }})</span></h2>
                <p class="text-sm text-slate-400">Anyone can send these, so check the account and package before linking. Linking moves the receipt to Awaiting confirmation.</p>
            </div>

            <ul class="divide-y divide-slate-800 rounded-xl border border-slate-800">
                @foreach ($submissions as $submission)
                    @php($suggestedUser = $suggestedUsers[$submission->id])
                    <li wire:key="submission-{{ $submission->id }}" class="flex flex-col gap-3 px-4 py-3 text-sm">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="font-medium">{{ $submission->name }} <span class="font-normal text-slate-400">· gave <span class="font-mono">{{ $submission->account_identifier }}</span>@if ($submission->phone) · {{ $submission->phone }}@endif</span></div>
                                @if ($suggestedUser)
                                    <div class="text-emerald-300">Matches {{ $suggestedUser->name }} · {{ $suggestedUser->email }} · {{ $suggestedUser->role->label() }}</div>
                                @else
                                    <div class="text-amber-300">No account has this user ID or email.</div>
                                @endif
                                <div class="text-slate-300">
                                    {{ $submission->package?->name ?? 'Package no longer available' }}
                                    @if ($submission->package)
                                        · <span class="font-medium text-white">{{ number_format($submission->package->price) }} {{ $currency }}</span>
                                    @endif
                                    into {{ $submission->payment_method }}
                                </div>
                                @if ($submission->payment_reference)
                                    <div class="text-slate-300">Reference: <span class="font-mono text-white">{{ $submission->payment_reference }}</span></div>
                                @endif
                                @if ($submission->notes)
                                    <div class="mt-1 whitespace-pre-line text-xs text-slate-400">{{ $submission->notes }}</div>
                                @endif
                                <div class="text-xs text-slate-500">Received {{ $submission->created_at->toDayDateTimeString() }}</div>
                            </div>

                            <div class="flex items-center gap-3">
                                <a href="{{ route('payment-submissions.receipt', $submission) }}" target="_blank" class="text-sky-400 hover:text-sky-300">View receipt</a>
                                @if ($linkingId !== $submission->id)
                                    <button type="button" wire:click="startLinking({{ $submission->id }})" class="rounded-md bg-sky-700 px-3 py-1.5 text-white hover:bg-sky-600">Link to account</button>
                                @endif
                                <button type="button" wire:click="discardSubmission({{ $submission->id }})" wire:confirm="Delete this receipt? The sender is not told." class="text-red-400 hover:text-red-300">Delete</button>
                            </div>
                        </div>

                        @if ($linkingId === $submission->id)
                            <form wire:submit="linkSubmission" class="flex flex-wrap items-start gap-3">
                                <div class="flex min-w-64 flex-1 flex-col gap-1">
                                    <input wire:model.live.blur="linkIdentifier" type="text" placeholder="User ID or email" aria-label="User ID or email"
                                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-1.5">
                                    @if ($linkUser)
                                        <span class="text-emerald-300">{{ $linkUser->name }} · {{ $linkUser->email }} · {{ $linkUser->role->label() }}</span>
                                    @endif
                                    @error('linkIdentifier') <span class="text-red-400">{{ $message }}</span> @enderror
                                </div>
                                <div class="flex min-w-64 flex-1 flex-col gap-1">
                                    <select wire:model="linkPackageId" aria-label="Package" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-1.5">
                                        <option value="">Choose a package…</option>
                                        @foreach ($linkPackages as $package)
                                            <option value="{{ $package->id }}">{{ $package->name }}, {{ number_format($package->price) }} {{ $currency }}</option>
                                        @endforeach
                                    </select>
                                    @error('linkPackageId') <span class="text-red-400">{{ $message }}</span> @enderror
                                </div>
                                <button type="submit" class="rounded-md border border-sky-700 px-3 py-1.5 text-sky-300 hover:bg-sky-950">Link</button>
                                <button type="button" wire:click="cancelLinking" class="py-1.5 text-slate-400 hover:text-white">Cancel</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
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
                                <div class="font-medium">{{ $subscription->user->name }} <span class="font-normal text-slate-400">· ID {{ $subscription->user->id }} · {{ $subscription->user->email }} · {{ $subscription->user->role->label() }}</span></div>
                                <div class="text-slate-300">
                                    {{ $subscription->package->name }} · <span class="font-medium text-white">{{ number_format($subscription->amount) }} {{ $currency }}</span>
                                    into {{ $subscription->payment_method }}
                                </div>
                                @if ($subscription->payment_reference)
                                    <div class="text-slate-300">Reference: <span class="font-mono text-white">{{ $subscription->payment_reference }}</span></div>
                                @endif
                                @if ($subscription->notes)
                                    <div class="mt-1 whitespace-pre-line text-xs text-slate-400">{{ $subscription->notes }}</div>
                                @endif
                                <div class="text-xs text-slate-500">Submitted {{ $subscription->created_at->toDayDateTimeString() }}</div>
                            </div>

                            @if ($rejectingId !== $subscription->id)
                                <div class="flex items-center gap-3">
                                    @if ($subscription->receipt_path)
                                        <a href="{{ route('payments.receipt-file', $subscription) }}" target="_blank" class="text-sky-400 hover:text-sky-300">View receipt</a>
                                    @else
                                        <span class="text-slate-500">No receipt</span>
                                    @endif
                                    <button type="button" wire:click="approve({{ $subscription->id }})"
                                        wire:confirm="Confirm you received {{ number_format($subscription->amount) }} {{ $currency }}{{ $subscription->payment_reference ? ' with reference '.$subscription->payment_reference : '' }}?"
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
                            @if ($subscription->receipt_path)
                                · <a href="{{ route('payments.receipt-file', $subscription) }}" target="_blank" class="text-xs text-sky-400 hover:text-sky-300">Buyer's receipt</a>
                            @endif
                            @if ($subscription->isPaymentConfirmed())
                                · <a href="{{ route('package.receipt', $subscription) }}" target="_blank" class="text-xs text-sky-400 hover:text-sky-300">{{ $subscription->receiptNumber() }}</a>
                            @endif
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
