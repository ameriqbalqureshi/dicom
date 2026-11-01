<x-layouts::site title="Terms of service" description="The terms that apply when you use {{ config('app.name') }}.">
    <x-site.page-header eyebrow="Legal" title="Terms of service" />

    <article class="mx-auto flex max-w-3xl flex-col gap-10 px-4 py-20 text-slate-300 sm:px-6 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-white [&_li]:ml-5 [&_li]:list-disc [&_section]:flex [&_section]:flex-col [&_section]:gap-3 [&_ul]:flex [&_ul]:flex-col [&_ul]:gap-1.5">
        <p class="text-sm text-slate-500">Last updated {{ \Illuminate\Support\Carbon::parse('2026-11-01')->toFormattedDateString() }}</p>

        <section>
            <p>
                These terms apply to your use of {{ config('app.name') }}. By creating an account or using the service, you agree to them.
                If you do not agree, please do not use the service.
            </p>
        </section>

        <section>
            <h2>Accounts</h2>
            <ul>
                <li>You must give accurate information when you sign up and keep your password secure.</li>
                <li>Physician accounts are verified against the registration number provided. We may refuse or remove verification at our discretion.</li>
                <li>You are responsible for activity under your account. Tell us straight away if you think someone else has used it.</li>
            </ul>
        </section>

        <section>
            <h2>Acceptable use</h2>
            <ul>
                <li>Upload and share medical images only where you have the right to, and only with people involved in the patient's care or with the patient's consent.</li>
                <li>Do not use the service for anything unlawful, to harass anyone, or to try to access data you have not been given access to.</li>
                <li>Do not interfere with the service, overload it, or attempt to get around package limits or security controls.</li>
            </ul>
        </section>

        <section>
            <h2>Clinical use</h2>
            <p>
                {{ config('app.name') }} is a tool for storing, sharing and reviewing images. It does not make diagnoses, and it does not
                replace the professional judgement of the clinicians who use it. Clinicians remain responsible for their interpretations
                and reports, and for confirming findings on appropriate equipment where needed.
            </p>
        </section>

        <section>
            <h2>Packages and payment</h2>
            <ul>
                <li>New accounts receive a free trial. After that, features depend on the package you choose.</li>
                <li>Prices are shown on the <a href="{{ route('pricing') }}" class="text-sky-400 hover:text-sky-300">pricing page</a> and may change; a change does not affect a package you have already bought.</li>
                <li>A package starts when we confirm your payment and ends after its stated duration. Packages do not renew automatically.</li>
                <li>Payments are non-refundable except where the law requires otherwise or we agree in writing.</li>
            </ul>
        </section>

        <section>
            <h2>Your content</h2>
            <p>
                You keep ownership of the studies and reports you upload or write. You give us permission to store, process and display
                them only as needed to provide the service to you and to the people you share them with.
            </p>
        </section>

        <section>
            <h2>Availability</h2>
            <p>
                We work to keep the service available and your data safe, but we cannot promise it will be uninterrupted or error-free.
                Keep your own copies of original studies; the service is not a substitute for your institution's archive.
            </p>
        </section>

        <section>
            <h2>Suspension and closing accounts</h2>
            <p>
                You may stop using the service at any time. We may suspend or close accounts that break these terms or put other users
                or patients at risk.
            </p>
        </section>

        <section>
            <h2>Liability</h2>
            <p>
                To the extent the law allows, we are not liable for indirect or consequential losses, or for clinical decisions made
                using the service. Nothing in these terms limits liability that cannot be limited by law.
            </p>
        </section>

        <section>
            <h2>Changes and contact</h2>
            <p>
                We may update these terms and will post the new version here. Questions? <a href="{{ route('contact') }}" class="text-sky-400 hover:text-sky-300">Contact us</a>.
                See also our <a href="{{ route('privacy') }}" class="text-sky-400 hover:text-sky-300">privacy policy</a>.
            </p>
        </section>
    </article>
</x-layouts::site>
