<x-layouts::site title="Contact us" description="Questions about {{ config('app.name') }}, packages or onboarding your team? Get in touch.">
    <x-site.page-header eyebrow="Contact" title="We would love to hear from you">
        Questions about packages, onboarding a department, or a problem with your account? Send us a message.
    </x-site.page-header>

    <section class="mx-auto grid max-w-6xl gap-12 px-4 py-24 sm:px-6 lg:grid-cols-[1fr_2fr]">
        <div class="flex flex-col gap-8">
            @if (config('site.contact.email'))
                <div class="flex flex-col gap-1">
                    <h2 class="text-sm font-medium text-slate-400">Email</h2>
                    <a href="mailto:{{ config('site.contact.email') }}" class="text-lg text-white hover:text-sky-300">{{ config('site.contact.email') }}</a>
                </div>
            @endif

            @if (config('site.contact.phone'))
                <div class="flex flex-col gap-1">
                    <h2 class="text-sm font-medium text-slate-400">Phone</h2>
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', config('site.contact.phone')) }}" class="text-lg text-white hover:text-sky-300">{{ config('site.contact.phone') }}</a>
                </div>
            @endif

            @if (config('site.contact.address'))
                <div class="flex flex-col gap-1">
                    <h2 class="text-sm font-medium text-slate-400">Office</h2>
                    <p class="whitespace-pre-line text-white">{{ config('site.contact.address') }}</p>
                </div>
            @endif

            @if (config('site.contact.hours'))
                <div class="flex flex-col gap-1">
                    <h2 class="text-sm font-medium text-slate-400">Support hours</h2>
                    <p class="text-white">{{ config('site.contact.hours') }}</p>
                </div>
            @endif

            <p class="rounded-xl border border-amber-900 bg-amber-950/40 p-4 text-sm text-amber-200">
                Please do not send patient images or identifiable patient details through this form. For a medical emergency,
                contact your local emergency services.
            </p>
        </div>

        <livewire:site.contact-form />
    </section>
</x-layouts::site>
