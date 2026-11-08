@inject('marketingSettings', 'App\Services\Marketing\MarketingSettings')
@php($rules = $marketingSettings->all())
<x-layouts::site title="Become a marketing officer" description="Introduce doctors, technicians and patients to {{ config('app.name') }} and earn commission on what they pay.">
    <x-site.page-header eyebrow="Marketing officers" title="Earn by introducing people to {{ config('app.name') }}">
        Tell doctors, cath labs and patients about the service. When someone you bring in pays for a package, you earn commission.
    </x-site.page-header>

    <section class="mx-auto grid max-w-6xl gap-12 px-4 py-24 sm:px-6 lg:grid-cols-[1fr_2fr]">
        <div class="flex flex-col gap-8">
            <div class="flex flex-col gap-3">
                <h2 class="text-lg font-semibold text-white">What you earn</h2>
                <table class="text-sm">
                    <thead>
                        <tr class="text-left text-slate-400">
                            <th class="py-1 pr-4 font-medium">Client</th>
                            <th class="py-1 pr-4 font-medium">First payment</th>
                            <th class="py-1 font-medium">Renewals</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-200">
                        @foreach ([App\Enums\Role::Physician, App\Enums\Role::Technician, App\Enums\Role::Patient] as $clientRole)
                            <tr>
                                <td class="py-1 pr-4">{{ $clientRole->label() }}</td>
                                <td class="py-1 pr-4 tabular-nums">{{ (float) $rules['rates'][$clientRole->value]['first'] }}%</td>
                                <td class="py-1 tabular-nums">{{ (float) $rules['rates'][$clientRole->value]['renewal'] }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="text-sm text-slate-400">
                    Renewals earn commission for {{ $rules['renewal_months'] }} months after a client's first payment. Commission can be paid {{ $rules['hold_days'] }} days after we confirm the client's payment. Free trials do not earn commission.
                </p>
            </div>

            <div class="flex flex-col gap-3">
                <h2 class="text-lg font-semibold text-white">How it works</h2>
                <ol class="flex list-decimal flex-col gap-2 pl-5 text-sm text-slate-300">
                    <li>Apply using this form. We review every application.</li>
                    <li>Once approved, you get your own referral code and link.</li>
                    <li>People sign up with your link or code, and become your clients.</li>
                    <li>Follow your clients and earnings on your dashboard, and download a statement for each payment we send you.</li>
                </ol>
            </div>

            <p class="rounded-xl border border-sky-900 bg-sky-950/40 p-4 text-sm text-sky-200">
                Patient privacy comes first. Marketing officers never see studies, reports or patient records. Doctors and technicians who use the service for their own work cannot also be marketing officers.
            </p>
        </div>

        <livewire:site.marketing-application-form />
    </section>
</x-layouts::site>
