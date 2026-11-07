<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-950">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
        @include('site.partials.seo-meta', [
            'title' => $title ?? null,
            'description' => null,
            'indexable' => request()->routeIs('login', 'register'),
        ])

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-full items-center justify-center px-4 text-slate-100 antialiased">
        <div class="w-full max-w-sm">
            <h1 class="mb-6 text-center text-xl font-semibold tracking-tight text-white">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 hover:text-sky-300">
                    <x-site.logo class="size-7" />
                    {{ config('app.name') }}
                </a>
            </h1>

            {{ $slot }}
        </div>
    </body>
</html>
