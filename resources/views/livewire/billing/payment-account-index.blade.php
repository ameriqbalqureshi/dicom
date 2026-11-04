<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Payment accounts</h1>
            <p class="mt-1 text-sm text-slate-400">The accounts buyers pay into, shown at checkout and on the public pages. <a href="{{ route('payments.index') }}" class="text-sky-400 hover:text-sky-300">Back to payments</a></p>
        </div>
        @unless ($editing)
            <button type="button" wire:click="create" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">New account</button>
        @endunless
    </div>

    @if ($status)
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ $status }}</p>
    @endif

    @if ($editing)
        <form wire:submit="save" class="flex max-w-3xl flex-col gap-4 rounded-xl border border-sky-800 bg-slate-900 p-6">
            <h2 class="font-semibold">{{ $accountId ? 'Edit account' : 'New account' }}</h2>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-slate-300">Name shown to buyers <span class="text-slate-500">(e.g. Meezan Bank, JazzCash)</span></span>
                    <input wire:model="name" type="text" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                    @error('name') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-slate-300">Type</span>
                    <select wire:model.live="type" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                        @foreach ($types as $typeOption)
                            <option value="{{ $typeOption->value }}">{{ $typeOption->label() }}</option>
                        @endforeach
                    </select>
                    @error('type') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-slate-300">Account title</span>
                    <input wire:model="accountTitle" type="text" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                    @error('accountTitle') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-slate-300">{{ App\Enums\PaymentAccountType::from($type)->numberLabel() }}</span>
                    <input wire:model="accountNumber" type="text" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                    @error('accountNumber') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                @if ($type === App\Enums\PaymentAccountType::Bank->value)
                    <label class="flex flex-col gap-1 text-sm">
                        <span class="text-slate-300">IBAN</span>
                        <input wire:model="iban" type="text" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                        @error('iban') <span class="text-red-400">{{ $message }}</span> @enderror
                    </label>

                    <label class="flex flex-col gap-1 text-sm">
                        <span class="text-slate-300">Branch <span class="text-slate-500">(optional)</span></span>
                        <input wire:model="branch" type="text" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                        @error('branch') <span class="text-red-400">{{ $message }}</span> @enderror
                    </label>
                @endif

                <label class="flex flex-col gap-1 text-sm sm:col-span-2">
                    <span class="text-slate-300">Extra instructions for this account <span class="text-slate-500">(optional)</span></span>
                    <textarea wire:model="instructions" rows="2" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2"></textarea>
                    @error('instructions') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex flex-col gap-1 text-sm">
                    <span class="text-slate-300">Display order <span class="text-slate-500">(lowest first)</span></span>
                    <input wire:model="sortOrder" type="number" min="0" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
                    @error('sortOrder') <span class="text-red-400">{{ $message }}</span> @enderror
                </label>

                <label class="flex items-center gap-2 self-end py-2 text-sm text-slate-300">
                    <input wire:model="isActive" type="checkbox" class="rounded border-slate-600 bg-slate-950 accent-sky-500">
                    Shown to buyers
                </label>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">Save</button>
                <button type="button" wire:click="cancel" class="text-sm text-slate-400 hover:text-white">Cancel</button>
            </div>
        </form>
    @endif

    @if ($accounts->where('is_active', true)->isEmpty())
        <p class="rounded-md border border-amber-800 bg-amber-950/60 px-4 py-2 text-sm text-amber-200">No accounts are shown to buyers, so nobody can pay for a package yet.</p>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($accounts as $account)
            <div wire:key="account-{{ $account->id }}" @class(['flex flex-col gap-3 rounded-xl border border-slate-800 bg-slate-900 p-5 text-sm', 'opacity-60' => ! $account->is_active])>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="font-semibold">{{ $account->name }}</h2>
                    <span class="rounded bg-slate-800 px-1.5 py-0.5 text-xs text-slate-300">{{ $account->type->label() }}</span>
                    @unless ($account->is_active)
                        <span class="rounded bg-slate-800 px-1.5 py-0.5 text-xs text-slate-300">Hidden</span>
                    @endunless
                </div>

                <dl class="flex flex-col gap-1">
                    @foreach ($account->details() as $label => $value)
                        <div class="flex flex-wrap justify-between gap-x-3">
                            <dt class="text-slate-400">{{ $label }}</dt>
                            <dd class="font-mono text-slate-200">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                @if ($account->instructions)
                    <p class="whitespace-pre-line text-xs text-slate-400">{{ $account->instructions }}</p>
                @endif

                <div class="mt-auto flex items-center gap-4">
                    <button type="button" wire:click="edit({{ $account->id }})" class="text-slate-300 hover:text-white">Edit</button>
                    <button type="button" wire:click="toggleActive({{ $account->id }})" class="text-slate-300 hover:text-white">{{ $account->is_active ? 'Hide' : 'Show' }}</button>
                    <button type="button" wire:click="delete({{ $account->id }})" wire:confirm="Delete {{ $account->name }}? Past payments keep its name." class="text-red-400 hover:text-red-300">Delete</button>
                </div>
            </div>
        @endforeach
    </div>

    <form wire:submit="saveSettings" class="flex max-w-3xl flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
        <h2 class="font-semibold">Payment settings</h2>

        <label class="flex flex-col gap-1 text-sm">
            <span class="text-slate-300">Instructions shown to buyers</span>
            <textarea wire:model="paymentInstructions" rows="3" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2"></textarea>
            @error('paymentInstructions') <span class="text-red-400">{{ $message }}</span> @enderror
        </label>

        <label class="flex flex-col gap-1 text-sm">
            <span class="text-slate-300">Email address for receipts</span>
            <input wire:model="receiptEmail" type="email" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2">
            @error('receiptEmail') <span class="text-red-400">{{ $message }}</span> @enderror
        </label>

        <button type="submit" class="self-start rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">Save settings</button>
    </form>
</div>
