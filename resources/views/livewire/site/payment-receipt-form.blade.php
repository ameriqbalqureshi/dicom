<div>
    @if ($sent)
        <div class="flex flex-col items-start gap-4 rounded-2xl border border-emerald-800 bg-emerald-950/40 p-8">
            <h2 class="text-xl font-semibold text-white">Thank you, we have your receipt.</h2>
            <p class="text-sm text-emerald-200">We check every receipt against our accounts, usually the same working day, and email the account holder when the package starts.</p>
            <button type="button" wire:click="$set('sent', false)" class="text-sm text-emerald-300 hover:text-white">Send another receipt</button>
        </div>
    @else
        <form wire:submit="submit" class="flex flex-col gap-5 rounded-2xl border border-slate-800 bg-slate-900/60 p-6 sm:p-8">
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <label for="receipt-name" class="text-sm text-slate-300">Your name</label>
                    <input wire:model="name" id="receipt-name" type="text" autocomplete="name" required
                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                    @error('name') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="receipt-account" class="text-sm text-slate-300">Your account email or user ID</label>
                    <input wire:model="accountIdentifier" id="receipt-account" type="text" required
                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                    @error('accountIdentifier') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <label for="receipt-package" class="text-sm text-slate-300">Package</label>
                    <select wire:model="packageId" id="receipt-package" required
                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                        <option value="">Choose…</option>
                        @foreach ($packages as $package)
                            <option value="{{ $package->id }}">{{ $package->name }} ({{ $package->role->label() }}), {{ number_format($package->price) }} {{ $currency }}</option>
                        @endforeach
                    </select>
                    @error('packageId') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="receipt-paid-into" class="text-sm text-slate-300">Paid into</label>
                    <select wire:model="paymentAccountId" id="receipt-paid-into" required
                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                        <option value="">Choose…</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                        @endforeach
                    </select>
                    @error('paymentAccountId') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <label for="receipt-reference" class="text-sm text-slate-300">Transaction reference <span class="text-slate-500">(optional)</span></label>
                    <input wire:model="paymentReference" id="receipt-reference" type="text"
                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                    @error('paymentReference') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-1.5">
                    <label for="receipt-phone" class="text-sm text-slate-300">Phone <span class="text-slate-500">(optional, in case we need to reach you)</span></label>
                    <input wire:model="phone" id="receipt-phone" type="tel" autocomplete="tel"
                        class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                    @error('phone') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="receipt-file" class="text-sm text-slate-300">Receipt <span class="text-slate-500">(photo or PDF, up to 5 MB)</span></label>
                <input wire:model="receipt" id="receipt-file" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" required
                    class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-slate-800 file:px-3 file:py-1 file:text-slate-200">
                <p wire:loading wire:target="receipt" class="text-sm text-slate-400">Uploading…</p>
                @error('receipt') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="receipt-notes" class="text-sm text-slate-300">Note <span class="text-slate-500">(optional)</span></label>
                <textarea wire:model="notes" id="receipt-notes" rows="2"
                    class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none"></textarea>
                @error('notes') <p class="text-sm text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="absolute -left-[9999px]" aria-hidden="true">
                <label for="receipt-website">Leave this field empty</label>
                <input wire:model="website" id="receipt-website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="receipt,submit"
                class="self-start rounded-md bg-sky-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-sky-500 disabled:opacity-60">
                <span wire:loading.remove wire:target="submit">Send receipt</span>
                <span wire:loading wire:target="submit">Sending…</span>
            </button>
        </form>
    @endif
</div>
