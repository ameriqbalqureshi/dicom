<x-layouts::site title="Pricing" description="DICOM viewing and sharing packages for physicians, technicians and patients. Start with a free trial, then pick the package that fits your work.">
    <x-site.page-header eyebrow="Pricing" title="Simple packages for every member of the team">
        Start with a free trial, then choose the package that fits how much you upload, share and report.
    </x-site.page-header>

    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <nav class="mx-auto flex w-fit rounded-full border border-slate-800 bg-slate-900 p-1 text-sm" aria-label="Account type">
            @foreach ($roles as $role)
                <a href="{{ route('pricing', ['for' => $role->value]) }}" @if ($role === $selectedRole) aria-current="page" @endif
                    class="rounded-full px-5 py-2 font-medium {{ $role === $selectedRole ? 'bg-sky-600 text-white' : 'text-slate-400 hover:text-white' }}">
                    {{ Str::plural($role->label()) }}
                </a>
            @endforeach
        </nav>

        @if ($packages->isEmpty())
            <div class="mx-auto mt-12 max-w-xl rounded-2xl border border-slate-800 bg-slate-900/60 p-8 text-center text-slate-400">
                Packages for {{ Str::plural(strtolower($selectedRole->label())) }} are being updated.
                <a href="{{ route('contact') }}" class="text-sky-400 hover:text-sky-300">Contact us</a> for current pricing.
            </div>
        @else
            <div @class([
                'mx-auto mt-12 grid gap-6',
                'max-w-md' => $packages->count() === 1,
                'max-w-4xl sm:grid-cols-2' => $packages->count() === 2,
                'sm:grid-cols-2 lg:grid-cols-3' => $packages->count() === 3,
                'sm:grid-cols-2 lg:grid-cols-4' => $packages->count() >= 4,
            ])>
                @foreach ($packages as $package)
                    <div @class([
                        'flex flex-col gap-6 rounded-2xl border p-6',
                        'border-sky-700 bg-gradient-to-b from-sky-950/60 to-slate-900' => $package->is_trial,
                        'border-slate-800 bg-slate-900/60' => ! $package->is_trial,
                    ])>
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center justify-between gap-2">
                                <h2 class="text-lg font-semibold text-white">{{ $package->name }}</h2>
                                @if ($package->is_trial)
                                    <span class="rounded-full bg-sky-600 px-2.5 py-0.5 text-xs font-medium text-white">Start here</span>
                                @endif
                            </div>
                            @if ($package->description)
                                <p class="text-sm text-slate-400">{{ $package->description }}</p>
                            @endif
                        </div>

                        <div class="flex items-baseline gap-2">
                            @if ($package->isFree())
                                <span class="text-4xl font-semibold tracking-tight text-white">Free</span>
                            @else
                                <span class="text-sm font-medium text-slate-400">{{ $currency }}</span>
                                <span class="text-4xl font-semibold tracking-tight text-white">{{ number_format($package->price) }}</span>
                            @endif
                            <span class="text-sm text-slate-400">
                                @if ($package->duration_days)
                                    {{ $package->isFree() ? 'for' : '/' }} {{ $package->duration_days }} {{ Str::plural('day', $package->duration_days) }}
                                @else
                                    {{ $package->isFree() ? 'forever' : 'one-time' }}
                                @endif
                            </span>
                        </div>

                        <ul class="flex flex-col gap-3 text-sm">
                            @foreach (App\Enums\Quota::forRole($package->role) as $quota)
                                @php($limit = $package->{$quota->limitColumn()})
                                <li @class(['flex gap-3', 'text-slate-500' => $limit === 0, 'text-slate-300' => $limit !== 0])>
                                    @if ($limit === 0)
                                        <svg class="mt-0.5 size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                        <span>{{ $quota->label() }} <span class="sr-only">not included</span></span>
                                    @else
                                        <svg class="mt-0.5 size-4 shrink-0 text-sky-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                        <span>{{ $limit === null ? 'Unlimited' : number_format($limit) }} {{ Str::lower($quota->label()) }}</span>
                                    @endif
                                </li>
                            @endforeach
                            <li class="flex gap-3 text-slate-300">
                                <svg class="mt-0.5 size-4 shrink-0 text-sky-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                <span>
                                    @if ($package->max_storage_mb === null)
                                        Unlimited storage
                                    @else
                                        {{ $package->max_storage_mb >= 1024 ? round($package->max_storage_mb / 1024, 1).' GB' : $package->max_storage_mb.' MB' }} of storage
                                    @endif
                                </span>
                            </li>
                            <li class="flex gap-3 text-slate-300">
                                <svg class="mt-0.5 size-4 shrink-0 text-sky-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                <span>Full browser viewer with cine and measurements</span>
                            </li>
                        </ul>

                        @auth
                            <a href="{{ route('package.show') }}" class="mt-auto rounded-md border border-sky-600 px-4 py-2.5 text-center text-sm font-semibold text-sky-300 hover:bg-sky-950">Manage your package</a>
                        @else
                            <a href="{{ route('register') }}" @class([
                                'mt-auto rounded-md px-4 py-2.5 text-center text-sm font-semibold',
                                'bg-sky-600 text-white hover:bg-sky-500' => $package->is_trial,
                                'border border-sky-600 text-sky-300 hover:bg-sky-950' => ! $package->is_trial,
                            ])>{{ $package->is_trial ? 'Start free trial' : 'Get started' }}</a>
                        @endauth
                    </div>
                @endforeach
            </div>
        @endif

        <p class="mt-8 text-center text-sm text-slate-500">
            Prices in {{ $currency }}. Every new account starts on the free trial; upgrade from your account whenever you are ready.
        </p>
    </section>

    <section class="border-y border-slate-800 bg-slate-900/40">
        <div class="mx-auto grid max-w-7xl gap-12 px-4 py-20 sm:px-6 lg:grid-cols-3">
            <div class="flex flex-col gap-3">
                <h2 class="text-2xl font-semibold tracking-tight text-white">How payment works</h2>
                <p class="text-slate-400">No card needed. Pay the way you already do, and your package starts as soon as the payment is confirmed.</p>
            </div>
            <ol class="grid gap-6 sm:grid-cols-3 lg:col-span-2">
                @foreach ([
                    ['Choose a package', 'Pick a package from the Package page in your account.'],
                    ['Pay locally', 'Pay into one of the accounts below, then send us your receipt from your account, by email or through our receipt form.'],
                    ['Get going', 'We confirm the payment, usually the same working day, and email you when your package starts.'],
                ] as [$heading, $text])
                    <li class="flex flex-col gap-2">
                        <span class="text-sm font-semibold text-sky-400">Step {{ $loop->iteration }}</span>
                        <h3 class="font-semibold text-white">{{ $heading }}</h3>
                        <p class="text-sm leading-6 text-slate-400">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
            <div class="lg:col-span-3">
                @include('billing.partials.payment-accounts')
                <p class="mt-4 text-sm text-slate-400">Already paid? <a href="{{ route('payment-receipt') }}" class="text-sky-400 hover:text-sky-300">Send us your receipt</a>.</p>
            </div>
        </div>
    </section>

    <section class="mx-auto grid max-w-5xl gap-8 px-4 py-20 sm:px-6 md:grid-cols-2">
        @foreach ([
            ['What happens when I reach a limit?', 'You can keep opening your studies and those shared with you. To upload, share or report more, choose a new package.'],
            ['Can I change packages?', 'Yes. A new package replaces your current one when it starts, and its limits start from zero.'],
            ['Do you offer plans for hospitals?', 'We can set up accounts for a whole department or cath lab. Get in touch and we will put together a package for you.'],
            ['Is there a contract?', 'No. Packages run for a fixed period and do not renew automatically. Buy another one when you need it.'],
        ] as [$question, $answer])
            <div class="flex flex-col gap-2">
                <h3 class="font-semibold text-white">{{ $question }}</h3>
                <p class="text-sm leading-6 text-slate-400">{{ $answer }}</p>
            </div>
        @endforeach
    </section>

    @include('site.partials.call-to-action')
</x-layouts::site>
