@props(['eyebrow' => null, 'title'])
<section class="relative overflow-hidden border-b border-slate-800">
    <div class="pointer-events-none absolute inset-x-0 -top-40 mx-auto h-80 max-w-3xl rounded-full bg-sky-600/20 blur-3xl" aria-hidden="true"></div>
    <div class="relative mx-auto flex max-w-3xl flex-col items-center gap-4 px-4 py-20 text-center sm:px-6">
        @if ($eyebrow)
            <p class="text-sm font-medium uppercase tracking-widest text-sky-400">{{ $eyebrow }}</p>
        @endif
        <h1 class="text-4xl font-semibold tracking-tight text-white sm:text-5xl">{{ $title }}</h1>
        @if ($slot->isNotEmpty())
            <p class="text-lg text-slate-400">{{ $slot }}</p>
        @endif
    </div>
</section>
