@php
    $features = [
        [
            'title' => 'Diagnostic viewer in the browser',
            'body' => 'Open studies on any computer without installing software. Scroll through series, adjust window and level by dragging, invert, magnify and go full screen.',
            'icon' => 'M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25',
        ],
        [
            'title' => 'Cine playback for angiography',
            'body' => 'Multi-frame runs such as coronary angiograms play back at their recorded frame rate, with frame-by-frame stepping and adjustable speed.',
            'icon' => 'M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z',
        ],
        [
            'title' => 'Calibrated measurements',
            'body' => 'Place calipers in millimetres using the pixel spacing in the image, or calibrate against a known length such as a catheter.',
            'icon' => 'M7.5 3.75H6A2.25 2.25 0 0 0 3.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0 1 20.25 6v1.5m0 9V18A2.25 2.25 0 0 1 18 20.25h-1.5m-9 0H6A2.25 2.25 0 0 1 3.75 18v-1.5M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
        ],
        [
            'title' => 'Share with verified physicians',
            'body' => 'Send a study to a colleague in seconds. Physicians are checked against their medical registration before they can receive studies, and can forward them along a referral.',
            'icon' => 'M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z',
        ],
        [
            'title' => 'Signed reports as PDF',
            'body' => 'Write reports beside the images, finalise them with your signature and registration number, and add addenda later. Everyone with access sees the final report.',
            'icon' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
        ],
        [
            'title' => 'Secure guest links',
            'body' => 'Need a second opinion from someone without an account? Create a link that expires after 24 hours, protected by a six-digit PIN.',
            'icon' => 'M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244',
        ],
    ];

    $audiences = [
        [
            'role' => 'Technicians',
            'summary' => 'Get studies off the modality and in front of the right doctor.',
            'points' => ['Upload whole studies straight from the cath lab or imaging suite', 'Share with the reporting physician in one step', 'Keep track of what you have sent and to whom'],
        ],
        [
            'role' => 'Physicians',
            'summary' => 'Review, measure and report from wherever you are.',
            'points' => ['Receive studies from technicians, colleagues and patients', 'Forward a case to a surgeon or specialist for an opinion', 'Issue signed PDF reports with addenda when needed'],
        ],
        [
            'role' => 'Patients',
            'summary' => 'Keep hold of your images and show them to your doctor.',
            'points' => ['Upload the study from the CD you were given', 'Send it to a physician of your choice, who accepts the request', 'Share a temporary link instead of carrying discs around'],
        ],
    ];

    $faqs = [
        ['question' => 'Do I need to install anything?', 'answer' => 'No. Everything runs in a modern web browser on Windows, macOS or Linux. Images are decoded in your browser, so there is nothing to download or configure.'],
        ['question' => 'Which images are supported?', 'answer' => 'Standard DICOM files, including multi-frame X-ray angiography, CT, MR and other greyscale modalities, uncompressed or JPEG lossless. Select the files from a CD or the modality and we sort them into studies and series.'],
        ['question' => 'Who can see my studies?', 'answer' => 'Only you and the people you choose. Studies can be shared with physicians we have verified, and you can stop sharing at any time; when you do, anyone the study was forwarded to loses access too.'],
        ['question' => 'How do I pay for a package?', 'answer' => 'Choose a package, pay into one of our bank or mobile accounts, and send us your receipt. Your package starts as soon as we confirm the payment.'],
        ['question' => 'What happens to patient uploads?', 'answer' => 'To protect patient privacy, studies uploaded by patients are kept for a limited time and then deleted automatically.'],
        ['question' => 'Is this a replacement for our PACS?', 'answer' => 'No. It is built for sharing and reviewing studies between people and places that do not share a PACS: referrals, second opinions and remote reporting.'],
    ];

    $pageDescription = 'View, share and report DICOM angiograms and medical images in any browser. No CDs, no installs. Send studies to verified physicians. Free trial.';

    $structuredData = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'WebSite',
                'name' => config('app.name'),
                'url' => route('home'),
            ],
            array_filter([
                '@type' => 'Organization',
                'name' => config('app.name'),
                'url' => route('home'),
                'logo' => asset('apple-touch-icon.png'),
                'image' => asset('og-image.jpg'),
                'email' => config('site.contact.email'),
                'telephone' => config('site.contact.phone'),
            ]),
            [
                '@type' => 'WebApplication',
                'name' => config('app.name'),
                'url' => route('home'),
                'description' => $pageDescription,
                'applicationCategory' => 'HealthApplication',
                'operatingSystem' => 'Any modern web browser',
                'browserRequirements' => 'Requires a modern web browser with JavaScript enabled',
                'image' => asset('og-image.jpg'),
            ],
            [
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn (array $faq): array => [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
                ], $faqs),
            ],
        ],
    ];
