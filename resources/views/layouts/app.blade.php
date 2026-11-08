<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full text-slate-100 antialiased">
        <nav class="border-b border-slate-800 bg-slate-900">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-4 py-3">
                <div class="flex items-center gap-6">
                    <a href="{{ route(auth()->user()->isMarketingOfficer() ? 'marketing.dashboard' : 'studies.index') }}" class="font-semibold tracking-tight text-white">
                        {{ config('app.name') }}
                    </a>

                    <div class="flex items-center gap-1 text-sm">
                        @can('use-marketing')
                            <x-nav-link :href="route('marketing.dashboard')" :active="request()->routeIs('marketing.dashboard')">
                                Marketing
                            </x-nav-link>
                        @endcan

                        @can('viewAny', App\Models\Study::class)
                            <x-nav-link :href="route('studies.index')" :active="request()->routeIs('studies.index', 'studies.show')">
                                Studies
                            </x-nav-link>
                        @endcan

                        @can('create', App\Models\Study::class)
                            <x-nav-link :href="route('studies.upload')" :active="request()->routeIs('studies.upload')">
                                Upload
                            </x-nav-link>
                        @endcan

                        @can('viewAny', App\Models\PatientRecord::class)
                            <x-nav-link :href="route('patient-records.index')" :active="request()->routeIs('patient-records.*')">
                                Patients
                            </x-nav-link>
                        @endcan

                        @if (auth()->user()->isPhysician())
                            <x-nav-link :href="route('shares.pending')" :active="request()->routeIs('shares.pending')">
                                Requests
                                @php($openRequestCount = auth()->user()->openRequestCount())
                                @if ($openRequestCount > 0)
                                    <span class="ml-1 rounded-full bg-sky-600 px-1.5 text-xs text-white">{{ $openRequestCount }}</span>
                                @endif
                            </x-nav-link>
                            <x-nav-link :href="route('shares.senders')" :active="request()->routeIs('shares.senders')">
                                Senders
                            </x-nav-link>
                        @endif

                        @can('manage-users')
                            <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.index')">
                                Users
                            </x-nav-link>
                        @endcan

                        @can('manage-billing')
                            <x-nav-link :href="route('packages.index')" :active="request()->routeIs('packages.index')">
                                Packages
                            </x-nav-link>
                            <x-nav-link :href="route('payments.index')" :active="request()->routeIs('payments.index')">
                                Payments
                                @php($pendingPaymentCount = App\Models\Subscription::pending()->count() + App\Models\PaymentSubmission::count())
                                @if ($pendingPaymentCount > 0)
                                    <span class="ml-1 rounded-full bg-sky-600 px-1.5 text-xs text-white">{{ $pendingPaymentCount }}</span>
                                @endif
                            </x-nav-link>
                            <x-nav-link :href="route('payment-accounts.index')" :active="request()->routeIs('payment-accounts.index')">
                                Payment accounts
                            </x-nav-link>
                        @endcan

                        @can('manage-marketing')
                            <x-nav-link :href="route('marketing.index')" :active="request()->routeIs('marketing.index', 'marketing.officers.show')">
                                Marketing
                                @php($pendingApplicationCount = App\Models\MarketingApplication::pending()->count())
                                @if ($pendingApplicationCount > 0)
                                    <span class="ml-1 rounded-full bg-sky-600 px-1.5 text-xs text-white">{{ $pendingApplicationCount }}</span>
                                @endif
                            </x-nav-link>
                        @endcan
                    </div>
                </div>

                <div class="flex items-center gap-4 text-sm">
                    @if (auth()->user()->usesPackages())
                        <a href="{{ route('package.show') }}" class="{{ request()->routeIs('package.show') ? 'text-white' : 'text-slate-400 hover:text-white' }}">Package</a>
                    @endif

                    <a href="{{ route('profile.edit') }}" class="{{ request()->routeIs('profile.edit') ? 'text-white' : 'text-slate-400 hover:text-white' }}">Profile</a>

                    <span class="text-slate-400">
                        {{ auth()->user()->name }}
                        <span class="ml-1 rounded bg-slate-800 px-1.5 py-0.5 text-xs text-slate-300">{{ auth()->user()->role->label() }}</span>
                    </span>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-slate-400 hover:text-white">Sign out</button>
                    </form>
                </div>
            </div>
        </nav>

        @if (auth()->user()->isPhysician() && ! auth()->user()->isVerifiedPhysician())
            <div class="border-b border-amber-900 bg-amber-950/60">
                <p class="mx-auto max-w-7xl px-4 py-2 text-sm text-amber-200">
                    Your physician account is waiting for verification. Until then you can upload and view your own studies, but cannot receive shared studies or sign reports.
                </p>
            </div>
        @endif

        <main class="mx-auto max-w-7xl px-4 py-6">
            {{ $slot }}
        </main>
    </body>
</html>
