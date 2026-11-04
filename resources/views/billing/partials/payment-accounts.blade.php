{{-- The accounts buyers can pay into, which administrators manage on the Payment accounts page. --}}
@php($paymentAccounts = App\Models\PaymentAccount::query()->active()->inDisplayOrder()->get())

@if ($paymentAccounts->isNotEmpty())
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach ($paymentAccounts as $account)
            <div wire:key="payment-account-{{ $account->id }}" class="flex flex-col gap-2 rounded-lg border border-slate-700 bg-slate-950 p-4 text-sm">
                <h3 class="font-medium text-white">{{ $account->name }}</h3>
                <dl class="flex flex-col gap-1">
                    @foreach ($account->details() as $label => $value)
                        <div class="flex flex-wrap justify-between gap-x-3">
                            <dt class="text-slate-400">{{ $label }}</dt>
                            <dd class="select-all font-mono text-slate-200">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($account->instructions)
                    <p class="whitespace-pre-line text-xs text-slate-400">{{ $account->instructions }}</p>
                @endif
            </div>
        @endforeach
    </div>
@endif