@endphp

<x-layouts::site title="Online DICOM Viewer for Coronary Angiograms" :description="$pageDescription">
    <x-slot:head>
        <script type="application/ld+json">@json($structuredData)</script>
    </x-slot:head>

    {{-- Hero --}}
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,var(--color-sky-900)_0%,transparent_60%)] opacity-60" aria-hidden="true"></div>
        <div class="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,rgb(148_163_184/0.06)_1px,transparent_1px),linear-gradient(to_bottom,rgb(148_163_184/0.06)_1px,transparent_1px)] bg-[size:48px_48px] [mask-image:radial-gradient(ellipse_at_top,black_30%,transparent_70%)]" aria-hidden="true"></div>

        <div class="relative mx-auto grid max-w-7xl items-center gap-16 px-4 pb-24 pt-20 sm:px-6 lg:grid-cols-2 lg:pt-28">
            <div class="flex flex-col items-start gap-6">
                <span class="rounded-full border border-sky-800 bg-sky-950/60 px-3 py-1 text-xs font-medium text-sky-300">Online DICOM viewer for cath labs, cardiologists and their patients</span>
                <h1 class="text-4xl font-semibold tracking-tight text-white sm:text-6xl">
                    Medical images, <span class="bg-gradient-to-r from-sky-400 to-cyan-300 bg-clip-text text-transparent">shared in seconds.</span>
                </h1>
                <p class="max-w-xl text-lg text-slate-400">
                    Upload DICOM studies, view them in any browser, send them to verified physicians and get back a signed report. No CDs, no installs, no waiting.
                </p>
                <div class="flex flex-wrap items-center gap-3">
                    @auth
                        <a href="{{ route('studies.index') }}" class="rounded-md bg-sky-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-sky-900/50 hover:bg-sky-500">Go to your studies</a>
                    @else
                        <a href="{{ route('register') }}" class="rounded-md bg-sky-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-sky-900/50 hover:bg-sky-500">Start your free trial</a>
                    @endauth
                    <a href="{{ route('pricing') }}" class="rounded-md border border-slate-700 px-5 py-3 text-sm font-semibold text-slate-200 hover:border-slate-500 hover:text-white">See pricing</a>
                </div>
                <p class="text-sm text-slate-500">Free trial for every account. No card required.</p>
            </div>

            {{-- Illustration of the viewer --}}
            <div class="relative" aria-hidden="true">
                <div class="absolute -inset-4 rounded-3xl bg-gradient-to-tr from-sky-600/20 to-cyan-400/10 blur-2xl"></div>
                <div class="relative overflow-hidden rounded-2xl border border-slate-700 bg-slate-900 shadow-2xl shadow-black/60">
                    <div class="flex items-center gap-2 border-b border-slate-800 px-4 py-3">
                        <span class="size-3 rounded-full bg-slate-700"></span>
                        <span class="size-3 rounded-full bg-slate-700"></span>
                        <span class="size-3 rounded-full bg-slate-700"></span>
                        <span class="ml-3 truncate text-xs text-slate-400">LAD · RAO 30° CRA 20°</span>
                    </div>
                    <div class="flex flex-wrap gap-1.5 border-b border-slate-800 px-4 py-2 text-[11px] text-slate-300">
                        <span class="rounded border border-sky-600 bg-sky-950/60 px-2 py-1">Pause</span>
                        <span class="rounded border border-slate-700 px-2 py-1">15 fps</span>
                        <span class="rounded border border-sky-600 bg-sky-950/60 px-2 py-1">Caliper</span>
                        <span class="rounded border border-slate-700 px-2 py-1">Magnify</span>
                        <span class="rounded border border-slate-700 px-2 py-1">Invert</span>
                        <span class="ml-auto rounded border border-slate-700 px-2 py-1">Full screen</span>
                    </div>
                    <div class="relative aspect-[4/3] bg-black">
                        <svg viewBox="0 0 400 300" class="absolute inset-0 h-full w-full">
                            <defs>
                                <radialGradient id="hero-field" cx="50%" cy="50%" r="60%">
                                    <stop offset="0%" stop-color="#5b6470" />
                                    <stop offset="70%" stop-color="#2a2f36" />
                                    <stop offset="100%" stop-color="#0b0d10" />
                                </radialGradient>
                                <filter id="hero-blur"><feGaussianBlur stdDeviation="1.2" /></filter>
                            </defs>
                            <circle cx="200" cy="150" r="170" fill="url(#hero-field)" />
                            <g fill="none" stroke="#0f1215" stroke-linecap="round" filter="url(#hero-blur)">
                                <path d="M120 40 C150 80 170 110 185 150 S215 230 250 275" stroke-width="9" />
                                <path d="M185 150 C215 140 250 150 290 175 S330 220 345 250" stroke-width="6" />
                                <path d="M160 105 C135 130 115 165 105 210" stroke-width="5" />
                                <path d="M215 210 C240 205 265 215 285 235" stroke-width="4" />
                                <path d="M250 158 C265 130 285 115 310 105" stroke-width="3.5" />
                                <path d="M130 160 C150 175 160 195 162 225" stroke-width="3" />
                                <path d="M95 30 L150 95" stroke-width="12" stroke="#1b1f24" />
                            </g>
                            <g stroke="#38bdf8" stroke-width="1.5">
                                <line x1="176" y1="128" x2="196" y2="168" />
                                <circle cx="176" cy="128" r="3" fill="#38bdf8" />
                                <circle cx="196" cy="168" r="3" fill="#38bdf8" />
                            </g>
                            <text x="202" y="142" fill="#7dd3fc" font-size="11" font-family="ui-sans-serif, system-ui">4.6 mm</text>
                        </svg>
                        <div class="absolute left-3 top-3 text-[11px] leading-4 text-slate-300">
                            <div>XA · Run 3</div>
                            <div>Frame 23 / 64</div>
                        </div>
                        <div class="absolute bottom-3 left-3 text-[11px] leading-4 text-slate-300">512 × 512</div>
                        <div class="absolute bottom-3 right-3 text-[11px] leading-4 text-slate-300">W 1820 · L 940</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Highlights --}}
    <section class="border-y border-slate-800 bg-slate-900/40">
        <dl class="mx-auto grid max-w-7xl grid-cols-2 gap-8 px-4 py-10 text-center sm:px-6 lg:grid-cols-4">
            <div class="flex flex-col gap-1">
                <dt class="text-sm text-slate-400">Software to install</dt>
                <dd class="text-2xl font-semibold text-white">None</dd>
            </div>
            <div class="flex flex-col gap-1">
                <dt class="text-sm text-slate-400">To share a study</dt>
                <dd class="text-2xl font-semibold text-white">One click</dd>
            </div>
            <div class="flex flex-col gap-1">
                <dt class="text-sm text-slate-400">Guest links expire after</dt>
                <dd class="text-2xl font-semibold text-white">24 hours</dd>
            </div>
            <div class="flex flex-col gap-1">
                <dt class="text-sm text-slate-400">Reports delivered as</dt>
                <dd class="text-2xl font-semibold text-white">Signed PDF</dd>
            </div>
        </dl>
    </section>

    {{-- Features --}}
    <section id="features" class="mx-auto max-w-7xl scroll-mt-20 px-4 py-24 sm:px-6">
        <div class="mx-auto flex max-w-2xl flex-col gap-4 text-center">
            <p class="text-sm font-medium uppercase tracking-widest text-sky-400">Features</p>
            <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">Everything a referral needs, in one place</h2>
            <p class="text-slate-400">From the moment a study leaves the modality to the moment the report is signed.</p>
        </div>

        <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($features as $feature)
                <div class="flex flex-col gap-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-6 transition hover:border-slate-700">
                    <span class="flex size-11 items-center justify-center rounded-lg bg-sky-950 text-sky-400 ring-1 ring-sky-800">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $feature['icon'] }}" />
                        </svg>
                    </span>
                    <h3 class="text-lg font-semibold text-white">{{ $feature['title'] }}</h3>
                    <p class="text-sm leading-6 text-slate-400">{{ $feature['body'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- How it works --}}
    <section class="border-y border-slate-800 bg-slate-900/40">
        <div class="mx-auto max-w-7xl px-4 py-24 sm:px-6">
            <div class="mx-auto flex max-w-2xl flex-col gap-4 text-center">
                <p class="text-sm font-medium uppercase tracking-widest text-sky-400">How it works</p>
                <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">From scanner to signed report in three steps</h2>
            </div>

            <ol class="mt-16 grid gap-8 md:grid-cols-3">
                @foreach ([
                    ['Upload', 'Select the DICOM files from the modality or a patient CD. Studies and series are recognised and organised automatically.'],
                    ['Share', 'Send the study to a verified physician, or create a guest link with a PIN for someone outside the platform.'],
                    ['Report', 'The physician reviews, measures and signs a report. Everyone with access can open the final PDF.'],
                ] as [$step, $description])
                    <li class="relative flex flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-950 p-6">
                        <span class="flex size-9 items-center justify-center rounded-full bg-sky-600 text-sm font-semibold text-white">{{ $loop->iteration }}</span>
                        <h3 class="text-lg font-semibold text-white">{{ $step }}</h3>
                        <p class="text-sm leading-6 text-slate-400">{{ $description }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Audiences --}}
    <section class="mx-auto max-w-7xl px-4 py-24 sm:px-6">
        <div class="mx-auto flex max-w-2xl flex-col gap-4 text-center">
            <p class="text-sm font-medium uppercase tracking-widest text-sky-400">Who it is for</p>
            <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">One platform, the whole care team</h2>
            <p class="text-slate-400">Each account type gets the tools it needs, and a package sized for how it works.</p>
        </div>

        <div class="mt-16 grid gap-6 lg:grid-cols-3">
            @foreach ($audiences as $audience)
                <div class="flex flex-col gap-4 rounded-2xl border border-slate-800 bg-gradient-to-b from-slate-900 to-slate-950 p-8">
                    <h3 class="text-xl font-semibold text-white">For {{ strtolower($audience['role']) }}</h3>
                    <p class="text-slate-400">{{ $audience['summary'] }}</p>
                    <ul class="flex flex-col gap-3 text-sm text-slate-300">
                        @foreach ($audience['points'] as $point)
                            <li class="flex gap-3">
                                <svg class="mt-0.5 size-5 shrink-0 text-sky-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                {{ $point }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Privacy and security --}}
    <section class="border-y border-slate-800 bg-slate-900/40">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 py-24 sm:px-6 lg:grid-cols-2">
            <div class="flex flex-col gap-4">
                <p class="text-sm font-medium uppercase tracking-widest text-sky-400">Privacy by design</p>
                <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">Patient data stays with the people treating the patient</h2>
                <p class="text-slate-400">Access is granted study by study, never by default. Every share has an owner, and taking it back removes access down the whole referral chain.</p>
            </div>
            <ul class="grid gap-4 sm:grid-cols-2">
                @foreach ([
                    ['Verified recipients', 'Physicians are checked against their medical registration before anyone can share with them.'],
                    ['Revocable access', 'Stop sharing at any time. Forwarded copies lose access too.'],
                    ['Expiring guest links', 'Links last 24 hours and are protected by a PIN by default.'],
                    ['Limited retention', 'Patient uploads are deleted automatically after a short period.'],
                ] as [$heading, $text])
                    <li class="flex flex-col gap-2 rounded-xl border border-slate-800 bg-slate-950 p-5">
                        <h3 class="font-semibold text-white">{{ $heading }}</h3>
                        <p class="text-sm text-slate-400">{{ $text }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" class="mx-auto max-w-3xl scroll-mt-20 px-4 py-24 sm:px-6">
        <div class="flex flex-col gap-4 text-center">
            <p class="text-sm font-medium uppercase tracking-widest text-sky-400">FAQ</p>
            <h2 class="text-3xl font-semibold tracking-tight text-white sm:text-4xl">Questions, answered</h2>
        </div>

        <div class="mt-12 divide-y divide-slate-800 rounded-2xl border border-slate-800">
            @foreach ($faqs as $faq)
                <details class="group px-6 py-5">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium text-white [&::-webkit-details-marker]:hidden">
                        {{ $faq['question'] }}
                        <svg class="size-5 shrink-0 text-slate-400 transition group-open:rotate-45" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    </summary>
                    <p class="mt-3 text-sm leading-6 text-slate-400">{{ $faq['answer'] }}</p>
                </details>
            @endforeach
        </div>
    </section>

    @include('site.partials.call-to-action')
</x-layouts::site>
