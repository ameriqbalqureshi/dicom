<x-layouts::site title="About us" description="Why we built {{ config('app.name') }}: getting angiograms and other medical images to the right doctor as quickly as the referral, without CDs.">
    <x-site.page-header eyebrow="About us" title="Getting images to the right doctor, faster">
        We started {{ config('app.name') }} because a patient's images should travel as quickly as their referral does.
    </x-site.page-header>

    <section class="mx-auto grid max-w-7xl gap-16 px-4 py-24 sm:px-6 lg:grid-cols-2">
        <div class="flex flex-col gap-6 text-slate-300">
            <h2 class="text-2xl font-semibold tracking-tight text-white">The problem we set out to solve</h2>
            <p>
                Every day, angiograms and scans leave the hospital on a CD, or as a phone video of a monitor. A surgeon asked for an
                opinion has to find a computer with a disc drive and the right viewer. A patient travelling for treatment carries a
                stack of discs and hopes they still read. Hours, sometimes days, are lost before anyone looks at the images.
            </p>
            <p>
                {{ config('app.name') }} replaces that with a link. Technicians upload studies straight from the lab, physicians open
                them in a browser with the tools they need to make a decision, and the report goes back to everyone involved, signed
                and ready to file.
            </p>
            <p>
                We began with interventional cardiology, where a delay in getting an angiogram to the right specialist matters most,
                and built a viewer that handles cine runs and calibrated measurements properly. The same platform works for any
                specialty that needs to share DICOM images.
            </p>
        </div>

        <div class="grid gap-4 self-start sm:grid-cols-2">
            @foreach ([
                ['Clinically focused', 'Built with practising physicians and technicians, around how referrals actually happen.'],
                ['Private by default', 'Nothing is shared unless someone chooses to share it, and access can be taken back.'],
                ['Simple to adopt', 'No hardware, no installation and no IT project. If you have a browser, you are ready.'],
                ['Fair pricing', 'Packages for each kind of user, local payment methods and a free trial to start.'],
            ] as [$heading, $text])
                <div class="flex flex-col gap-2 rounded-2xl border border-slate-800 bg-slate-900/60 p-6">
                    <h3 class="font-semibold text-white">{{ $heading }}</h3>
                    <p class="text-sm leading-6 text-slate-400">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="border-y border-slate-800 bg-slate-900/40">
        <div class="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-24 text-center sm:px-6">
            <p class="text-sm font-medium uppercase tracking-widest text-sky-400">Our mission</p>
            <blockquote class="text-2xl font-medium leading-relaxed text-white sm:text-3xl">
                &ldquo;No patient should wait for treatment because their images were stuck on a disc.&rdquo;
            </blockquote>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-24 sm:px-6">
        <div class="mx-auto flex max-w-2xl flex-col gap-4 text-center">
            <h2 class="text-3xl font-semibold tracking-tight text-white">What we care about</h2>
        </div>
        <div class="mt-12 grid gap-8 md:grid-cols-3">
            @foreach ([
                ['Trust', 'We verify every physician before they can receive studies, and we never use your images for anything other than providing the service to you.'],
                ['Speed', 'A study should be viewable by the right person minutes after it is acquired, wherever they are.'],
                ['Respect for clinicians', 'Software should get out of the way. Every feature has to save time for the people using it.'],
            ] as [$heading, $text])
                <div class="flex flex-col gap-3 border-l-2 border-sky-600 pl-6">
                    <h3 class="text-lg font-semibold text-white">{{ $heading }}</h3>
                    <p class="text-sm leading-6 text-slate-400">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    @include('site.partials.call-to-action')
</x-layouts::site>
