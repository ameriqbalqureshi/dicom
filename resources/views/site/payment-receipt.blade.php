<x-layouts::site title="Send a payment receipt" description="Paid for a package? Send us your receipt so we can start it.">
    <x-site.page-header eyebrow="Payments" title="Send your payment receipt">
        Paid for a package? Send us the receipt and we will start your package as soon as we have confirmed the payment.
    </x-site.page-header>

    @php($receiptEmail = app(App\Services\Billing\BillingSettings::class)->receiptEmail())

    <section class="mx-auto flex max-w-4xl flex-col gap-8 px-4 py-16 sm:px-6">
        @include('billing.partials.payment-accounts')

        <livewire:site.payment-receipt-form />

        @if ($receiptEmail)
            <p class="text-sm text-slate-400">
                Prefer email? Send the receipt to
                <a href="mailto:{{ $receiptEmail }}" class="text-sky-400 hover:text-sky-300">{{ $receiptEmail }}</a>
                with your account email or user ID and the package you paid for. You can find your user ID on the Package page in your account.
            </p>
        @endif
    </section>
</x-layouts::site>
