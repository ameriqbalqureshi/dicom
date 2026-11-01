<section class="px-4 pb-24 sm:px-6">
    <div class="relative mx-auto max-w-5xl overflow-hidden rounded-3xl border border-sky-800 bg-gradient-to-br from-sky-900 via-slate-900 to-slate-950 px-6 py-16 text-center sm:px-16">
        <div class="pointer-events-none absolute -right-20 -top-20 size-72 rounded-full bg-sky-500/20 blur-3xl" aria-hidden="true"></div>
        <div class="relative flex flex-col items-center gap-6">
            <h2 class="max-w-2xl text-3xl font-semibold tracking-tight text-white sm:text-4xl">Stop burning CDs. Start sharing studies today.</h2>
            <p class="max-w-xl text-slate-300">Create an account in under a minute and try every feature of your plan free.</p>
            <div class="flex flex-wrap justify-center gap-3">
                @auth
                    <a href="{{ route('studies.index') }}" class="rounded-md bg-white px-5 py-3 text-sm font-semibold text-slate-900 hover:bg-slate-200">Go to your studies</a>
                @else
                    <a href="{{ route('register') }}" class="rounded-md bg-white px-5 py-3 text-sm font-semibold text-slate-900 hover:bg-slate-200">Start your free trial</a>
                @endauth
                <a href="{{ route('contact') }}" class="rounded-md border border-slate-500 px-5 py-3 text-sm font-semibold text-white hover:border-slate-300">Talk to us</a>
            </div>
        </div>
    </div>
</section>
