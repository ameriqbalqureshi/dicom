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
                    <a href="{{ route('studies.index') }}" class="font-semibold tracking-tight text-white">
                        {{ config('app.name') }}
                    </a>

                    <div class="flex items-center gap-1 text-sm">
                        <x-nav-link :href="route('studies.index')" :active="request()->routeIs('studies.index', 'studies.show')">
                            Studies
                        </x-nav-link>

                        @can('create', App\Models\Study::class)
                            <x-nav-link :href="route('studies.upload')" :active="request()->routeIs('studies.upload')">
                                Upload
                            </x-nav-link>
                        @endcan

                        @if (auth()->user()->isPhysician())
                            <x-nav-link :href="route('shares.pending')" :active="request()->routeIs('shares.pending')">
                                Requests
                                @php($pendingShareCount = auth()->user()->receivedShares()->pending()->count())
                                @if ($pendingShareCount > 0)
                                    <span class="ml-1 rounded-full bg-sky-600 px-1.5 text-xs text-white">{{ $pendingShareCount }}</span>
                                @endif
                            </x-nav-link>
                        @endif

                        @can('manage-users')
                            <x-nav-link :href="route('users.index')" :active="request()->routeIs('users.index')">
                                Users
                            </x-nav-link>
                        @endcan
                    </div>
                </div>

                <div class="flex items-center gap-4 text-sm">
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

        <main class="mx-auto max-w-7xl px-4 py-6">
            {{ $slot }}
        </main>
    </body>
</html>
