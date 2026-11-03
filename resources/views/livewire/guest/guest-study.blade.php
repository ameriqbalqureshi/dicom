<div class="flex flex-col gap-6">
    @if ($link === null)
        <div class="mx-auto mt-16 max-w-md rounded-xl border border-slate-800 bg-slate-900 p-6 text-center">
            <h1 class="text-lg font-semibold">This link is no longer available</h1>
            <p class="mt-2 text-sm text-slate-400">Guest links expire after {{ App\Models\GuestLink::LIFETIME_HOURS }} hours and can be revoked at any time. Ask the person who sent it for a new one.</p>
        </div>
    @elseif (! $link->isUnlocked())
        <form wire:submit="unlock" class="mx-auto mt-16 flex w-full max-w-sm flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-6">
            <div>
                <h1 class="text-lg font-semibold">Enter the PIN</h1>
                <p class="mt-1 text-sm text-slate-400">{{ $link->creator->name }} shared a study with you. Enter the 6-digit PIN they sent you separately.</p>
            </div>

            <input wire:model="pin" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus
                aria-label="PIN" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 text-center font-mono text-lg tracking-[0.5em] focus:border-sky-500 focus:outline-none">

            @error('pin')
                <p class="text-sm text-red-400">{{ $message }}</p>
            @enderror

            <button type="submit" class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500">View study</button>
        </form>
    @else
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">{{ $study->patient_name ?? 'Unknown patient' }}</h1>
                <dl class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-sm text-slate-400">
                    <div><dt class="inline">Patient ID:</dt> <dd class="inline text-slate-200">{{ $study->patient_id ?? '—' }}</dd></div>
                    <div><dt class="inline">Sex:</dt> <dd class="inline text-slate-200">{{ $study->patient_sex ?? '—' }}</dd></div>
                    <div><dt class="inline">Born:</dt> <dd class="inline text-slate-200">{{ $study->patient_birth_date?->format('Y-m-d') ?? '—' }}</dd></div>
                    <div><dt class="inline">Study date:</dt> <dd class="inline text-slate-200">{{ $study->study_date?->format('Y-m-d') ?? '—' }}</dd></div>
                    <div><dt class="inline">Description:</dt> <dd class="inline text-slate-200">{{ $study->study_description ?? '—' }}</dd></div>
                </dl>
            </div>

            <p class="rounded-md border border-amber-800 bg-amber-950/60 px-3 py-2 text-xs text-amber-200">
                Shared by {{ $link->creator->name }} · guest view expires {{ $link->expires_at->diffForHumans() }}
            </p>
        </div>

        @if ($reports->isNotEmpty())
            <section class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-4">
                <h2 class="text-sm font-medium">Reports</h2>

                @foreach ($reports as $report)
                    <article wire:key="report-{{ $report->id }}" class="flex flex-col gap-3 rounded-md border border-slate-800 p-4 text-sm">
                        <header class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="font-medium">{{ $report->authorName() }}</div>
                                <div class="text-xs text-slate-400">Signed {{ $report->finalized_at->toDayDateTimeString() }}</div>
                            </div>
                            <a href="{{ route('guest.reports.pdf', [$token, $report]) }}" target="_blank" class="text-sky-400 hover:text-sky-300">Download PDF</a>
                        </header>

                        @include('reports.partials.body', ['report' => $report])
                    </article>
                @endforeach
            </section>
        @endif

        @if ($comments->isNotEmpty())
            <section class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <h2 class="text-sm font-medium">Comments <span class="ml-1 text-slate-400">({{ $comments->count() }})</span></h2>
                    <a href="{{ route('guest.comments.pdf', $token) }}" target="_blank" class="text-sm text-sky-400 hover:text-sky-300">Download PDF</a>
                </div>

                @foreach ($comments as $comment)
                    <article wire:key="comment-{{ $comment->id }}" class="flex flex-col gap-2 rounded-md border border-slate-800 p-3 text-sm">
                        <div>
                            <span class="font-medium">{{ $comment->authorName() }}</span>
                            <span class="ml-1 text-xs text-slate-400" title="{{ $comment->created_at->toDayDateTimeString() }}">
                                {{ $comment->created_at->diffForHumans() }}@if ($comment->isEdited()) · edited @endif
                            </span>
                        </div>
                        <p class="whitespace-pre-line text-slate-200">{{ $comment->body }}</p>
                    </article>
                @endforeach
            </section>
        @endif

        @if ($viewerSeries === [])
            <p class="rounded-xl border border-slate-800 px-4 py-10 text-center text-slate-400">This study has no images.</p>
        @else
            <x-dicom-viewer :series="$viewerSeries" />
        @endif
    @endif
</div>
