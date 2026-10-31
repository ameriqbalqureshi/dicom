<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="referrer" content="no-referrer">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full text-slate-100 antialiased">
        <nav class="border-b border-slate-800 bg-slate-900">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-6 px-4 py-3">
                <span class="font-semibold tracking-tight text-white">{{ config('app.name') }}</span>
                <span class="rounded bg-slate-800 px-1.5 py-0.5 text-xs text-slate-300">Guest view</span>
            </div>
        </nav>

        <main class="mx-auto max-w-7xl px-4 py-6">
            {{ $slot }}
        </main>
    </body>
</html>
