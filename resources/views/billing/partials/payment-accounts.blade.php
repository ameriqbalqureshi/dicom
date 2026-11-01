{{-- The accounts buyers can pay into, from config/billing.php. Methods without any details are left out. --}}
@php
    $paymentAccounts = collect(config('billing.payment_methods'))
        ->map(fn (string $label, string $method) => ['label' => $label, 'details' => array_filter(config("billing.payment_accounts.{$method}", []), 'filled')])
        ->filter(fn (array $account) => $account['details'] !== []);
@endphp

@if ($paymentAccounts->isNotEmpty())
    <div class="grid gap-3 sm:grid-cols-2">
        @foreach ($paymentAccounts as $method => $account)
            <div wire:key="payment-account-{{ $method }}" class="flex flex-col gap-2 rounded-lg border border-slate-700 bg-slate-950 p-4 text-sm">
                <h3 class="font-medium text-white">{{ $account['label'] }}</h3>
                <dl class="flex flex-col gap-1">
                    @foreach ($account['details'] as $label => $value)
                        <div class="flex flex-wrap justify-between gap-x-3">
                            <dt class="text-slate-400">{{ $label }}</dt>
                            <dd class="select-all font-mono text-slate-200">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @endforeach
    </div>
@endif
