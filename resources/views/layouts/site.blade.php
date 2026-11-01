@props(['title' => null, 'description' => null])
@php($navigation = ['home' => 'Home', 'pricing' => 'Pricing', 'about' => 'About', 'contact' => 'Contact'])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth bg-slate-950">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
        <meta name="description" content="{{ $description ?? config('site.tagline') }}">
        <meta property="og:title" content="{{ $title ?? config('app.name') }}">
        <meta property="og:description" content="{{ $description ?? config('site.tagline') }}">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        <link rel="canonical" href="{{ url()->current() }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-full flex-col text-slate-100 antialiased">
        <header class="sticky top-0 z-40 border-b border-slate-800/80 bg-slate-950/85 backdrop-blur">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-4 py-4 sm:px-6">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold tracking-tight text-white">
                    <x-site.logo class="size-7" />
                    {{ config('app.name') }}
                </a>

                <nav class="hidden items-center gap-1 text-sm md:flex" aria-label="Main">
                    @foreach ($navigation as $routeName => $label)
                        <a href="{{ route($routeName) }}" @if (request()->routeIs($routeName)) aria-current="page" @endif
                            class="rounded-md px-3 py-2 {{ request()->routeIs($routeName) ? 'text-white' : 'text-slate-400 hover:text-white' }}">{{ $label }}</a>
                    @endforeach
                </nav>

                <div class="hidden items-center gap-3 text-sm md:flex">
                    @auth
                        <a href="{{ route('studies.index') }}" class="rounded-md bg-sky-600 px-4 py-2 font-medium text-white hover:bg-sky-500">Open app</a>
                    @else
                        <a href="{{ route('login') }}" class="px-3 py-2 text-slate-300 hover:text-white">Sign in</a>
                        <a href="{{ route('register') }}" class="rounded-md bg-sky-600 px-4 py-2 font-medium text-white hover:bg-sky-500">Start free trial</a>
                    @endauth
                </div>

                <details class="group relative md:hidden">
                    <summary class="flex cursor-pointer list-none items-center rounded-md border border-slate-700 p-2 text-slate-300 [&::-webkit-details-marker]:hidden" aria-label="Menu">
                        <svg class="size-5 group-open:hidden" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                        <svg class="hidden size-5 group-open:block" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </summary>
                    <div class="absolute right-0 mt-2 flex w-56 flex-col gap-1 rounded-xl border border-slate-800 bg-slate-900 p-2 text-sm shadow-2xl shadow-black/50">
                        @foreach ($navigation as $routeName => $label)
                            <a href="{{ route($routeName) }}" class="rounded-md px-3 py-2 {{ request()->routeIs($routeName) ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800' }}">{{ $label }}</a>
                        @endforeach
                        <div class="my-1 border-t border-slate-800"></div>
                        @auth
                            <a href="{{ route('studies.index') }}" class="rounded-md bg-sky-600 px-3 py-2 text-center font-medium text-white">Open app</a>
                        @else
                            <a href="{{ route('login') }}" class="rounded-md px-3 py-2 text-slate-300 hover:bg-slate-800">Sign in</a>
                            <a href="{{ route('register') }}" class="rounded-md bg-sky-600 px-3 py-2 text-center font-medium text-white">Start free trial</a>
                        @endauth
                    </div>
                </details>
            </div>
        </header>

        <main class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-slate-800 bg-slate-950">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 py-12 sm:px-6 md:grid-cols-[2fr_1fr_1fr_1fr]">
                <div class="flex flex-col gap-3">
                    <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold text-white">
                        <x-site.logo class="size-6" />
                        {{ config('app.name') }}
                    </a>
                    <p class="max-w-xs text-sm text-slate-400">{{ config('site.tagline') }}</p>
                </div>

                <div class="flex flex-col gap-2 text-sm">
                    <h2 class="font-medium text-white">Product</h2>
                    <a href="{{ route('home') }}#features" class="text-slate-400 hover:text-white">Features</a>
                    <a href="{{ route('pricing') }}" class="text-slate-400 hover:text-white">Pricing</a>
                    <a href="{{ route('home') }}#faq" class="text-slate-400 hover:text-white">FAQ</a>
                    <a href="{{ route('register') }}" class="text-slate-400 hover:text-white">Create an account</a>
                </div>

                <div class="flex flex-col gap-2 text-sm">
                    <h2 class="font-medium text-white">Company</h2>
                    <a href="{{ route('about') }}" class="text-slate-400 hover:text-white">About us</a>
                    <a href="{{ route('contact') }}" class="text-slate-400 hover:text-white">Contact</a>
                    <a href="{{ route('privacy') }}" class="text-slate-400 hover:text-white">Privacy policy</a>
                    <a href="{{ route('terms') }}" class="text-slate-400 hover:text-white">Terms of service</a>
                </div>

                <div class="flex flex-col gap-2 text-sm">
                    <h2 class="font-medium text-white">Get in touch</h2>
                    @if (config('site.contact.email'))
                        <a href="mailto:{{ config('site.contact.email') }}" class="text-slate-400 hover:text-white">{{ config('site.contact.email') }}</a>
                    @endif
                    @if (config('site.contact.phone'))
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', config('site.contact.phone')) }}" class="text-slate-400 hover:text-white">{{ config('site.contact.phone') }}</a>
                    @endif
                    @if (config('site.contact.address'))
                        <p class="whitespace-pre-line text-slate-400">{{ config('site.contact.address') }}</p>
                    @endif
                </div>
            </div>

            <div class="border-t border-slate-800">
                <p class="mx-auto max-w-7xl px-4 py-6 text-xs text-slate-500 sm:px-6">
                    &copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved. {{ config('app.name') }} is a tool for sharing and reviewing images; it does not replace clinical judgement.
                </p>
            </div>
        </footer>
    </body>
</html>
