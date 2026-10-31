<section class="flex flex-col gap-4 rounded-xl border border-slate-800 bg-slate-900 p-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h2 class="text-sm font-medium">
            Reports
            <span class="ml-1 text-slate-400">({{ $reports->filter->isFinal()->count() }} final)</span>
        </h2>

        @if ($canWrite && ! $editing)
            <button type="button" wire:click="startReport" class="rounded-md border border-sky-600 px-3 py-1.5 text-sm text-sky-300 hover:bg-sky-950">
                Write a report
            </button>
        @endif
    </div>

    @if ($status)
        <p class="rounded-md border border-emerald-800 bg-emerald-950 px-4 py-2 text-sm text-emerald-300">{{ $status }}</p>
    @endif

    @error('reports')
        <p class="rounded-md border border-red-800 bg-red-950 px-4 py-2 text-sm text-red-300">{{ $message }}</p>
    @enderror

    @if ($canWrite && ! $canSign)
        <p class="rounded-md border border-amber-800 bg-amber-950/60 px-3 py-2 text-xs text-amber-200">
            Your physician account is waiting for verification. You can write and save drafts, and sign them once an administrator has verified your registration.
        </p>
    @endif

    @if ($editing)
        <form wire:submit="saveDraft" class="flex flex-col gap-3 rounded-md border border-slate-700 p-4">
            <h3 class="text-sm font-medium">{{ $draftId ? 'Edit draft report' : 'New report' }}</h3>

            @if ($credentialsMissing)
                <p class="rounded-md border border-amber-800 bg-amber-950/60 px-3 py-2 text-xs text-amber-200">
                    Your registration number is not set, so it will not appear on the signed report.
                    <a href="{{ route('profile.edit') }}" class="underline hover:text-white">Add your credentials and signature</a> before finalizing.
                </p>
            @endif

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-slate-300">Clinical indication <span class="text-slate-500">(optional)</span></span>
                <textarea wire:model="indication" rows="2" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 focus:border-sky-500 focus:outline-none"></textarea>
                @error('indication') <span class="text-red-400">{{ $message }}</span> @enderror
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-slate-300">Findings</span>
                <textarea wire:model="findings" rows="8" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 focus:border-sky-500 focus:outline-none"></textarea>
                @error('findings') <span class="text-red-400">{{ $message }}</span> @enderror
            </label>

            <label class="flex flex-col gap-1 text-sm">
                <span class="text-slate-300">Impression</span>
                <textarea wire:model="impression" rows="3" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 focus:border-sky-500 focus:outline-none"></textarea>
                @error('impression') <span class="text-red-400">{{ $message }}</span> @enderror
            </label>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-md border border-sky-600 px-3 py-1.5 text-sm text-sky-300 hover:bg-sky-950">Save draft</button>
                @if ($canSign)
                    <button type="button" wire:click="saveAndFinalize"
                        wire:confirm="Finalize this report? It will be signed with your name and visible to everyone who can open this study. You will not be able to edit it, only add addenda."
                        class="rounded-md bg-sky-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-500">Finalize and sign</button>
                @endif
                <button type="button" wire:click="cancelDraft" class="text-sm text-slate-400 hover:text-white">Cancel</button>
            </div>
        </form>
    @endif

    @if ($reports->isEmpty() && ! $editing)
        <p class="text-sm text-slate-400">No reports yet.</p>
    @endif

    @foreach ($reports as $report)
        <article wire:key="report-{{ $report->id }}" class="flex flex-col gap-3 rounded-md border {{ $report->isFinal() ? 'border-slate-800' : 'border-dashed border-amber-800' }} p-4 text-sm">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="font-medium">
                        {{ $report->authorName() }}
                        @if ($report->isFinal())
                            <span class="ml-1 rounded bg-emerald-900 px-1.5 py-0.5 text-xs text-emerald-200">Final</span>
                        @else
                            <span class="ml-1 rounded bg-amber-900 px-1.5 py-0.5 text-xs text-amber-200">Draft · only you can see this</span>
                        @endif
                    </div>
                    <div class="text-xs text-slate-400">
                        @if ($report->isFinal())
                            Signed {{ $report->finalized_at->toDayDateTimeString() }}
                        @else
                            Last saved {{ $report->updated_at->diffForHumans() }}
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('reports.pdf', $report) }}" target="_blank" class="text-sky-400 hover:text-sky-300">
                        {{ $report->isFinal() ? 'Download PDF' : 'Preview PDF' }}
                    </a>

                    @can('update', $report)
                        @unless ($draftId === $report->id)
                            <button type="button" wire:click="editDraft({{ $report->id }})" class="text-slate-300 hover:text-white">Edit</button>
                            @can('finalize', $report)
                                <button type="button" wire:click="finalize({{ $report->id }})"
                                    wire:confirm="Finalize this report? It will be signed with your name and visible to everyone who can open this study. You will not be able to edit it, only add addenda."
                                    class="text-emerald-400 hover:text-emerald-300">Finalize</button>
                            @endcan
                        @endunless
                        <button type="button" wire:click="discardDraft({{ $report->id }})" wire:confirm="Discard this draft report?"
                            class="text-red-400 hover:text-red-300">Discard</button>
                    @endcan

                    @can('addAddendum', $report)
                        @if ($addendumReportId !== $report->id)
                            <button type="button" wire:click="startAddendum({{ $report->id }})" class="text-slate-300 hover:text-white">Add addendum</button>
                        @endif
                    @endcan
                </div>
            </header>

            @include('reports.partials.body', ['report' => $report])

            @if ($addendumReportId === $report->id)
                <form wire:submit="saveAddendum" class="flex flex-col gap-2 border-t border-slate-800 pt-3">
                    <label class="flex flex-col gap-1">
                        <span class="text-slate-300">Addendum</span>
                        <textarea wire:model="addendum" rows="3" class="rounded-md border border-slate-700 bg-slate-950 px-3 py-2 focus:border-sky-500 focus:outline-none"></textarea>
                        @error('addendum') <span class="text-red-400">{{ $message }}</span> @enderror
                    </label>
                    <p class="text-xs text-slate-400">Addenda are dated and added to the signed report. They cannot be changed afterwards.</p>
                    <div class="flex items-center gap-3">
                        <button type="submit" class="rounded-md border border-sky-600 px-3 py-1.5 text-sky-300 hover:bg-sky-950">Add addendum</button>
                        <button type="button" wire:click="cancelAddendum" class="text-slate-400 hover:text-white">Cancel</button>
                    </div>
                </form>
            @endif
        </article>
    @endforeach
</section>
