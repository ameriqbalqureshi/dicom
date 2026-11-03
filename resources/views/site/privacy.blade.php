<x-layouts::site title="Privacy policy" description="How {{ config('app.name') }} collects, uses and protects personal and medical data.">
    <x-site.page-header eyebrow="Legal" title="Privacy policy" />

    <article class="mx-auto flex max-w-3xl flex-col gap-10 px-4 py-20 text-slate-300 sm:px-6 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-white [&_li]:ml-5 [&_li]:list-disc [&_section]:flex [&_section]:flex-col [&_section]:gap-3 [&_ul]:flex [&_ul]:flex-col [&_ul]:gap-1.5">
        <p class="text-sm text-slate-500">Last updated {{ \Illuminate\Support\Carbon::parse('2026-11-01')->toFormattedDateString() }}</p>

        <section>
            <p>
                {{ config('app.name') }} lets healthcare professionals and patients store, view and share medical images and reports.
                Because this involves health information, we take privacy seriously. This policy explains what we collect, why, and
                the choices you have.
            </p>
        </section>

        <section>
            <h2>Information we collect</h2>
            <ul>
                <li><strong class="text-white">Account details:</strong> your name, email address, password (stored only as a secure hash) and account type. Physicians also give their registration number, qualifications and, optionally, a signature image for reports.</li>
                <li><strong class="text-white">Medical images and reports:</strong> DICOM studies you upload, which contain patient details and images, and reports written about them.</li>
                <li><strong class="text-white">Billing details:</strong> the packages you buy and the payment method and transaction reference you give us. We do not collect card numbers.</li>
                <li><strong class="text-white">Messages:</strong> what you send us through the contact form or by email.</li>
                <li><strong class="text-white">Technical data:</strong> information needed to run the service securely, such as IP addresses, browser type and session cookies.</li>
            </ul>
        </section>

        <section>
            <h2>How we use it</h2>
            <ul>
                <li>To provide the service: storing studies, showing them to the people you share them with, and producing reports.</li>
                <li>To verify physicians before they can receive shared studies or sign reports.</li>
                <li>To send emails about your account, such as shares, reports, payments and package reminders.</li>
                <li>To keep the service secure, prevent abuse and meet our legal obligations.</li>
            </ul>
            <p>We do not sell personal data, use medical images for advertising, or share them with anyone you have not chosen to share them with, except where the law requires it.</p>
        </section>

        <section>
            <h2>Who can see your studies</h2>
            <p>
                A study is visible to the person who uploaded it and to the people they share it with. Physicians may forward a study
                shared with them to another verified physician; if the original share is withdrawn, everyone it was forwarded to loses
                access too. If you delete a study you have shared, or a patient's study reaches the end of its retention period, the
                physicians who can open it may each choose to keep their own copy, as a clinician would keep records of a patient they
                have seen. Guest links give view-only access for 24 hours and are protected by a PIN unless the person creating the link
                turns it off. Our administrators can access studies when needed to operate and support the service.
            </p>
        </section>

        <section>
            <h2>How long we keep data</h2>
            <ul>
                <li>Studies uploaded by patients are deleted automatically after a short retention period.</li>
                <li>Other studies are kept until the person who uploaded them deletes them or closes their account.</li>
                <li>When a shared study is deleted or expires, physicians who can open it have {{ config('studies.handover_days') }} days to keep it; it is deleted if nobody does.</li>
                <li>Billing records are kept for as long as accounting and tax law requires.</li>
            </ul>
        </section>

        <section>
            <h2>Security</h2>
            <p>
                Data is transferred over encrypted connections, access is controlled study by study, and passwords are hashed.
                No system is perfectly secure, so please use a strong password, keep it to yourself and sign out on shared computers.
            </p>
        </section>

        <section>
            <h2>Your responsibilities when uploading patient data</h2>
            <p>
                If you upload images of other people, you are responsible for having the right to do so and for sharing them only with
                people involved in the patient's care, in line with the laws and professional rules that apply to you.
            </p>
        </section>

        <section>
            <h2>Your rights</h2>
            <p>
                You can view and update your account details at any time and delete studies you uploaded. To ask for a copy of your
                data, to correct it, or to close your account, <a href="{{ route('contact') }}" class="text-sky-400 hover:text-sky-300">contact us</a>.
            </p>
        </section>

        <section>
            <h2>Changes to this policy</h2>
            <p>We may update this policy as the service changes. We will post the new version here and, for significant changes, let you know by email.</p>
        </section>
    </article>
</x-layouts::site>
